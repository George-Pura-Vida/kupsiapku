/* Shared sticky header and purchase UI. Cart is self-contained so buttons work even if cart.js is cached/missing. */
(()=>{
'use strict';
const en=(document.documentElement.lang||'cs').toLowerCase().startsWith('en');
const checkout=en?'/en/index.html#checkout':'/index.html#checkout';
const KEY='kupsiapku_cart';
const VALID=new Set(['zdravi','finance','portfolio','cile','vztahy','rozvoj','firma','podnikani','prace']);
const ALIAS={investice:'portfolio'};
const norm=v=>{v=String(v||'').trim().toLowerCase();return ALIAS[v]||v};
function read(){try{const a=JSON.parse(localStorage.getItem(KEY)||'[]');return Array.isArray(a)?[...new Set(a.map(norm).filter(x=>VALID.has(x)))]:[]}catch(e){return[]}}
function render(){const n=read().length;document.querySelectorAll('[data-cart-count],#count').forEach(el=>el.textContent=String(n));}
function add(id){id=norm(id);if(!VALID.has(id))return false;const a=read();if(!a.includes(id))a.push(id);try{localStorage.setItem(KEY,JSON.stringify(a))}catch(e){console.error('KupSiApku cart storage:',e);return false}render();return true}
function buy(id){if(add(id))location.href=checkout}
function go(){location.href=checkout}
window.KupSiApkuCart={add,buy,checkout:go,items:read,count:()=>read().length,render};
window.add=add;
const path=location.pathname.toLowerCase();
const rules=[['finance',['financ']],['zdravi',['zdravi','health']],['portfolio',['portfolio','investic']],['cile',['cile','goals']],['vztahy',['vztahy','relationship']],['rozvoj',['rozvoj','development']],['firma',['firma','company']],['podnikani',['podnikani','business']],['prace',['prace','work']]];
const found=rules.find(([,words])=>words.some(w=>path.includes(w)));
const product=found?found[0]:null;
let header=document.querySelector('.siteHeader');
if(!header){header=document.createElement('header');header.className='siteHeader';document.body.prepend(header)}
header.innerHTML=`<div class="siteHeader-inner"><a class="siteHeader-brand" href="${en?'/en/':'/'}"><span aria-hidden="true">🚀</span> KUP SI <b>APKU</b></a><div class="siteHeader-languages"><a href="${en?'/':'/en/'}" aria-label="${en?'Čeština':'English'}"><span class="siteHeader-flag ${en?'siteHeader-flagCZ':'siteHeader-flagGB'}" aria-hidden="true"></span></a></div><a class="siteHeader-cartQuick" data-cart-checkout href="${checkout}" aria-label="${en?'Cart':'Košík'}"><span aria-hidden="true">🛒</span><strong data-cart-count>0</strong></a><button class="siteHeader-toggle" type="button" aria-expanded="false" aria-controls="site-navigation" aria-label="${en?'Open menu':'Otevřít menu'}"><span aria-hidden="true">☰</span></button><nav class="siteHeader-nav" id="site-navigation"><a href="${en?'/en/index.html#apps':'/index.html#apps'}">${en?'Apps':'Aplikace'}</a><a href="${en?'/en/index.html#showcase':'/index.html#gallery'}">${en?'Showcase':'Ukázky'}</a><a href="${en?'/en/index.html#how':'/index.html#jak-to-funguje'}">${en?'How it works':'Jak to funguje'}</a><a href="${en?'/en/index.html#faq':'/index.html#faq'}">FAQ</a><a href="${en?'/en/index.html#about':'/index.html#about'}">${en?'About me':'O mně'}</a><a data-cart-checkout href="${checkout}">${en?'Order':'Objednat'}</a><a class="siteHeader-cart" data-cart-checkout href="${checkout}"><span aria-hidden="true">🛒</span> ${en?'Cart':'Košík'} <strong data-cart-count>0</strong></a></nav></div>`;
const toggle=header.querySelector('.siteHeader-toggle'),nav=header.querySelector('.siteHeader-nav'),mobile=matchMedia('(max-width:1100px)');
function setOpen(open){toggle.setAttribute('aria-expanded',String(open));toggle.firstElementChild.textContent=open?'✕':'☰';nav.hidden=mobile.matches&&!open}
setOpen(false);toggle.addEventListener('click',()=>setOpen(toggle.getAttribute('aria-expanded')!=='true'));nav.addEventListener('click',()=>{if(mobile.matches)setOpen(false)});if(mobile.addEventListener)mobile.addEventListener('change',()=>setOpen(false));
if(product){const hero=document.querySelector('.hero,.productHero');const actions=hero&&hero.querySelector('.actions');if(actions){const keep=[...actions.querySelectorAll('a[href^="#"]')];actions.innerHTML=`<button class="btn primary" type="button" data-cart-add="${product}">🛒 ${en?'Add to cart':'Přidat do košíku'}</button><button class="btn secondary" type="button" data-cart-buy="${product}">⚡ ${en?'Order':'Objednat'}</button>`;keep.forEach(a=>actions.append(a))}}
document.addEventListener('click',e=>{const el=e.target&&e.target.closest?e.target.closest('[data-cart-add],[data-cart-buy],[data-cart-checkout]'):null;if(!el)return;e.preventDefault();const id=norm(el.dataset.cartAdd||el.dataset.cartBuy||el.dataset.product||'');if(el.hasAttribute('data-cart-add')){if(add(id)){const old=el.textContent;el.textContent=en?'✓ Added to cart':'✓ Přidáno do košíku';setTimeout(()=>{if(el.isConnected)el.textContent=old},900)}return}if(el.hasAttribute('data-cart-buy')){buy(id);return}go()},false);
window.addEventListener('storage',render);render();
})();
