<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

$pdo=db();
$method=$_SERVER['REQUEST_METHOD']??'GET';
$action=$_GET['action']??'summary';
$affiliate=affiliate_from_token($pdo,(string)($_SERVER['HTTP_X_AFFILIATE_TOKEN']??''));
if(!$affiliate) respond(['ok'=>false,'error'=>'Přihlášení partnera není platné.'],401);

function payout_summary(PDO $pdo,int $affiliateId): array {
    $q=$pdo->prepare('SELECT COALESCE(SUM(commission_minor),0) FROM referrals WHERE affiliate_id=? AND status="paid"');
    $q->execute([$affiliateId]);$earned=(int)$q->fetchColumn();
    $q=$pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM payout_requests WHERE affiliate_id=? AND status IN ("requested","approved","paid")');
    $q->execute([$affiliateId]);$reserved=(int)$q->fetchColumn();
    return ['earnedMinor'=>$earned,'reservedMinor'=>$reserved,'availableMinor'=>max(0,$earned-$reserved),'minimumMinor'=>(int)(getenv('AFFILIATE_MIN_PAYOUT_MINOR')?:100000)];
}
function ascii_pdf(string $value): string {
    $value=str_replace(["\r","\n"],' ',$value);
    return iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;
}
function esc_pdf(string $value): string {return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],ascii_pdf($value));}
function invoice_pdf(array $a,string $number,int $amount): string {
    $today=date('d.m.Y');$due=date('d.m.Y',strtotime('+14 days'));$money=number_format($amount/100,2,',',' ').' Kc';
    $lines=[
      ['Faktura c. '.$number,18],['NEJSEM PLATCE DPH',9],['Dodavatel',13],
      [$a['first_name'].' '.$a['last_name'],11],[$a['street'],10],[$a['zip'].' '.$a['city'],10],[$a['country'],10],
      ['ICO: '.($a['ico']?:'-').'  DIC: '.($a['dic']?:'-'),10],['E-mail: '.$a['email'].'  Telefon: '.$a['phone'],9],
      ['Odberatel',13],['Jiri Janousek',11],['Ke Kotlarce 1146/12',10],['150 00 Praha 5, Ceska republika',10],['ICO: 13798804',10],
      ['Datum vystaveni: '.$today,10],['Datum splatnosti: '.$due,10],['Cislo uctu: '.($a['bank_account']?:'-'),10],
      ['IBAN: '.($a['iban']?:'-').'  BIC/SWIFT: '.($a['bic']?:'-'),9],
      ['Popis sluzby',12],['Provize za doporuceni zakazniku prostrednictvim affiliate programu na www.kupsiapku.cz',9],
      ['Mnozstvi: 1 ks',10],['Celkem k uhrade: '.$money,14]
    ];
    $stream="BT\n";$y=800;
    foreach($lines as [$text,$size]){$stream.="/F1 {$size} Tf 50 {$y} Td (".esc_pdf($text).") Tj\n";$stream.="0 0 Td\n";$y-=($size>=14?30:20);}
    $stream.="ET";
    $objects=[
      '<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
      '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
      '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
      '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream"
    ];
    $pdf="%PDF-1.4\n";$offsets=[0];
    foreach($objects as $i=>$object){$offsets[]=strlen($pdf);$n=$i+1;$pdf.="{$n} 0 obj\n{$object}\nendobj\n";}
    $xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
    for($i=1;$i<=count($objects);$i++)$pdf.=sprintf('%010d 00000 n ',$offsets[$i])."\n";
    return $pdf."trailer << /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
}
function email_invoice(array $affiliate,string $number,string $pdf): bool {
    $boundary='KSA'.bin2hex(random_bytes(8));
    $subject='=?UTF-8?B?'.base64_encode('Faktura '.$number.' | Kup si apku').'?=';
    $headers=['MIME-Version: 1.0','From: Kup si apku <info@jirijanousek.cz>','Reply-To: info@jirijanousek.cz','Content-Type: multipart/mixed; boundary="'.$boundary.'"'];
    $body="--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nV příloze je automaticky vytvořená faktura k výplatě affiliate provize.\r\n";
    $body.="--{$boundary}\r\nContent-Type: application/pdf; name=\"faktura-{$number}.pdf\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"faktura-{$number}.pdf\"\r\n\r\n".chunk_split(base64_encode($pdf))."\r\n--{$boundary}--";
    return @mail($affiliate['email'],$subject,$body,implode("\r\n",$headers));
}
if($method==='GET'&&$action==='summary'){
    $s=payout_summary($pdo,(int)$affiliate['id']);
    $q=$pdo->prepare('SELECT p.id,p.amount_minor,p.status,p.requested_at,i.invoice_number FROM payout_requests p LEFT JOIN invoices i ON i.payout_request_id=p.id WHERE p.affiliate_id=? ORDER BY p.id DESC LIMIT 20');
    $q->execute([$affiliate['id']]);respond(['ok'=>true,'summary'=>$s,'payouts'=>$q->fetchAll()]);
}
if($method==='GET'&&$action==='invoice'){
    $id=(int)($_GET['id']??0);$q=$pdo->prepare('SELECT i.pdf_path,i.invoice_number FROM invoices i JOIN payout_requests p ON p.id=i.payout_request_id WHERE i.id=? AND p.affiliate_id=?');$q->execute([$id,$affiliate['id']]);$row=$q->fetch();
    if(!$row||!is_file($row['pdf_path']))respond(['ok'=>false,'error'=>'Faktura nebyla nalezena.'],404);
    header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="faktura-'.$row['invoice_number'].'.pdf"');readfile($row['pdf_path']);exit;
}
if($method==='POST'&&$action==='request'){
    foreach(['ico','bank_account'] as $field)if(trim((string)($affiliate[$field]??''))==='')respond(['ok'=>false,'error'=>'Nejdřív doplňte IČO a číslo účtu.'],422);
    $pdo->beginTransaction();
    try{
      $s=payout_summary($pdo,(int)$affiliate['id']);if($s['availableMinor']<$s['minimumMinor'])throw new RuntimeException('Minimální částka pro výplatu je 1 000 Kč.');
      $amount=$s['availableMinor'];$pdo->prepare('INSERT INTO payout_requests(affiliate_id,amount_minor,status,requested_at) VALUES(?,?,"requested",?)')->execute([$affiliate['id'],$amount,gmdate('c')]);
      $payoutId=(int)$pdo->lastInsertId();$number=date('Y').str_pad((string)$payoutId,5,'0',STR_PAD_LEFT);
      $dir=__DIR__.'/data/invoices';if(!is_dir($dir))mkdir($dir,0770,true);$path=$dir.'/invoice-'.$number.'.pdf';$pdf=invoice_pdf($affiliate,$number,$amount);file_put_contents($path,$pdf);
      $pdo->prepare('INSERT INTO invoices(payout_request_id,invoice_number,pdf_path,status,created_at) VALUES(?,?,?,"created",?)')->execute([$payoutId,$number,$path,gmdate('c')]);
      $invoiceId=(int)$pdo->lastInsertId();$pdo->commit();$sent=email_invoice($affiliate,$number,$pdf);
      if($sent)$pdo->prepare('UPDATE invoices SET emailed_at=? WHERE id=?')->execute([gmdate('c'),$invoiceId]);
      respond(['ok'=>true,'message'=>$sent?'Žádost byla vytvořena a faktura odeslána e-mailem.':'Žádost a faktura byly vytvořeny; e-mail se nepodařilo odeslat.','invoiceId'=>$invoiceId],201);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();respond(['ok'=>false,'error'=>$e instanceof RuntimeException?$e->getMessage():'Žádost se nepodařilo vytvořit.'],422);}
}
respond(['ok'=>false,'error'=>'Neznámá operace.'],404);
