(()=>{'use strict';let csrf='';const $=s=>document.querySelector(s),money=n=>new Intl.NumberFormat('cs-CZ',{style:'currency',currency:'CZK',maximumFractionDigits:0}).format((Number(n)||0)/100),esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function api(action,options={}){const headers={'Content-Type':'application/json',...(options.headers||{})};if(options.method==='POST'&&csrf)headers['X-CSRF-Token']=csrf;const r=await fetch('/api/admin.php?action='+encodeURIComponent(action),{credentials:'same-origin',...options,headers});const d=await r.json().catch(()=>({ok:false,error:'Server vrátil neplatnou odpověď.'}));if(!r.ok||!d.ok){if(r.status===401&&action!=='login'){csrf='';showLogin(d.error);await refreshSession();}throw new Error(d.error||'Operace se nezdařila.');}return d;}
function message(text,error=false){const el=$('#status')||$('.adminMessage');if(el){el.textContent=text;el.classList.toggle('error',error);}}
function select(kind,id,current,values){return `<select data-kind="${kind}" data-id="${id}">${values.map(([v,l])=>`<option value="${v}" ${v===current?'selected':''}>${l}</option>`).join('')}</select>`;}
function render(data){$('#summary').innerHTML=`<div><b>${data.summary.affiliates}</b><span>Partnerů</span></div><div><b>${data.summary.orders}</b><span>Objednávek</span></div><div><b>${data.summary.paidOrders}</b><span>Zaplacených</span></div><div><b>${data.summary.pendingPayouts}</b><span>Čekajících výplat</span></div>`;
$('#orders').innerHTML=`<h2>Objednávky</h2><div class="tableWrap"><table><thead><tr><th>Číslo</th><th>Zákazník</th><th>Částka</th><th>Partner</th><th>Stav</th><th>Datum</th></tr></thead><tbody>${data.orders.map(o=>`<tr><td><strong>${esc(o.order_number)}</strong></td><td>${esc(o.customer_name)}<small>${esc(o.customer_email)}</small></td><td>${money(o.total_minor)}</td><td>${esc(o.referral_code||'—')}</td><td>${select('order',o.id,o.status,[['awaiting_payment','Čeká na platbu'],['paid','Zaplaceno'],['cancelled','Zrušeno']])}</td><td>${esc(o.created_at)}</td></tr>`).join('')||'<tr><td colspan="6">Zatím nejsou žádné objednávky.</td></tr>'}</tbody></table></div>`;
$('#affiliates').innerHTML=`<h2>Affiliate partneři</h2><div class="adminCards">${data.affiliates.map(a=>`<form class="partnerCard" data-id="${a.id}"><div><strong>${esc(a.code)}</strong><small>${a.referral_count} doporučení · ${money(a.commission_minor)}</small></div><label>Jméno<input name="firstName" value="${esc(a.first_name)}" required></label><label>Příjmení<input name="lastName" value="${esc(a.last_name)}" required></label><label>E-mail<input name="email" type="email" value="${esc(a.email)}" required></label><label>Telefon<input name="phone" value="${esc(a.phone)}"></label><label>Město<input name="city" value="${esc(a.city)}"></label><label>IČO<input name="ico" value="${esc(a.ico)}"></label><label class="wide">Číslo účtu<input name="bankAccount" value="${esc(a.bank_account)}"></label><button class="btn">Uložit partnera</button></form>`).join('')||'<p>Zatím nejsou žádní partneři.</p>'}</div>`;
$('#payouts').innerHTML=`<h2>Výplaty a faktury</h2><div class="tableWrap"><table><thead><tr><th>Partner</th><th>Částka</th><th>Faktura</th><th>Stav</th><th>Žádost</th></tr></thead><tbody>${data.payouts.map(p=>`<tr><td>${esc(p.first_name+' '+p.last_name)}<small>${esc(p.code)} · ${esc(p.bank_account||'bez účtu')}</small></td><td>${money(p.amount_minor)}</td><td>${esc(p.invoice_number||'—')}</td><td>${select('payout',p.id,p.status,[['requested','Požadováno'],['approved','Schváleno'],['paid','Vyplaceno'],['rejected','Zamítnuto']])}</td><td>${esc(p.requested_at)}</td></tr>`).join('')||'<tr><td colspan="5">Zatím nejsou žádné žádosti o výplatu.</td></tr>'}</tbody></table></div>`;
document.querySelectorAll('select[data-kind]').forEach(el=>el.addEventListener('change',changeStatus));document.querySelectorAll('.partnerCard').forEach(el=>el.addEventListener('submit',savePartner));}
async function load(){try{message('Načítám údaje…');const d=await api('dashboard');render(d);message('Údaje jsou aktuální.');}catch(e){message(e.message,true);}}
async function changeStatus(e){const el=e.currentTarget;if(!confirm('Opravdu chcete změnit stav?')){await load();return;}try{await api(el.dataset.kind==='order'?'order-status':'payout-status',{method:'POST',body:JSON.stringify({id:Number(el.dataset.id),status:el.value})});message('Stav byl uložen.');await load();}catch(err){message(err.message,true);await load();}}
async function savePartner(e){e.preventDefault();const f=e.currentTarget,d=Object.fromEntries(new FormData(f));d.id=Number(f.dataset.id);try{await api('affiliate-update',{method:'POST',body:JSON.stringify(d)});message('Účet partnera byl uložen.');await load();}catch(err){message(err.message,true);}}
function showLogin(text='') {
  ['dashboard','forgotCard','resetCard','logout'].forEach(id=>$('#'+id).classList.add('hide'));
  $('#loginCard').classList.remove('hide');
  $('#loginForm .adminMessage').textContent=text;
  $('#loginForm .adminMessage').classList.remove('error');
  ['orders','affiliates','payouts','summary'].forEach(id=>$('#'+id).replaceChildren());
}
function showDashboard(){['loginCard','forgotCard','resetCard'].forEach(id=>$('#'+id).classList.add('hide'));$('#dashboard').classList.remove('hide');$('#logout').classList.remove('hide');}
let resetToken='';
const resetMatch=location.hash.match(/^#reset=([a-f0-9]{64})$/);
if(resetMatch) resetToken=resetMatch[1];
if(location.hash) history.replaceState(null,'',location.pathname+location.search);
async function refreshSession(){const s=await api('session');csrf=s.csrf;return s;}
const ready=(async()=>{try{const s=await refreshSession();if(resetToken){$('#loginCard').classList.add('hide');$('#resetCard').classList.remove('hide');}else if(s.authenticated){showDashboard();await load();}}catch(e){$('#loginForm .adminMessage').textContent=e.message;}})();
function onForm(id,handler){$('#'+id).addEventListener('submit',async e=>{
  e.preventDefault();const form=e.currentTarget,button=form.querySelector('button'),notice=form.querySelector('.adminMessage');
  button.disabled=true;notice.textContent='Pracuji…';notice.classList.remove('error');
  try{await ready;if(!csrf)await refreshSession();await handler(form,notice);}catch(err){notice.textContent=err.message;notice.classList.add('error');}finally{button.disabled=false;}
});}
onForm('loginForm',async(form,notice)=>{const d=Object.fromEntries(new FormData(form));const r=await api('login',{method:'POST',body:JSON.stringify(d)});csrf=r.csrf;form.reset();notice.textContent='';showDashboard();await load();});
$('#logout').addEventListener('click',async()=>{try{await api('logout',{method:'POST',body:'{}'});csrf='';showLogin('Byli jste odhlášeni.');await refreshSession();}catch(e){message(e.message,true);}});
$('#refresh').addEventListener('click',load);
document.querySelectorAll('.adminTabs button').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('.adminTabs button').forEach(x=>x.classList.toggle('active',x===b));document.querySelectorAll('.adminPanel').forEach(p=>p.classList.toggle('hide',p.id!==b.dataset.tab));}));
onForm('passwordForm',async(form)=>{const r=await api('change-password',{method:'POST',body:JSON.stringify(Object.fromEntries(new FormData(form)))});form.reset();csrf='';showLogin(r.message);await refreshSession();});
$('#forgotPassword').addEventListener('click',()=>{$('#loginCard').classList.add('hide');$('#forgotCard').classList.remove('hide');});
document.querySelectorAll('.backToLogin').forEach(b=>b.addEventListener('click',()=>{resetToken='';showLogin();}));
onForm('forgotForm',async(form,notice)=>{const r=await api('forgot-password',{method:'POST',body:JSON.stringify(Object.fromEntries(new FormData(form)))});notice.textContent=r.message;});
onForm('resetForm',async(form)=>{const d=Object.fromEntries(new FormData(form));d.token=resetToken;const r=await api('reset-password',{method:'POST',body:JSON.stringify(d)});form.reset();resetToken='';csrf='';showLogin(r.message);await refreshSession();});
})();
