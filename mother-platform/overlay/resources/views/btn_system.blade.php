{{-- Button system (owner, 14 Nov 2026), every page of the store and the admin:
     orange button  -> dark navy border, turns dark navy under the pointer;
     dark navy button -> orange border, turns orange under the pointer and while pressed.
     Buttons are found by their real colour, so old pages with their own styles follow the rule too. --}}
<style>
[data-bt]{transition:background-color .15s,border-color .15s,color .15s}
[data-bt="o"][data-bt][data-bt][data-bt]{border:1px solid #0B1B33!important}
[data-bt="o"][data-bt][data-bt][data-bt]:hover,[data-bt="o"][data-bt][data-bt][data-bt]:focus-visible{background:#0B1B33!important;background-image:none!important;border-color:#0B1B33!important;color:#fff!important}
[data-bt="n"][data-bt][data-bt][data-bt]{border:1px solid #F95505!important}
[data-bt="n"][data-bt][data-bt][data-bt]:hover,[data-bt="n"][data-bt][data-bt][data-bt]:active,[data-bt="n"][data-bt][data-bt][data-bt]:focus-visible{background:#F95505!important;background-image:none!important;border-color:#F95505!important;color:#fff!important}
[data-bt]:hover svg,[data-bt]:hover i{color:inherit}
[data-bt][disabled]{pointer-events:none}
@media print{[data-bt]{border-color:transparent!important}}
</style>
<script>(function(){
  var SEL='button,input[type=submit],input[type=button],a,[role=button],[class*="btn"],.bt,[onclick]';
  function kind(el){
    var c=getComputedStyle(el).backgroundColor,m=c&&c.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?/);
    if(!m||(m[4]!==undefined&&parseFloat(m[4])<0.6))return '';
    var r=+m[1],g=+m[2],b=+m[3];
    if(r>=185&&g>=45&&g<=135&&b<=70)return 'o';      // DutyFreeBD orange family
    if(r<=35&&g<=45&&b<=75&&(r+g+b)>0)return 'n';     // dark navy / blue-black
    if(r+g+b===0)return 'n';
    return '';
  }
  function mark(root){
    var list=(root.querySelectorAll?root.querySelectorAll(SEL):[]);
    for(var i=0;i<list.length;i++){var el=list[i]; if(el.hasAttribute('data-bt')||el.closest('[data-bt-skip]'))continue; if(el.matches(':hover'))continue;
      var r=el.getBoundingClientRect(); if(r.width===0&&r.height===0){continue;}
      var tg=el.tagName; if(tg!=='BUTTON'&&tg!=='INPUT'&&(r.height>72||r.width>480||el.closest('aside,.sidebar,nav.side'))){el.setAttribute('data-bt','-');continue;} // only button-sized things; menus are not buttons
      var k=kind(el)||'-'; el.setAttribute('data-bt',k);
      // some pages force their own border with !important: the rule is also written on the element itself so it always wins
      if(k==='o'){el.style.setProperty('border','1px solid #0B1B33','important');el.__bt=1}else if(k==='n'){el.style.setProperty('border','1px solid #F95505','important');el.__bt=1}else if(el.__bt){el.style.removeProperty('border');el.__bt=0}}
  }
  function over(ev){var el=ev.target&&ev.target.closest?ev.target.closest('[data-bt="o"],[data-bt="n"]'):null;if(!el||el.__hv)return;el.__hv=1;
    el.__sv=[el.style.getPropertyValue('background'),el.style.getPropertyPriority('background'),el.style.getPropertyValue('color'),el.style.getPropertyPriority('color')];
    var c=el.getAttribute('data-bt')==='o'?'#0B1B33':'#F95505';el.style.setProperty('background',c,'important');el.style.setProperty('border-color',c,'important');el.style.setProperty('color','#fff','important');}
  function out(ev){var el=ev.target&&ev.target.closest?ev.target.closest('[data-bt="o"],[data-bt="n"]'):null;if(!el||!el.__hv)return;if(ev.relatedTarget&&el.contains(ev.relatedTarget))return;el.__hv=0;
    var v=el.__sv||['','','',''];if(v[0])el.style.setProperty('background',v[0],v[1]);else el.style.removeProperty('background');if(v[2])el.style.setProperty('color',v[2],v[3]);else el.style.removeProperty('color');el.style.setProperty('border','1px solid '+(el.getAttribute('data-bt')==='o'?'#0B1B33':'#F95505'),'important');}
  document.addEventListener('mouseover',over,true);document.addEventListener('mouseout',out,true);
  var t=null;function run(){t=null;try{mark(document)}catch(e){}}
  function later(){if(t)return;t=setTimeout(run,250)}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();
  window.addEventListener('load',later);
  try{new MutationObserver(later).observe(document.documentElement,{childList:true,subtree:true})}catch(e){}
  // a button that changes colour by state (tabs, toggles) is looked at again after a click
  document.addEventListener('click',function(){setTimeout(function(){try{var l=document.querySelectorAll('[data-bt]');for(var i=0;i<l.length;i++){if(!l[i].matches(':hover')&&!l[i].__hv)l[i].removeAttribute('data-bt')}mark(document)}catch(e){}},300)},true);
})();</script>
