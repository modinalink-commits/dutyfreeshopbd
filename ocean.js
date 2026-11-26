/* Ocean ambience for the home page hero: slow ripples on the water of the port photograph (WebGL)
   and one soft five-second welcome swell, played once per browser after the visitor's first tap, click or key.
   No controls on the page: Escape or leaving the tab stops the sound at once.
   Without WebGL, or with reduced motion, the photograph stays as it is. */
(function(){
'use strict';
var WRAP=125.66370614;
var mq=window.matchMedia?window.matchMedia('(prefers-reduced-motion: reduce)'):{matches:false};
var S={play:!mq.matches,chosen:false,sound:false,inView:true,gl:null,cv:null,prog:null,tex:false,img:null,t:0,last:0,raf:0,u:{},ro:null,io:null,
       ac:null,gain:null,src:null,buf:null,loading:false};
var VS='attribute vec2 p;void main(){gl_Position=vec4(p,0.,1.);}';
var FS='#ifdef GL_FRAGMENT_PRECISION_HIGH\nprecision highp float;\n#else\nprecision mediump float;\n#endif\n'+
'uniform sampler2D uTex;uniform vec2 uRes;uniform vec2 uImg;uniform float uT;'+
/* top edge of the water in the photograph (horizon, pier, hull, quay), in image coordinates */
'float edge(float x){if(x<.44)return .705;if(x<.53)return .705+(x-.44)/.09*.025;if(x<.85)return .73+(x-.53)/.32*.082;if(x<.86)return .812+(x-.85)/.01*.023;return .835;}'+
'void main(){vec2 q=gl_FragCoord.xy;q.y=uRes.y-q.y;float s=max(uRes.x/uImg.x,uRes.y/uImg.y);vec2 sz=uImg*s;vec2 off=(uRes-sz)*vec2(.72,.5);vec2 uv=(q-off)/sz;'+
'float e=edge(uv.x);float m=smoothstep(e+.004,e+.05,uv.y);vec3 c;'+
'if(m<=0.){c=texture2D(uTex,uv).rgb;}else{'+
'float d=max(uv.y-e,0.);float g=log(1.+d*40.);float x=uv.x*uImg.x/uImg.y;'+
'float r1=sin(g*22.-uT*.9+x*6.+sin(x*3.+uT*.25)*1.5);float r2=sin(g*37.-uT*1.35-x*11.+1.7);float r3=sin(x*38.*(1.-d*1.5)+uT*.7+g*9.);'+
'float a=m*(.0011+.0095*d);vec2 dv=vec2((r1*.6+r3*.4)*a*.9,(r1*.7+r2*.3)*a*.55);'+
'c=texture2D(uTex,clamp(uv+dv,0.,1.)).rgb;c+=m*r1*r2*.016;}'+
'gl_FragColor=vec4(c,1.);}';

function hero(){return document.querySelector('.mh')}
/* ---------- water ---------- */
function compile(gl,type,src){var o=gl.createShader(type);gl.shaderSource(o,src);gl.compileShader(o);if(!gl.getShaderParameter(o,gl.COMPILE_STATUS))throw new Error('shader');return o}
function initGL(bg){
 var cv=document.createElement('canvas');cv.className='oc-cv';cv.setAttribute('aria-hidden','true');
 var gl=null;try{gl=cv.getContext('webgl',{antialias:false,alpha:false,powerPreference:'low-power',preserveDrawingBuffer:false})||cv.getContext('experimental-webgl')}catch(e){}
 if(!gl)return false;
 try{var pr=gl.createProgram();gl.attachShader(pr,compile(gl,gl.VERTEX_SHADER,VS));gl.attachShader(pr,compile(gl,gl.FRAGMENT_SHADER,FS));gl.linkProgram(pr);if(!gl.getProgramParameter(pr,gl.LINK_STATUS))throw 0;
  gl.useProgram(pr);var b=gl.createBuffer();gl.bindBuffer(gl.ARRAY_BUFFER,b);gl.bufferData(gl.ARRAY_BUFFER,new Float32Array([-1,-1,1,-1,-1,1,1,1]),gl.STATIC_DRAW);
  var loc=gl.getAttribLocation(pr,'p');gl.enableVertexAttribArray(loc);gl.vertexAttribPointer(loc,2,gl.FLOAT,false,0,0);
  S.u={res:gl.getUniformLocation(pr,'uRes'),img:gl.getUniformLocation(pr,'uImg'),t:gl.getUniformLocation(pr,'uT')};
 }catch(e){return false}
 S.gl=gl;S.cv=cv;S.prog=pr;S.tex=false;bg.appendChild(cv);
 cv.addEventListener('webglcontextlost',function(e){e.preventDefault();S.tex=false;cv.classList.remove('on');stop()});
 var im=new Image();im.decoding='async';im.onload=function(){try{var tx=gl.createTexture();gl.bindTexture(gl.TEXTURE_2D,tx);gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL,0);
   gl.texImage2D(gl.TEXTURE_2D,0,gl.RGB,gl.RGB,gl.UNSIGNED_BYTE,im);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MIN_FILTER,gl.LINEAR);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MAG_FILTER,gl.LINEAR);
   gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_S,gl.CLAMP_TO_EDGE);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_T,gl.CLAMP_TO_EDGE);
   gl.uniform2f(S.u.img,im.naturalWidth,im.naturalHeight);S.tex=true;size();if(S.play){frame(performance.now(),true);go()}}catch(e){S.gl=null}};
 im.src='/banner.jpg';S.img=im;
 if(window.ResizeObserver){S.ro=new ResizeObserver(size);S.ro.observe(bg)}else window.addEventListener('resize',size);
 if(window.IntersectionObserver){S.io=new IntersectionObserver(function(es){S.inView=es[0].isIntersecting;S.inView?go():stop()},{rootMargin:'80px'});S.io.observe(bg)}
 return true;
}
function size(){var cv=S.cv,gl=S.gl;if(!cv||!gl)return;var w=cv.clientWidth,h=cv.clientHeight;if(!w||!h)return;
 var r=Math.min(window.devicePixelRatio||1,1.5),W=Math.round(w*r),H=Math.round(h*r);if(W>1600){H=Math.round(H*1600/W);W=1600}
 if(cv.width!==W||cv.height!==H){cv.width=W;cv.height=H;gl.viewport(0,0,W,H);gl.uniform2f(S.u.res,W,H);if(S.tex&&!S.raf&&cv.classList.contains('on'))draw()}}
function draw(){var gl=S.gl;gl.uniform1f(S.u.t,S.t);gl.drawArrays(gl.TRIANGLE_STRIP,0,4)}
function frame(now,force){S.raf=0;if(!S.tex||!S.gl)return;var dt=now-S.last;
 if(force||dt>=32){S.t=(S.t+(force?0:Math.min(dt,100)/1000))%WRAP;S.last=now;draw();if(!S.cv.classList.contains('on'))S.cv.classList.add('on')}
 if(!force&&active())S.raf=requestAnimationFrame(frame)}
function active(){return S.play&&S.tex&&S.inView&&!document.hidden}
function go(){if(active()&&!S.raf){S.last=performance.now();S.raf=requestAnimationFrame(frame)}}
function stop(){if(S.raf){cancelAnimationFrame(S.raf);S.raf=0}}
/* ---------- welcome sound: one soft 5-second swell, once per browser, after the visitor's first tap, click or key ---------- */
var INTRO='/ocean-intro.mp3',KEY='mdf_ocean_intro',VOL=0.16,played=false,ac=null,gain=null,src=null;
try{played=window.localStorage.getItem(KEY)==='1'}catch(e){}
function quiet(fast){if(!ac||!gain)return;try{var n=ac.currentTime,g=gain.gain;g.cancelScheduledValues(n);g.setValueAtTime(g.value,n);g.linearRampToValueAtTime(0,n+(fast?0.15:0.4))}catch(e){}
 var a=ac,s=src;ac=null;src=null;gain=null;setTimeout(function(){try{if(s)s.stop()}catch(e){}try{a.close()}catch(e){}},fast?220:480)}
function intro(ev){
 if(played)return off();
 if(ev&&ev.type==='keydown'&&(ev.key==='Escape'||ev.key==='Tab'||ev.key==='Shift'||ev.key==='Control'||ev.key==='Alt'||ev.key==='Meta'))return;
 var C=window.AudioContext||window.webkitAudioContext;if(!C||!window.fetch)return off();
 played=true;off();try{window.localStorage.setItem(KEY,'1')}catch(e){}
 try{ac=new C();if(ac.state==='suspended')ac.resume()}catch(e){ac=null;return}
 var a=ac;
 fetch(INTRO).then(function(r){if(!r.ok)throw 0;return r.arrayBuffer()}).then(function(ab){return new Promise(function(ok,no){var p=a.decodeAudioData(ab,ok,no);if(p&&p.catch)p.catch(no)})})
 .then(function(buf){if(ac!==a||document.hidden)return quiet(true);
  gain=a.createGain();gain.gain.value=0;gain.connect(a.destination);src=a.createBufferSource();src.buffer=buf;src.connect(gain);
  var n=a.currentTime,d=Math.min(5,buf.duration);gain.gain.setValueAtTime(0,n);gain.gain.linearRampToValueAtTime(VOL,n+1);gain.gain.setValueAtTime(VOL,n+d-1.4);gain.gain.linearRampToValueAtTime(0,n+d);
  src.start(n);src.stop(n+d+0.05);S.introAt=Date.now();src.onended=function(){if(ac===a){ac=null;src=null;gain=null;try{a.close()}catch(e){}}S.introDone=true}})
 .catch(function(){if(ac===a){ac=null;try{a.close()}catch(e){}}})}
var EV=['pointerup','keydown','touchend'];
function off(){EV.forEach(function(t){document.removeEventListener(t,intro,true)})}
if(!played)EV.forEach(function(t){document.addEventListener(t,intro,{capture:true,passive:true})});
document.addEventListener('keydown',function(e){if(e.key==='Escape')quiet(true)});
/* ---------- mount ---------- */
function mount(){var h=hero();if(!h)return;var bg=h.querySelector('.mh-bg');if(!bg)return;
 if(!S.noGL&&!bg.querySelector('.oc-cv')){stop();if(S.ro){S.ro.disconnect();S.ro=null}if(S.io){S.io.disconnect();S.io=null}S.gl=null;S.cv=null;S.tex=false;if(!initGL(bg))S.noGL=true}}
document.addEventListener('visibilitychange',function(){if(document.hidden){stop();quiet(true)}else go()});
function rm(){S.play=!mq.matches;if(S.play){if(S.tex)frame(performance.now(),true);go()}else{stop();if(S.cv)S.cv.classList.remove('on')}}
if(mq.addEventListener)mq.addEventListener('change',rm);else if(mq.addListener)mq.addListener(rm);
var app=document.getElementById('app');if(app&&window.MutationObserver)new MutationObserver(function(){var h=hero();if(h&&!S.noGL&&!h.querySelector('.oc-cv'))mount()}).observe(app,{childList:true,subtree:true});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount);else mount();
window.__ocean={state:S};
})();
