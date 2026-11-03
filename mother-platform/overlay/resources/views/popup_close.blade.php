@once
<style id="dfpx-css">
.dfpx{font-size:0!important;line-height:0!important;color:transparent!important;text-shadow:none!important;width:32px!important;height:32px!important;min-width:32px!important;min-height:32px!important;padding:0!important;border:0!important;border-radius:9px!important;background:transparent url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23DC2626' stroke-width='3.2' stroke-linecap='round'%3E%3Cpath d='M5.5 5.5l13 13M18.5 5.5l-13 13'/%3E%3C/svg%3E") center/17px 17px no-repeat!important;cursor:pointer;display:inline-flex!important;align-items:center;justify-content:center;box-shadow:none!important;text-decoration:none!important;flex:0 0 auto;opacity:1!important;transition:background-color .12s}
.dfpx:hover,.dfpx:focus-visible{background-color:#FEE2E2!important;outline:0}
.dfpx:active{background-color:#FECACA!important}
</style>
<script>
(function(){
var SET={'×':1,'✕':1,'✖':1,'✗':1,'╳':1,'⨯':1};
var POP='dialog,[role=dialog],[aria-modal],.modal,.modal-box,[class*=modal],[class*=popup],[class*=overlay],[class*=-ov],[id$=ov],[id*=modal],[id*=popup]';
var KNOWN='#pX,.qe-x,#cmX,#soX,#soRX,.dfm-x,.dfi-x,#dfs-x,#aix,#dfmcX,#dfcclose,#dfcpx,#mcx,#ddbx .dh button,#mpd button[onclick*=close],dialog>form>button[value=cancel]';
function isFixed(el){var n=el,i=0;while(n&&n.nodeType===1&&i<8){try{if(getComputedStyle(n).position==='fixed')return true}catch(e){}n=n.parentElement;i++}return false}
function skip(el){var oc=(el.getAttribute('onclick')||'');return el.hasAttribute('data-rm')||/textContent|\.remove\(\)/.test(oc)||el.closest('tr,table,td,th')&&!el.closest('dialog,[role=dialog],[aria-modal]')}
function mark(el){if(el.dataset.dfpx)return;if(skip(el))return;el.dataset.dfpx='1';el.classList.add('dfpx');if(!el.getAttribute('aria-label')&&!el.title)el.setAttribute('aria-label','Close')}
function scan(root){
 if(!root||root.nodeType!==1)return;
 try{if(root.matches&&root.matches(KNOWN))mark(root);root.querySelectorAll(KNOWN).forEach(mark)}catch(e){}
 var list=root.querySelectorAll?root.querySelectorAll('button,a,span,div,i,b'):[];
 for(var i=0;i<list.length;i++){var el=list[i];if(el.dataset.dfpx||el.children.length)continue;var t=(el.textContent||'').trim();if(!SET[t])continue;
  if(el.closest(POP)||isFixed(el))mark(el)}
}
function go(){scan(document.body);try{new MutationObserver(function(ms){ms.forEach(function(m){m.addedNodes.forEach(function(n){scan(n)});if(m.type==='characterData'&&m.target.parentElement)scan(m.target.parentElement.parentElement||m.target.parentElement)})}).observe(document.body,{childList:true,subtree:true,characterData:true})}catch(e){}}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',go);else go();
})();
</script>
@endonce
