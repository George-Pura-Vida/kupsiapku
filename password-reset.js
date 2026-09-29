(()=>{'use strict';
const en=document.documentElement.lang==='en',message=document.getElementById('message');
const reset=document.getElementById('resetForm'),forgot=document.getElementById('forgotForm');
let token='';
if(reset){const match=location.hash.match(/^#reset=([a-f0-9]{64})$/);token=match?match[1]:'';history.replaceState(null,'',location.pathname+location.search);reset.hidden=!token;if(!token)message.textContent=en?'The reset link is invalid. Request a new one.':'Odkaz je neplatný. Požádejte o nový.';}
const form=reset||forgot;
form.addEventListener('submit',async e=>{
  e.preventDefault();const button=form.querySelector('button');button.disabled=true;message.textContent='';
  const data=Object.fromEntries(new FormData(form));
  if(reset){if(data.newPassword!==data.confirmPassword){message.textContent=en?'Passwords do not match.':'Hesla se neshodují.';button.disabled=false;return;}data.token=token;}
  try{const response=await fetch('/api/auth/'+(reset?'reset':'forgot')+'.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.message||result.error||'Operace se nezdařila.');message.textContent=en?(reset?'Password changed. Sign in with your new password.':'If the email belongs to an account, a reset link will arrive. Check spam too.'):result.message;if(reset){token='';form.hidden=true;}else form.reset();}
  catch(error){message.textContent=error.message||'Operace se nezdařila.';}
  finally{button.disabled=false;}
});
})();
