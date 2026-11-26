(function(){
function get(s,p){p=p.replace(/[{}]/g,'').trim();if(p==='true')return true;if(p==='false')return false;var o=s;p.split('.').forEach(function(k){o=o==null?undefined:o[k]});return o}
var RE=/\{\{([^}]+)\}\}/g;
function kids(n,s){Array.prototype.slice.call(n.childNodes).forEach(function(c){fill(c,s)})}
function fill(n,s){
if(n.nodeType===3){if(n.nodeValue.indexOf('{{')>-1)n.nodeValue=n.nodeValue.replace(RE,function(_,p){var v=get(s,p);return v==null?'':v});return}
if(n.nodeType!==1)return;
if(n.tagName==='TEMPLATE'){var f=document.createDocumentFragment();
if(n.hasAttribute('data-for')){var l=get(s,n.getAttribute('data-for'))||[],as=n.getAttribute('data-as');l.forEach(function(it,i){var c=n.content.cloneNode(true),sc=Object.create(s);sc[as]=it;sc.$index=i;kids(c,sc);f.appendChild(c)})}
else if(get(s,n.getAttribute('data-if'))){var c=n.content.cloneNode(true);kids(c,s);f.appendChild(c)}
n.parentNode.replaceChild(f,n);return}
Array.prototype.slice.call(n.attributes).forEach(function(a){
if(a.name==='onclick'){var fn=get(s,a.value);n.removeAttribute('onclick');if(typeof fn==='function')n.addEventListener('click',fn);return}
if(a.value.indexOf('{{')>-1)n.setAttribute(a.name,a.value.replace(RE,function(_,p){var v=get(s,p);return v==null?'':v}))});
kids(n,s)}
var root=document.getElementById('app'),tpl=document.getElementById('tpl'),inst;
function render(){var c=tpl.content.cloneNode(true);kids(c,inst.renderVals());root.innerHTML='';root.appendChild(c)}
function DCLogic(){this.props={};this.state={}}
DCLogic.prototype.setState=function(p){for(var k in p)this.state[k]=p[k];render()};
var C=new Function('DCLogic',document.getElementById('logic').textContent+';return Component;')(DCLogic);
inst=new C();if(!inst.state)inst.state={};if(!inst.props)inst.props={};render();
})();