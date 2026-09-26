/* One controller for the shared sticky header and product purchase UI. */
(()=>{
'use strict';
const header=document.querySelector('.siteHeader');
if(!header)return;
const en=document.documentElement.lang.toLowerCase().startsWith('en');
const path=location.pathname;
const product=path.includes('financ')?'finance':path.includes('health')||path.includes('zdravi')?'zdravi':path.includes('portfolio')?'portfolio':null;
const pairs={finance:['/moje-finance.html','/en/my-finances.html'],zdravi:['/moje-zdravi.html','/en/my-health.html'],portfolio:['/moje-portfolio.html','/en/my-portfolio.html']};
const pair=product?pairs[product]:['/','/en/'];
header.innerHTML=`<div class="siteHeader-inner"><a class="siteHeader-brand" href="${en?'/en/':'/'}"><span aria-hidden="true">🚀</span> ${en?'BUY AN':'KUP SI'} <b>${en?'APP':'APKU'}</b></a><div class="siteHeader-languages" aria-label="${en?'Language':'Jazyk'}"><a href="${pair[0]}" lang="cs" ${en?'':'aria-current="page"'}><span class="siteHeader-flag siteHeader-flagCZ" aria-hidden="true"></span>CZ</a><span aria-hidden="true">|</span><a href="${pair[1]}" lang="en" ${en?'aria-current="page"':''}><span class="siteHeader-flag siteHeader-flagGB" aria-hidden="true"></span>EN</a></div><button class="siteHeader-toggle" type="button" aria-expanded="false" aria-controls="site-navigation" aria-label="${en?'Open menu':'Otevřít menu'}"><span aria-hidden="true">☰</span></button><nav class="siteHeader-nav" id="site-navigation" aria-label="${en?'Main navigation':'Hlavní navigace'}"><a href="${en?'/en/index.html#apps':'/index.html#apps'}">${en?'Apps':'Aplikace'}</a><a href="${en?'/en/doporucit.html':'/doporucit.html'}">${en?'Refer & earn':'Doporučit a vydělat'}</a><a href="${en?'/en/ucet.html':'/ucet.html'}">${en?'My account':'Můj účet'}</a><a class="siteHeader-cart" data-cart-checkout href="${en?'/en/index.html#checkout':'/index.html#checkout'}"><span aria-hidden="true">🛒</span> ${en?'Cart':'Košík'} <strong data-cart-count>0</strong></a></nav></div>`;
const toggle=header.querySelector('.siteHeader-toggle');
const nav=header.querySelector('.siteHeader-nav');
const mobile=matchMedia('(max-width:1100px)');
function setOpen(open,focus=false){toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',en?(open?'Close menu':'Open menu'):(open?'Zavřít menu':'Otevřít menu'));toggle.firstElementChild.textContent=open?'✕':'☰';nav.hidden=mobile.matches&&!open;if(focus)toggle.focus()}
setOpen(false);
toggle.addEventListener('click',()=>setOpen(toggle.getAttribute('aria-expanded')!=='true'));
nav.addEventListener('click',e=>{if(e.target.closest('a,button')&&mobile.matches)setOpen(false)});
document.addEventListener('keydown',e=>{if(e.key==='Escape')setOpen(false,true)});
document.addEventListener('click',e=>{if(!header.contains(e.target)&&mobile.matches)setOpen(false)});
mobile.addEventListener('change',()=>setOpen(false));
if(product){
  const actions=document.querySelector('.hero .actions');
  if(actions){const more=actions.querySelector('a[href^="#"]');actions.innerHTML=`<button class="btn primary" type="button" data-cart-add="${product}">🛒 ${en?'Add to cart':'Přidat do košíku'}</button><button class="btn secondary" type="button" data-cart-buy="${product}">⚡ ${en?'Buy now':'Koupit nyní'}</button>`;if(more)actions.append(more)}
  if(!document.querySelector('script[src="/cart.js"]')){const s=document.createElement('script');s.src='/cart.js';s.defer=true;document.body.append(s)}
}
})();
