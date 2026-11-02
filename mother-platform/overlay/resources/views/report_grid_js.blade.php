<script>
(function(){var f=document.querySelector('form.bar');if(!f)return;var t;function go(){var u=location.pathname+'?'+new URLSearchParams(new FormData(f)).toString();fetch(u,{headers:{'X-Requested-With':'fetch'}}).then(function(r){return r.text()}).then(function(h){var d=new DOMParser().parseFromString(h,'text/html');['.rp-body'].forEach(function(s){var a=document.querySelector(s),b=d.querySelector(s);if(a&&b)a.innerHTML=b.innerHTML});history.replaceState(null,'',u)})}
f.addEventListener('submit',function(e){e.preventDefault();go()});f.querySelectorAll('input[type=date],select').forEach(function(i){i.addEventListener('change',go)});var q=f.querySelector('input[name=q]');if(q)q.addEventListener('input',function(){clearTimeout(t);t=setTimeout(go,350)})})();
</script>
