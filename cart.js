(()=>{
'use strict';
const KEY='kupsiapku_cart';
const allowed=new Set(['zdravi','finance','portfolio','cile','vztahy','rozvoj','firma','podnikani','prace']);
const aliases={investice:'portfolio'};
const isEn=document.documentElement.lang.toLowerCase().startsWith('en');
const checkout=isEn?'/en/index.html#checkout':'/index.html#checkout';
function normalize(v){v=String(v||'').trim().toLowerCase();return aliases[v]||v}
function read(){try{const v=JSON.parse(localStorage.getItem(KEY)||'[]');return Array.isArray(v)?[...new Set(v.map(normalize).filter(x=>allowed.has(x)))]:[]}catch(e){return []}}
function write(items){const v=[...new Set(items.map(normalize).filter(x=>allowed.has(x)))];try{localStorage.setItem(KEY,JSON.stringify(v))}catch(e){}render();return v}
function render(){const n=read().length;document.querySelectorAll('[data-cart-count],#count').forEach(el=>el.textContent=String(n));document.querySelectorAll('[data-cart-checkout]').forEach(el=>el.setAttribute('href',checkout))}
function add(product){product=normalize(product);if(!allowed.has(product))return false;const items=read();if(!items.includes(product))items.push(product);write(items);return true}
function go(){window.location.href=checkout}
function buy(product){if(add(product))go()}
window.KupSiApkuCart={add,buy,items:read,count:()=>read().length,checkout:go,checkoutUrl:checkout,render};
window.add=add;
document.addEventListener('click',e=>{const a=e.target.closest('[data-cart-add]');if(a){e.preventDefault();if(add(a.dataset.cartAdd)){a.textContent=isEn?'✓ Added to cart':'✓ Vloženo do košíku';a.setAttribute('aria-pressed','true')}return}const b=e.target.closest('[data-cart-buy]');if(b){e.preventDefault();buy(b.dataset.cartBuy);return}const c=e.target.closest('[data-cart-checkout]');if(c){e.preventDefault();go()}});
window.addEventListener('storage',e=>{if(e.key===KEY)render()});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',render);else render();
})();
