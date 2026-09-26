/* KupSiApku cart – global, dependency-free */
(()=>{
'use strict';
const KEY='kupsiapku_cart';
const VALID=new Set(['zdravi','finance','portfolio','cile','vztahy','rozvoj','firma','podnikani','prace']);
const ALIAS={investice:'portfolio'};
const norm=v=>{v=String(v||'').trim().toLowerCase();return ALIAS[v]||v};
const isEN=()=>String(document.documentElement.lang||'cs').toLowerCase().startsWith('en');
const checkout=()=>isEN()?'/en/index.html#checkout':'/index.html#checkout';
function read(){try{const a=JSON.parse(localStorage.getItem(KEY)||'[]');return Array.isArray(a)?[...new Set(a.map(norm).filter(x=>VALID.has(x)))]:[]}catch(e){return[]}}
function write(a){try{localStorage.setItem(KEY,JSON.stringify(a));return true}catch(e){console.error('KupSiApku cart storage:',e);return false}}
function render(){const n=read().length;document.querySelectorAll('[data-cart-count],#count').forEach(el=>el.textContent=String(n));}
function add(id){id=norm(id);if(!VALID.has(id))return false;const a=read();if(!a.includes(id))a.push(id);const ok=write(a);render();try{window.dispatchEvent(new Event('storage'))}catch(e){}return ok}
function buy(id){if(add(id))window.location.assign(checkout())}
function go(){window.location.assign(checkout())}
window.KupSiApkuCart={add,buy,checkout:go,items:read,count:()=>read().length,render};
window.add=add;
function idOf(el){return norm(el.getAttribute('data-cart-add')||el.getAttribute('data-cart-buy')||el.getAttribute('data-product')||'')}
function click(e){const el=e.target&&e.target.closest?e.target.closest('[data-cart-add],[data-cart-buy],[data-cart-checkout]'):null;if(!el)return;e.preventDefault();e.stopPropagation();if(el.hasAttribute('data-cart-add')){if(add(idOf(el))){const old=el.textContent;el.textContent=isEN()?'✓ Added to cart':'✓ Přidáno do košíku';setTimeout(()=>{if(el.isConnected)el.textContent=old},900)}return}if(el.hasAttribute('data-cart-buy')){buy(idOf(el));return}go()}
document.addEventListener('click',click,false);
window.addEventListener('storage',render);
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',render,{once:true});else render();
})();
