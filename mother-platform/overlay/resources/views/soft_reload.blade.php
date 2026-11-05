@once
<style>#dfsoftbar{position:fixed;left:0;top:0;height:2px;width:0;background:#FF5A00;z-index:2147483000;opacity:0;transition:width .35s ease,opacity .25s ease;pointer-events:none}#dfsoftbar.on{opacity:1}</style>
<script>
/* Soft reload: refresh / F5 / Ctrl+R fetch the page from the server and patch only what changed. No white flash, scroll, filters, typed text and open popups stay exactly where they are. */
(function(){
if(window.dfSoft)return;
var busy=false,bar=null;
function prog(on,w){if(!bar){bar=document.createElement('div');bar.id='dfsoftbar';document.body.appendChild(bar)}bar.style.width=w+'%';if(on)bar.classList.add('on');else setTimeout(function(){bar.classList.remove('on');bar.style.width='0'},250)}
function keepNode(n){
 if(n.nodeType!==1)return false;var t=n.tagName;
 if(t==='SCRIPT'||t==='STYLE'||t==='LINK'||t==='IFRAME'||t==='NOSCRIPT'||t==='CANVAS'||t==='VIDEO')return true;
 if(n.hasAttribute('data-keep'))return true;
 var id=n.id||'';if(/(^|[-_])(ov|modal|pop|dlg)|ov$|overlay|dialog/i.test(id))return true;
 if(n.getAttribute&&(n.getAttribute('role')==='dialog'||n.getAttribute('aria-modal')==='true'))return true;
 if(n.className&&typeof n.className==='string'&&/(^|\s)(qe-ov|dfm|modal)(\s|$)/.test(n.className))return true;
 return false}
function sameAttrs(a,b){
 var i,x,v;for(i=0;i<b.attributes.length;i++){x=b.attributes[i];v=x.value;if(x.name==='class'){var keep=[].slice.call(a.classList).filter(function(c){return /^(mc-|dfx-|dfpx)/.test(c)&&!new RegExp('(^|\\s)'+c+'(\\s|$)').test(v)});if(keep.length)v=(v?v+' ':'')+keep.join(' ')}if(a.getAttribute(x.name)!==v){if(x.name==='value'&&(a.tagName==='INPUT'||a.tagName==='TEXTAREA'||a.tagName==='SELECT'))continue;a.setAttribute(x.name,v)}}
 for(i=a.attributes.length-1;i>=0;i--){x=a.attributes[i];if(!b.hasAttribute(x.name)&&x.name!=='style'&&!/^data-df/.test(x.name)&&!(x.name==='open'&&a.tagName==='DETAILS')){if(x.name==='class'){var kp=[].slice.call(a.classList).filter(function(c){return /^(mc-|dfx-|dfpx)/.test(c)});if(kp.length){a.setAttribute('class',kp.join(' '));continue}}a.removeAttribute(x.name)}}}
function morph(a,b){
 if(a.nodeType===3||a.nodeType===8){if(a.nodeValue!==b.nodeValue)a.nodeValue=b.nodeValue;return}
 if(a.nodeType!==1)return;
 var tg=a.tagName;
 if(tg==='INPUT'||tg==='TEXTAREA'||tg==='SELECT'){
  var dirty=(tg==='INPUT'&&(a.type==='checkbox'||a.type==='radio'))?a.checked!==a.defaultChecked:(a.value!==a.defaultValue);
  if(document.activeElement===a||dirty){return}
  sameAttrs(a,b);if(tg!=='SELECT'&&a.type!=='checkbox'&&a.type!=='radio'&&b.value!==undefined&&a.value!==b.value)a.value=b.value;return}
 sameAttrs(a,b);
 var ac=a.childNodes,bc=b.childNodes,i=0,j=0;
 var A=[].slice.call(ac).filter(function(n){return !(n.nodeType===8)}),B=[].slice.call(bc).filter(function(n){return !(n.nodeType===8)});
 var list=(tg==='TBODY'||tg==='UL'||tg==='OL'||tg==='SELECT'||tg==='TABLE'||tg==='THEAD');
 var n=Math.min(A.length,B.length);
 for(i=0;i<n;i++){var x=A[i],y=B[i];
  if(x.nodeType!==y.nodeType||(x.nodeType===1&&x.tagName!==y.tagName)){if(x.nodeType===1&&keepNode(x))continue;a.replaceChild(y.cloneNode(true),x);continue}
  if(x.nodeType===1&&keepNode(x))continue;
  morph(x,y)}
 for(i=n;i<B.length;i++){if(B[i].nodeType===1&&keepNode(B[i]))continue;a.appendChild(B[i].cloneNode(true))}
 if(list)for(i=A.length-1;i>=B.length;i--)if(A[i].parentNode===a)a.removeChild(A[i])}
function run(opt){
 if(busy)return Promise.resolve(false);busy=true;prog(true,15);
 var url=location.href;
 return fetch(url,{credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'}}).then(function(r){
  prog(true,60);
  if(r.redirected&&new URL(r.url).pathname!==location.pathname){location.assign(r.url);throw 0}
  if(!r.ok)throw 1;return r.text()}).then(function(h){
  var d=new DOMParser().parseFromString(h,'text/html'),nm=d.querySelector('main'),om=document.querySelector('main');
  if(!nm||!om){location.reload();return false}
  var sy=window.scrollY,sx=window.scrollX;
  morph(om,nm);
  var t=d.querySelector('title');if(t&&t.textContent)document.title=t.textContent;
  window.scrollTo(sx,sy);prog(true,100);prog(false,100);
  try{document.dispatchEvent(new CustomEvent('dfsoft'))}catch(e){}
  return true}).catch(function(e){prog(false,0);if(e!==0&&!(opt&&opt.quiet))location.reload();return false}).then(function(v){busy=false;return v})}
window.dfSoft=function(opt){run(opt);return false};
document.addEventListener('keydown',function(e){
 var k=(e.key||'').toLowerCase();
 if(k==='f5'||((e.ctrlKey||e.metaKey)&&k==='r')){var t=e.target;if(t&&/^(input|textarea|select)$/i.test(t.tagName)&&t.value!==t.defaultValue&&false)return;e.preventDefault();run()}},true);
})();
</script>
@endonce
