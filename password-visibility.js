(()=>{'use strict';
const en=document.documentElement.lang==='en';
document.querySelectorAll('input[type="password"]').forEach(input=>{
  const wrap=document.createElement('span');wrap.className='passwordField';
  input.parentNode.insertBefore(wrap,input);wrap.append(input);
  const button=document.createElement('button');button.type='button';button.className='passwordEye';
  button.setAttribute('aria-label',en?'Show password':'Zobrazit heslo');
  button.setAttribute('aria-pressed','false');button.title=en?'Show password':'Zobrazit heslo';
  button.textContent='👁';wrap.append(button);
  button.addEventListener('click',()=>{
    const shown=input.type==='password';input.type=shown?'text':'password';
    const label=en?(shown?'Hide password':'Show password'):(shown?'Skrýt heslo':'Zobrazit heslo');
    button.setAttribute('aria-label',label);button.setAttribute('aria-pressed',String(shown));button.title=label;
    input.focus({preventScroll:true});
  });
});
})();
