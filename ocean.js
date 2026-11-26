/* Ocean ambience for the home page hero: slow ripples on the water of the port photograph (WebGL)
   and one soft five-second welcome swell, played once per visit after the visitor's first tap, click or key.
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
'uniform sampler2D uTex;uniform vec2 uRes;uniform vec2 uImg;uniform vec2 uPos;uniform float uT;uniform float uAmp;uniform vec2 uA[8];uniform vec2 uB[8];'+
/* water lies between an upper outline (uA) and a lower outline (uB), both given in image coordinates */
'float la(float x){float y=uA[0].y;for(int i=0;i<7;i++){if(x>=uA[i].x){float w=uA[i+1].x-uA[i].x;y=w>0.?mix(uA[i].y,uA[i+1].y,clamp((x-uA[i].x)/w,0.,1.)):uA[i+1].y;}}return y;}'+
'float lb(float x){float y=uB[0].y;for(int i=0;i<7;i++){if(x>=uB[i].x){float w=uB[i+1].x-uB[i].x;y=w>0.?mix(uB[i].y,uB[i+1].y,clamp((x-uB[i].x)/w,0.,1.)):uB[i+1].y;}}return y;}'+
'void main(){vec2 q=gl_FragCoord.xy;q.y=uRes.y-q.y;float s=max(uRes.x/uImg.x,uRes.y/uImg.y);vec2 sz=uImg*s;vec2 off=(uRes-sz)*uPos;vec2 uv=(q-off)/sz;'+
'float e=la(uv.x);float f=lb(uv.x);float m=smoothstep(e+.004,e+.05,uv.y)*(1.-smoothstep(f-.035,f-.004,uv.y));vec3 c;'+
'if(m<=0.){c=texture2D(uTex,uv).rgb;}else{'+
'float d=max(uv.y-e,0.);float g=log(1.+d*40.);float x=uv.x*uImg.x/uImg.y;'+
'float r1=sin(g*22.-uT*.9+x*6.+sin(x*3.+uT*.25)*1.5);float r2=sin(g*37.-uT*1.35-x*11.+1.7);float r3=sin(x*38.*(1.-d*1.5)+uT*.7+g*9.);'+
'float a=m*(.0018+.0165*d)*uAmp;vec2 dv=vec2((r1*.6+r3*.4)*a*.9,(r1*.7+r2*.3)*a*.55);'+
'c=texture2D(uTex,clamp(uv+dv,0.,1.)).rgb;c+=m*r1*r2*.028*min(uAmp,1.6);}'+
'gl_FragColor=vec4(c,1.);}';
/* which photograph this page shows, and where its water is */
var NOB=[[0,2],[1,2]];
var PHOTOS={
 home:{sel:'.mh-bg',img:'/banner.jpg',pos:[.72,.5],amp:1.7,a:[[0,.705],[.44,.705],[.53,.73],[.85,.812],[.86,.835],[1,.835]],b:NOB},
 chattogram:{sel:'.hero',img:'/port-chattogram.jpg',pos:[.5,.6],amp:2.1,first:true,a:[[0,2],[.5,2],[.505,.365],[1,.365]],b:[[0,.36],[.52,.37],[.6,.39],[.8,.45],[.88,.50],[.9,.69],[1,.81]]},
 mongla:{sel:'.hero',img:'/port-mongla.jpg',pos:[.5,.6],amp:2.1,first:true,a:[[0,.445],[.04,.445],[.49,.548],[.51,.648],[.78,.665],[.8,.69],[1,.705]],b:NOB},
 payra:{sel:'.hero',img:'/port-payra.jpg',pos:[.5,.6],amp:2.1,first:true,a:[[0,.635],[1,.635]],b:NOB}
};
var PM=/ship-supply-([a-z]+)/.exec(location.pathname),P=document.querySelector('.mh-bg')?PHOTOS.home:(PM&&PHOTOS[PM[1]])||null;
function pts(a){var o=[],i,l=a[a.length-1];for(i=0;i<8;i++){var p=a[i]||[l[0]+1+i,l[1]];o.push(p[0],p[1])}return new Float32Array(o)}
function host(){return P?document.querySelector(P.sel):null}
/* ---------- water ---------- */
function compile(gl,type,src){var o=gl.createShader(type);gl.shaderSource(o,src);gl.compileShader(o);if(!gl.getShaderParameter(o,gl.COMPILE_STATUS))throw new Error('shader');return o}
function initGL(bg){
 var cv=document.createElement('canvas');cv.className='oc-cv';cv.setAttribute('aria-hidden','true');
 var gl=null;try{gl=cv.getContext('webgl',{antialias:false,alpha:false,powerPreference:'low-power',preserveDrawingBuffer:false})||cv.getContext('experimental-webgl')}catch(e){}
 if(!gl)return false;
 try{var pr=gl.createProgram();gl.attachShader(pr,compile(gl,gl.VERTEX_SHADER,VS));gl.attachShader(pr,compile(gl,gl.FRAGMENT_SHADER,FS));gl.linkProgram(pr);if(!gl.getProgramParameter(pr,gl.LINK_STATUS))throw 0;
  gl.useProgram(pr);var b=gl.createBuffer();gl.bindBuffer(gl.ARRAY_BUFFER,b);gl.bufferData(gl.ARRAY_BUFFER,new Float32Array([-1,-1,1,-1,-1,1,1,1]),gl.STATIC_DRAW);
  var loc=gl.getAttribLocation(pr,'p');gl.enableVertexAttribArray(loc);gl.vertexAttribPointer(loc,2,gl.FLOAT,false,0,0);
  S.u={res:gl.getUniformLocation(pr,'uRes'),img:gl.getUniformLocation(pr,'uImg'),t:gl.getUniformLocation(pr,'uT')};gl.uniform2f(gl.getUniformLocation(pr,'uPos'),P.pos[0],P.pos[1]);gl.uniform1f(gl.getUniformLocation(pr,'uAmp'),P.amp);gl.uniform2fv(gl.getUniformLocation(pr,'uA[0]'),pts(P.a));gl.uniform2fv(gl.getUniformLocation(pr,'uB[0]'),pts(P.b));
 }catch(e){return false}
 S.gl=gl;S.cv=cv;S.prog=pr;S.tex=false;if(P.first)bg.insertBefore(cv,bg.firstChild);else bg.appendChild(cv);
 cv.addEventListener('webglcontextlost',function(e){e.preventDefault();S.tex=false;cv.classList.remove('on');stop()});
 var im=new Image();im.decoding='async';im.onload=function(){try{var tx=gl.createTexture();gl.bindTexture(gl.TEXTURE_2D,tx);gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL,0);
   gl.texImage2D(gl.TEXTURE_2D,0,gl.RGB,gl.RGB,gl.UNSIGNED_BYTE,im);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MIN_FILTER,gl.LINEAR);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MAG_FILTER,gl.LINEAR);
   gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_S,gl.CLAMP_TO_EDGE);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_T,gl.CLAMP_TO_EDGE);
   gl.uniform2f(S.u.img,im.naturalWidth,im.naturalHeight);S.tex=true;size();if(S.play){frame(performance.now(),true);go()}}catch(e){S.gl=null}};
 im.src=P.img;S.img=im;
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
/* ---------- welcome sound: one soft 5-second swell, once per visit, after the visitor's first tap, click or key ---------- */
var INTRO='/ocean-intro.mp3',KEY='mdf_ocean_intro3',VOL=0.7,played=false,ac=null,gain=null,src=null;
try{played=window.sessionStorage.getItem(KEY)==='1'}catch(e){}
function quiet(fast){if(!ac||!gain)return;try{var n=ac.currentTime,g=gain.gain;g.cancelScheduledValues(n);g.setValueAtTime(g.value,n);g.linearRampToValueAtTime(0,n+(fast?0.15:0.4))}catch(e){}
 var a=ac,s=src;ac=null;src=null;gain=null;setTimeout(function(){try{if(s)s.stop()}catch(e){}try{a.close()}catch(e){}},fast?220:480)}
function intro(ev){
 if(played)return off();
 if(ev&&ev.type==='keydown'&&(ev.key==='Escape'||ev.key==='Tab'||ev.key==='Shift'||ev.key==='Control'||ev.key==='Alt'||ev.key==='Meta'))return;
 var C=window.AudioContext||window.webkitAudioContext;if(!C||!window.fetch)return off();
 played=true;off();try{window.sessionStorage.setItem(KEY,'1')}catch(e){}
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
function mount(){var bg=host();if(!bg)return;
 if(!S.noGL&&!bg.querySelector('.oc-cv')){stop();if(S.ro){S.ro.disconnect();S.ro=null}if(S.io){S.io.disconnect();S.io=null}S.gl=null;S.cv=null;S.tex=false;if(!initGL(bg))S.noGL=true}}
document.addEventListener('visibilitychange',function(){if(document.hidden){stop();quiet(true)}else go()});
function rm(){S.play=!mq.matches;if(S.play){if(S.tex)frame(performance.now(),true);go()}else{stop();if(S.cv)S.cv.classList.remove('on')}}
if(mq.addEventListener)mq.addEventListener('change',rm);else if(mq.addListener)mq.addListener(rm);
var app=document.getElementById('app');if(app&&window.MutationObserver)new MutationObserver(function(){var h=host();if(h&&!S.noGL&&!h.querySelector('.oc-cv'))mount()}).observe(app,{childList:true,subtree:true});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount);else mount();
window.__ocean={state:S};
})();
