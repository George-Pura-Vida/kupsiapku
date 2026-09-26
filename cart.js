(()=>{
'use strict';
const KEY='kupsiapku_cart';
const allowed=new Set(['zdravi','finance','portfolio','investice','cile','vztahy','rozvoj','firma','podnikani','prace']);
const aliases={investice:'portfolio'};
const isEn=document.documentElement.lang.toLowerCase().startsWith('en');
const checkout=isEn?'/en/index.html#checkout':'/index.html#checkout';

function normalize(product){
  const value=String(product||'').trim();
  return aliases[value]||value;
}
function read(){
  try{
    const raw=JSON.parse(localStorage.getItem(KEY)||'[]');
    if(!Array.isArray(raw)) return [];
    return [...new Set(raw.map(normalize).filter(x=>allowed.has(x)))];
  }catch{return []}
}
function write(items){
  const clean=[...new Set(items.map(normalize).filter(x=>allowed.has(x)))];
  localStorage.setItem(KEY,JSON.stringify(clean));
  render();
  return clean;
}
function render(){
  const items=read();
  const n=items.length;
  document.querySelectorAll('[data-cart-count], #count').forEach(el=>el.textContent=String(n));
  document.querySelectorAll('[data-cart-checkout]').forEach(el=>el.href=checkout);
}
function add(product){
  product=normalize(product);
  if(!allowed.has(product)) return false;
  const items=read();
  if(!items.includes(product)) items.push(product);
  const saved=write(items);
  document.dispatchEvent(new CustomEvent('kupsiapku:cartchange',{detail:{items:saved,count:saved.length}}));
  return true;
}
function go(){location.href=checkout}

window.KupSiApkuCart={add,items:read,count:()=>read().length,checkout:go,checkoutUrl:checkout,render};
/* Backwards compatibility for legacy homepage onclick="add('...')" buttons. */
window.add=function(product){return add(product)};

document.addEventListener('click',e=>{
  const addButton=e.target.closest('[data-cart-add]');
  if(addButton){
    e.preventDefault();
    if(add(addButton.dataset.cartAdd)){
      addButton.textContent=isEn?'✓ Added to cart':'✓ Přidáno do košíku';
      addButton.setAttribute('aria-pressed','true');
    }
    return;
  }
  const buyButton=e.target.closest('[data-cart-buy]');
  if(buyButton){
    e.preventDefault();
    if(add(buyButton.dataset.cartBuy)) go();
    return;
  }
  const checkoutButton=e.target.closest('[data-cart-checkout]');
  if(checkoutButton){e.preventDefault();go()}
});
window.addEventListener('storage',e=>{if(e.key===KEY)render()});
window.addEventListener('pageshow',render);
if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',render); else render();
})();
