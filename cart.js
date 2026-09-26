(()=>{
'use strict';
const KEY='kupsiapku_cart';
const allowed=new Set(['zdravi','finance','portfolio','investice','cile','vztahy','rozvoj','firma','podnikani','prace']);
const isEn=document.documentElement.lang.toLowerCase().startsWith('en');
const checkout=isEn?'/en/index.html#checkout':'/index.html#checkout';
function read(){try{const v=JSON.parse(localStorage.getItem(KEY)||'[]');return Array.isArray(v)?[...new Set(v.filter(x=>allowed.has(x)))]:[]}catch{return[]}}
function write(v){localStorage.setItem(KEY,JSON.stringify([...new Set(v)]));render()}
function render(){const n=read().length;document.querySelectorAll('[data-cart-count]').forEach(el=>el.textContent=String(n));document.querySelectorAll('[data-cart-checkout]').forEach(el=>el.href=checkout)}
function add(product){if(!allowed.has(product))return false;const v=read();if(!v.includes(product))v.push(product);write(v);document.dispatchEvent(new CustomEvent('kupsiapku:cartchange',{detail:{items:v}}));return true}
function go(){location.href=checkout}
window.KupSiApkuCart={add,items:read,count:()=>read().length,checkout:go,checkoutUrl:checkout};
document.addEventListener('click',e=>{const addButton=e.target.closest('[data-cart-add]');if(addButton){e.preventDefault();const product=addButton.dataset.cartAdd;if(add(product)){addButton.textContent=isEn?'✓ Added to cart':'✓ Přidáno do košíku';addButton.setAttribute('aria-pressed','true')}return}const checkoutButton=e.target.closest('[data-cart-checkout]');if(checkoutButton){e.preventDefault();go()}});
window.addEventListener('storage',e=>{if(e.key===KEY)render()});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',render);else render();
})();
