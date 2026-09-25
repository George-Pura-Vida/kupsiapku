(()=>{const ref=new URLSearchParams(location.search).get('ref');if(!ref)return;sessionStorage.setItem('ksa_ref',ref);fetch('/api/affiliate.php?action=click',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({code:ref,landingPage:location.pathname})}).catch(()=>{});})();

