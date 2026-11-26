<script>
/* Image picker for Marine pages: uploads through the existing /admin/upload-image and keeps the returned /uploads/... path in the hidden field. */
(function(){var t=(document.querySelector('meta[name=csrf-token]')||{}).content||'';
document.addEventListener('change',function(e){var i=e.target;if(!i.matches||!i.matches('input[type=file][data-mimg]'))return;var f=i.files&&i.files[0];if(!f)return;
var box=i.closest('[data-mbox]'),hid=box.querySelector('input[type=hidden]'),pv=box.querySelector('img'),st=box.querySelector('[data-mst]');st.textContent='Uploading...';
var fd=new FormData();fd.append('file',f);fd.append('kind','banner');
fetch('/admin/upload-image',{method:'POST',headers:{'X-CSRF-TOKEN':t,'Accept':'application/json'},body:fd}).then(function(r){return r.json().then(function(j){return {ok:r.ok,j:j}})}).then(function(x){
if(!x.ok||!x.j.url){st.textContent=(x.j&&x.j.message)||'Upload failed';return}hid.value=x.j.url;pv.src=x.j.url;pv.hidden=false;st.textContent='Uploaded. Press Save.'}).catch(function(){st.textContent='Upload failed'})});
document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('[data-mclr]');if(!b)return;var box=b.closest('[data-mbox]');box.querySelector('input[type=hidden]').value='';var pv=box.querySelector('img');pv.hidden=true;pv.removeAttribute('src');box.querySelector('[data-mst]').textContent='Image removed. Press Save.'});
})();
</script>
