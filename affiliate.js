const form=document.querySelector('#affiliateForm');
const preview=document.querySelector('#refPreview');
const linkEl=document.querySelector('#shareLink');
const statusEl=document.querySelector('#affiliateStatus');
const qrEl=document.querySelector('#affiliateQr');
const tokenKey='ksa_affiliate_token';

function setStatus(message,type='info'){statusEl.textContent=message;statusEl.className='formStatus '+type;}
function qrData(value){const code=qrcode(0,'M');code.addData(value);code.make();return 'data:image/svg+xml;charset=utf-8,'+encodeURIComponent(code.createSvgTag({cellSize:6,margin:4,scalable:true}));}
function render(profile,stats){
  preview.textContent=profile.code;
  linkEl.textContent=profile.shareUrl;
  document.querySelector('#sharePreview').textContent=profile.firstName+', toto je tvůj osobní doporučitelský odkaz.';
  qrEl.src=qrData(profile.shareUrl); qrEl.alt='QR kód doporučitelského odkazu '+profile.code;
  document.querySelector('#qrDownload').href=qrData(profile.shareUrl);
  document.querySelector('#affiliateResult').hidden=false;
  form.hidden=true;
  if(stats){
    document.querySelector('#statClicks').textContent=stats.clicks;
    document.querySelector('#statRegistrations').textContent=stats.registrations;
    document.querySelector('#statPurchases').textContent=stats.purchases;
    document.querySelector('#statCommission').textContent=(stats.commissionMinor/100).toLocaleString('cs-CZ')+' Kč';
  }
}
async function api(action,options={}){
  const response=await fetch('/api/affiliate.php?action='+action,{...options,headers:{'Content-Type':'application/json',...(options.headers||{})}});
  const data=await response.json().catch(()=>({ok:false,error:'Server vrátil neplatnou odpověď.'}));
  if(!response.ok)throw new Error(data.error||'Operaci se nepodařilo dokončit.');
  return data;
}
async function loadProfile(){
  const token=localStorage.getItem(tokenKey); if(!token)return;
  try{const data=await api('profile',{headers:{'X-Affiliate-Token':token}});render(data.profile,data.stats);setStatus('Profil a statistiky jsou načtené ze serveru.','success');}
  catch(error){localStorage.removeItem(tokenKey);setStatus(error.message,'error');}
}
form.addEventListener('submit',async event=>{
  event.preventDefault(); const button=form.querySelector('button[type=submit]'); button.disabled=true; setStatus('Vytvářím bezpečný partnerský profil…');
  const payload=Object.fromEntries(new FormData(form).entries()); payload.privacy=!!form.elements.privacy.checked;
  try{const data=await api('register',{method:'POST',body:JSON.stringify(payload)});localStorage.setItem(tokenKey,data.token);render(data.profile,{clicks:0,registrations:0,purchases:0,commissionMinor:0});setStatus(data.emailSent?'Hotovo. Kód, QR i potvrzovací e-mail jsou připravené.':'Kód a QR jsou připravené. E-mail se nepodařilo odeslat; osobní odkaz si uložte.','success');}
  catch(error){setStatus(error.message,'error');button.disabled=false;}
});
document.querySelector('#copyLink').addEventListener('click',async()=>{await navigator.clipboard.writeText(linkEl.textContent);setStatus('Odkaz je zkopírovaný.','success');});
document.querySelector('#qrDownload').addEventListener('click',event=>{
  event.preventDefault();
  const image=new Image();
  image.onload=()=>{
    const canvas=document.createElement('canvas');canvas.width=1024;canvas.height=1024;
    const context=canvas.getContext('2d');context.fillStyle='#fff';context.fillRect(0,0,1024,1024);context.drawImage(image,0,0,1024,1024);
    canvas.toBlob(blob=>{if(!blob){setStatus('PNG se nepodařilo vytvořit.','error');return;}const url=URL.createObjectURL(blob);const link=document.createElement('a');link.href=url;link.download='kupsiapku-qr-'+(event.currentTarget.dataset.code||'kod')+'.png';document.body.appendChild(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);setStatus('QR byl stažen jako PNG.','success');},'image/png');
  };
  image.onerror=()=>setStatus('QR se nepodařilo převést do PNG.','error');
  image.src=qrEl.src;
});
document.querySelector('#resendEmail').addEventListener('click',async()=>{const token=localStorage.getItem(tokenKey);if(!token){setStatus('E-mail lze znovu poslat jen z registračního zařízení.','error');return;}try{const data=await api('resend',{method:'POST',headers:{'X-Affiliate-Token':token},body:'{}'});setStatus(data.message,'success');}catch(error){setStatus(error.message,'error');}});
document.querySelector('#nativeShare').addEventListener('click',async()=>{const url=linkEl.textContent;if(navigator.share)await navigator.share({title:'Kup si apku',text:'Mrkni na praktické aplikace.',url});else{await navigator.clipboard.writeText(url);setStatus('Odkaz je zkopírovaný.','success');}});
const publicCode=new URLSearchParams(location.search).get('code');
if(publicCode&&!localStorage.getItem(tokenKey)){
  const shareUrl='https://kupsiapku.cz/?ref='+encodeURIComponent(publicCode);
  render({code:publicCode,firstName:'Partner',shareUrl},{clicks:0,registrations:0,purchases:0,commissionMinor:0});
  setStatus('Veřejný náhled kódu. Statistiky jsou dostupné pouze na registračním zařízení.');
}else loadProfile();

