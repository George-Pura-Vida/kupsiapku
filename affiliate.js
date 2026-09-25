const form=document.querySelector('#affiliateForm');
const preview=document.querySelector('#refPreview');
const linkEl=document.querySelector('#shareLink');
const statusEl=document.querySelector('#affiliateStatus');
const qrEl=document.querySelector('#affiliateQr');
const tokenKey='ksa_affiliate_token';
let sessionToken='';
function getToken(){try{return localStorage.getItem(tokenKey)||sessionToken;}catch(_){return sessionToken;}}
function saveToken(token){sessionToken=token;try{localStorage.setItem(tokenKey,token);}catch(_){setStatus('Profil je vytvořený. Telefon ale blokuje trvalé uložení; tento odkaz si uložte.','info');}}
function forgetToken(){sessionToken='';try{localStorage.removeItem(tokenKey);}catch(_){}}

function setStatus(message,type='info'){document.querySelectorAll('.affiliateStatus').forEach(el=>{el.textContent=message;el.className='formStatus affiliateStatus '+type;});}
function drawQr(canvas,value){const code=qrcode(0,'M');code.addData(value);code.make();const modules=code.getModuleCount();const quiet=4;const total=modules+quiet*2;const size=1024;const cell=size/total;canvas.width=size;canvas.height=size;const context=canvas.getContext('2d');context.imageSmoothingEnabled=false;context.fillStyle='#fff';context.fillRect(0,0,size,size);context.fillStyle='#000';for(let row=0;row<modules;row++){for(let col=0;col<modules;col++){if(code.isDark(row,col)){const x=Math.floor((col+quiet)*cell);const y=Math.floor((row+quiet)*cell);const w=Math.ceil((col+quiet+1)*cell)-x;const h=Math.ceil((row+quiet+1)*cell)-y;context.fillRect(x,y,w,h);}}}}
function render(profile,stats){
  preview.textContent=profile.code;
  linkEl.textContent=profile.shareUrl;
  document.querySelector('#sharePreview').textContent=profile.firstName+', toto je tvůj osobní doporučitelský odkaz.';
  drawQr(qrEl,profile.shareUrl); qrEl.setAttribute('aria-label','QR kód doporučitelského odkazu '+profile.code);
  document.querySelector('#qrDownload').dataset.code=profile.code;
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
  const token=getToken(); if(!token)return;
  try{const data=await api('profile',{headers:{'X-Affiliate-Token':token}});render(data.profile,data.stats);setStatus('Profil a statistiky jsou načtené ze serveru.','success');}
  catch(error){forgetToken();setStatus(error.message,'error');}
}
form.addEventListener('submit',async event=>{
  event.preventDefault(); const button=form.querySelector('button[type=submit]'); button.disabled=true; setStatus('Vytvářím bezpečný partnerský profil…');
  const payload={firstName:form.elements.firstName.value,lastName:form.elements.lastName.value,email:form.elements.email.value,phone:form.elements.phone.value,street:form.elements.street.value,zip:form.elements.zip.value,city:form.elements.city.value,country:form.elements.country.value,privacy:!!form.elements.privacy.checked};
  try{const data=await api('register',{method:'POST',body:JSON.stringify(payload)});saveToken(data.token);render(data.profile,{clicks:0,registrations:0,purchases:0,commissionMinor:0});setStatus(data.emailSent?'Hotovo. Kód, QR i potvrzovací e-mail jsou připravené.':'Kód a QR jsou připravené. E-mail se nepodařilo odeslat; osobní odkaz si uložte.','success');}
  catch(error){setStatus(error.message,'error');button.disabled=false;}
});
document.querySelector('#copyLink').addEventListener('click',async()=>{await navigator.clipboard.writeText(linkEl.textContent);setStatus('Odkaz je zkopírovaný.','success');});
document.querySelector('#qrDownload').addEventListener('click',event=>{
  event.preventDefault();
  const downloadCode=event.currentTarget.dataset.code||'kod';
  qrEl.toBlob(blob=>{if(!blob){setStatus('PNG se nepodařilo vytvořit.','error');return;}const url=URL.createObjectURL(blob);const link=document.createElement('a');link.href=url;link.download='kupsiapku-qr-'+downloadCode+'.png';document.body.appendChild(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);setStatus('QR byl stažen jako PNG.','success');},'image/png');
});
document.querySelector('#resendEmail').addEventListener('click',async event=>{const button=event.currentTarget;const token=getToken();if(!token){setStatus('E-mail lze znovu poslat jen z registračního zařízení.','error');return;}button.disabled=true;setStatus('Odesílám e-mail…');try{const data=await api('resend',{method:'POST',headers:{'X-Affiliate-Token':token},body:'{}'});setStatus(data.message,'success');}catch(error){setStatus(error.message,'error');}finally{button.disabled=false;}});
document.querySelector('#nativeShare').addEventListener('click',async()=>{const url=linkEl.textContent;if(navigator.share)await navigator.share({title:'Kup si apku',text:'Mrkni na praktické aplikace.',url});else{await navigator.clipboard.writeText(url);setStatus('Odkaz je zkopírovaný.','success');}});
document.querySelector('#loginButton').addEventListener('click',async()=>{const email=document.querySelector('#loginEmail').value.trim();if(!email){setStatus('Zadejte registrační e-mail.','error');return;}setStatus('Odesílám přihlašovací odkaz…');try{const data=await api('login',{method:'POST',body:JSON.stringify({email})});setStatus(data.message,'success');}catch(error){setStatus(error.message,'error');}});
const fragment=new URLSearchParams(location.hash.slice(1));const loginToken=fragment.get('token');if(loginToken){saveToken(loginToken);history.replaceState(null,'',location.pathname+location.search);}
const publicCode=new URLSearchParams(location.search).get('code');
if(publicCode&&!getToken()){
  const shareUrl='https://kupsiapku.cz/?ref='+encodeURIComponent(publicCode);
  render({code:publicCode,firstName:'Partner',shareUrl},{clicks:0,registrations:0,purchases:0,commissionMinor:0});
  setStatus('Veřejný náhled kódu. Statistiky jsou dostupné pouze na registračním zařízení.');
}else loadProfile();

