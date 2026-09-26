/* Shared sticky header and purchase UI. */
(()=>{
'use strict';
const header=document.querySelector('.siteHeader');
if(!header)return;
const en=document.documentElement.lang.toLowerCase().startsWith('en');
const path=location.pathname.toLowerCase();
const rules=[['finance',['financ']],['zdravi',['zdravi','health']],['portfolio',['portfolio','investic']],['cile',['cile','goals']],['vztahy',['vztahy','relationship']],['rozvoj',['rozvoj','development']],['firma',['firma','company']],['podnikani',['podnikani','business']],['prace',['prace','work']]];
const found=rules.find(([,words])=>words.some(w=>path.includes(w)));
const product=found?found[0]:null;
const checkout=en?'/en/index.html#checkout':'/index.html#checkout';
header.innerHTML=`<div class="siteHeader-inner"><a class="siteHeader-brand" href="${en?'/en/':'/'}"><span aria-hidden="true">🚀</span> KUP SI <b>APKU</b></a><div class="siteHeader-languages"><a href="${en?'/':'/en/'}" aria-label="${en?'Čeština':'English'}"><span class="siteHeader-flag ${en?'siteHeader-flagCZ':'siteHeader-flagGB'}" aria-hidden="true"></span></a></div><a class="siteHeader-cartQuick" data-cart-checkout href="${checkout}" aria-label="${en?'Cart':'Košík'}"><span aria-hidden="true">🛒</span><strong data-cart-count>0</strong></a><button class="siteHeader-toggle" type="button" aria-expanded="false" aria-controls="site-navigation" aria-label="${en?'Open menu':'Otevřít menu'}"><span aria-hidden="true">☰</span></button><nav class="siteHeader-nav" id="site-navigation"><a href="${en?'/en/index.html#apps':'/index.html#apps'}">${en?'Apps':'Aplikace'}</a><a href="${en?'/en/index.html#showcase':'/index.html#showcase'}">${en?'Showcase':'Ukázky'}</a><a href="${en?'/en/index.html#how':'/index.html#how'}">${en?'How it works':'Jak to funguje'}</a><a href="${en?'/en/index.html#faq':'/index.html#faq'}">FAQ</a><a href="${en?'/en/index.html#about':'/index.html#about'}">${en?'About me':'O mně'}</a><a href="${checkout}">${en?'Order':'Objednat'}</a><a class="siteHeader-cart" data-cart-checkout href="${checkout}"><span aria-hidden="true">🛒</span> ${en?'Cart':'Košík'} <strong data-cart-count>0</strong></a></nav></div>`;
const toggle=header.querySelector('.siteHeader-toggle'),nav=header.querySelector('.siteHeader-nav'),mobile=matchMedia('(max-width:1100px)');
function setOpen(open){toggle.setAttribute('aria-expanded',String(open));toggle.firstElementChild.textContent=open?'✕':'☰';nav.hidden=mobile.matches&&!open}
setOpen(false);toggle.addEventListener('click',()=>setOpen(toggle.getAttribute('aria-expanded')!=='true'));nav.addEventListener('click',()=>{if(mobile.matches)setOpen(false)});mobile.addEventListener('change',()=>setOpen(false));
function loadCart(){if(window.KupSiApkuCart)return Promise.resolve();return new Promise((resolve,reject)=>{let s=document.querySelector('script[data-kupsiapku-cart]');if(s){s.addEventListener('load',resolve,{once:true});return}s=document.createElement('script');s.src='/cart.js?v=20260926-3';s.dataset.kupsiapkuCart='1';s.onload=resolve;s.onerror=reject;document.body.append(s)})}
if(product){
 const hero=document.querySelector('.hero');
 const actions=hero?.querySelector('.actions')||hero?.querySelector('p:has(.btn)');
 if(actions){const more=actions.querySelector('a[href^="#"]');actions.innerHTML=`<button class="btn primary" type="button" data-cart-add="${product}">🛒 ${en?'Add to cart':'Vložit do košíku'}</button> <button class="btn secondary" type="button" data-cart-buy="${product}">⚡ ${en?'Order':'Objednat'}</button>`;if(more)actions.append(' ',more)}
}
loadCart().catch(()=>console.error('KupSiApku: cart.js se nepodařilo načíst'));
})();
