const form=document.querySelector('#donationForm');
const statusEl=document.querySelector('#donationStatus');
const result=document.querySelector('#donationResult');
const qr=document.querySelector('#donationQr');
const qrUrl=value=>'https://quickchart.io/qr?size=360&margin=2&format=svg&text='+encodeURIComponent(value);
document.querySelectorAll('[data-amount]').forEach(button=>button.addEventListener('click',()=>{form.elements.amount.value=button.dataset.amount;form.elements.amount.focus();}));
form.addEventListener('submit',async event=>{
  event.preventDefault(); const button=form.querySelector('button[type=submit]');button.disabled=true;statusEl.textContent='Připravuji údaje k daru…';statusEl.className='formStatus';
  const payload=Object.fromEntries(new FormData(form).entries());payload.amount=Number(payload.amount);
  try{const response=await fetch('/api/donation.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});const data=await response.json();if(!response.ok)throw new Error(data.error||'Dar se nepodařilo připravit.');
    const d=data.donation;document.querySelector('#donationId').textContent=d.id;document.querySelector('#donationAmount').textContent=d.amount.toLocaleString('cs-CZ')+' Kč';document.querySelector('#donationVs').textContent=d.variableSymbol;
    if(d.paymentQrPayload){qr.src=qrUrl(d.paymentQrPayload);qr.alt='Platební QR pro dar '+d.id;document.querySelector('#paymentReady').hidden=false;document.querySelector('#paymentMissing').hidden=true;}
    else{qr.removeAttribute('src');document.querySelector('#paymentReady').hidden=true;document.querySelector('#paymentMissing').hidden=false;}
    result.hidden=false;statusEl.textContent='Děkujeme. Darovací záznam byl vytvořen.';statusEl.className='formStatus success';
  }catch(error){statusEl.textContent=error.message;statusEl.className='formStatus error';button.disabled=false;}
});

