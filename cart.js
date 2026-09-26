/* KupSiApku global cart - single source of truth for all pages */
(()=>{
'use strict';
const KEY='kupsiapku_cart';
const VALID=new Set(['zdravi','finance','portfolio','cile','vztahy','rozvoj','firma','podnikani','prace']);
const ALIAS={investice:'portfolio'};
const en=(document.documentElement.lang||'cs').toLowerCase().startsWith('en');
const CHECKOUT=en?'/en/index.html#checkout':'/index.html#checkout';
const norm=v=>ALIAS[(v=String(v||'').trim().toLowerCase())]||v;
function read(){try{const v=JSON.parse(localStorage.getItem(KEY)||'[]');return Array.isArray(v)?[...new Set(v.map(norm).filter(x=>VALID.has(x)))]:[]}catch(e){return[]}}
function write(items){try{localStorage.setItem(KEY,JSON.stringify(items));window.dispatchEvent(new CustomEvent('kupsiapku:cartchange',{detail:{items:[...items]}}));return true}catch(e){console.error('Cart storage error',e);return false}}
function render(){const n=read().length;document.querySelectorAll('[data-cart-count],#count').forEach(el=>el.textContent=String(n));document.querySelectorAll('[data-cart-checkout]').forEach(el=>el.setAttribute('href',CHECKOUT));}
function add(id){id=norm(id);if(!VALID.has(id)){console.warn('Unknown product',id);return false}const items=read();if(!items.includes(id))items.push(id);if(!write(items))return false;render();return true}
function buy(id){if(add(id)){location.href=CHECKOUT}}
function checkout(){location.href=CHECKOUT}
window.KupSiApkuCart={add,buy,items:read,count:()=>read().length,checkout,render};
window.add=add;
function productFrom(el){return norm(el?.dataset?.cartAdd||el?.dataset?.cartBuy||el?.getAttribute?.('data-product')||'')}
function currentProduct(){const p=location.pathname.toLowerCase();if(p.includes('zdravi')||p.includes('health'))return 'zdravi';if(p.includes('financ'))return 'finance';if(p.includes('portfolio')||p.includes('investic'))return 'portfolio';if(p.includes('cile')||p.includes('goals'))return 'cile';if(p.includes('vztahy')||p.includes('relationship'))return 'vztahy';if(p.includes('rozvoj')||p.includes('development'))return 'rozvoj';if(p.includes('firma')||p.includes('company'))return 'firma';if(p.includes('podnikani')||p.includes('business'))return 'podnikani';if(p.includes('prace')||p.includes('work'))return 'prace';return ''}
document.addEventListener('click',e=>{
 const el=e.target.closest('[data-cart-add],[data-cart-buy],[data-cart-checkout]');
 if(!el)return;
 e.preventDefault();e.stopImmediatePropagation();
 if(el.hasAttribute('data-cart-add')){if(add(productFrom(el))){el.dataset.cartDone='1';const old=el.textContent;el.textContent=en?'✓ Added to cart':'✓ Přidáno do košíku';setTimeout(()=>{if(el.isConnected)el.textContent=old},1200)}return}
 if(el.hasAttribute('data-cart-buy')){buy(productFrom(el));return}
 checkout();
},true);
function upgradeLegacyButtons(){
 document.querySelectorAll('button[onclick],a[onclick]').forEach(el=>{
   const s=el.getAttribute('onclick')||'';
   const m=s.match(/add\s*\(\s*['\"]([^'\"]+)['\"]\s*\)/i);
   if(m){el.removeAttribute('onclick');el.dataset.cartAdd=norm(m[1]);}
 });
 const product=currentProduct();
 if(product){document.querySelectorAll('.productHero [data-cart-checkout],.purchaseRow [data-cart-checkout],.finalPurchase [data-cart-checkout]').forEach(el=>{el.removeAttribute('data-cart-checkout');el.dataset.cartBuy=product;});}
 document.querySelectorAll('a[href="#checkout"],a[href="/index.html#checkout"],a[href="/en/index.html#checkout"]').forEach(el=>{if(!el.dataset.cartBuy&&!el.dataset.cartAdd)el.dataset.cartCheckout='1'});
 render();
}
window.addEventListener('storage',render);
window.addEventListener('kupsiapku:cartchange',render);
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',upgradeLegacyButtons,{once:true});else upgradeLegacyButtons();
})();
