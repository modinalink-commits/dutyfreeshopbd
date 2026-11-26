/* Ocean ambience for the home page hero: slow ripples on the water of the port photograph (WebGL)
   and an optional ocean sound. Sound starts off and its file is fetched only when switched on.
   Without WebGL, or with reduced motion, the photograph stays as it is. */
(function(){
'use strict';
var AUDIO='/ocean.mp3',LOOP_START=2,LOOP_END=26,VOLUME=0.2,WRAP=125.66370614;
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
function label(id,text,aria){var b=document.getElementById(id);if(!b)return;var sp=b.querySelector('span');if(sp.textContent!==text)sp.textContent=text;if(b.getAttribute('aria-label')!==aria)b.setAttribute('aria-label',aria)}
function paint(){
 label('oc-wav',S.play?'Pause waves':'Play waves',S.play?'Pause the water movement':'Play the water movement');
 var b=document.getElementById('oc-wav');if(b)b.hidden=!S.gl;
 var t=S.loading?'Loading sound':(S.sound?'Sound on':'Sound off');
 label('oc-snd',t,S.sound?'Ocean sound is on. Turn sound off':'Ocean sound is off. Turn sound on');
 var s=document.getElementById('oc-snd');if(s){s.classList.toggle('on',S.sound);var bz=S.loading?'true':'false';if(s.getAttribute('aria-busy')!==bz)s.setAttribute('aria-busy',bz);s.hidden=!(window.AudioContext||window.webkitAudioContext)}
}
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
   gl.uniform2f(S.u.img,im.naturalWidth,im.naturalHeight);S.tex=true;size();if(S.play){frame(performance.now(),true);go()}}catch(e){S.gl=null;paint()}};
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
/* ---------- sound ---------- */
function ctx(){if(!S.ac){var C=window.AudioContext||window.webkitAudioContext;S.ac=new C();S.gain=S.ac.createGain();S.gain.gain.value=0;S.gain.connect(S.ac.destination)}return S.ac}
function startSound(){var ac=S.ac;if(!S.buf||!S.sound)return;if(S.src){try{S.src.stop()}catch(e){}}
 var s=ac.createBufferSource();s.buffer=S.buf;s.loop=true;s.loopStart=LOOP_START;s.loopEnd=Math.min(LOOP_END,S.buf.duration-0.05);s.connect(S.gain);s.start(0,LOOP_START);S.src=s;
 var g=S.gain.gain,n=ac.currentTime;g.cancelScheduledValues(n);g.setValueAtTime(0,n);g.linearRampToValueAtTime(VOLUME,n+1.6)}
function soundOn(){var ac;try{ac=ctx()}catch(e){return}S.sound=true;if(ac.state==='suspended')ac.resume();
 if(S.buf){startSound();paint();return}
 if(S.loading)return;S.loading=true;paint();
 var fail=function(){S.loading=false;S.sound=false;paint();label('oc-snd','Sound unavailable','Ocean sound could not be loaded. Try again')};
 fetch(AUDIO).then(function(r){if(!r.ok)throw 0;return r.arrayBuffer()}).then(function(ab){return new Promise(function(ok,no){var p=ac.decodeAudioData(ab,ok,no);if(p&&p.catch)p.catch(no)})})
 .then(function(b){S.buf=b;S.loading=false;if(S.sound&&!document.hidden)startSound();paint()}).catch(fail)}
function soundOff(){S.sound=false;paint();var ac=S.ac;if(!ac||!S.gain)return;var g=S.gain.gain,n=ac.currentTime;g.cancelScheduledValues(n);g.setValueAtTime(g.value,n);g.linearRampToValueAtTime(0,n+0.4);
 var s=S.src;S.src=null;setTimeout(function(){try{if(s)s.stop()}catch(e){}if(!S.sound&&ac.state==='running')ac.suspend()},450)}
/* ---------- controls ---------- */
var ICON_S='<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9v6h4l5 4V5L8 9H4z"/><path class="oc-w" d="M16.5 8.5a5 5 0 010 7M19 6a8.5 8.5 0 010 12"/><path class="oc-x" d="M17 9.5l5 5M22 9.5l-5 5"/></svg>';
var ICON_W='<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 9c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2M2 16c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2"/></svg>';
function mount(){var h=hero();if(!h)return;var bg=h.querySelector('.mh-bg');if(!bg)return;if(!h.classList.contains('has-oc'))h.classList.add('has-oc');
 if(!h.querySelector('.oc')){var d=document.createElement('div');d.className='oc';d.setAttribute('role','group');d.setAttribute('aria-label','Ocean ambience');
  d.innerHTML='<button type="button" class="oc-b" id="oc-snd">'+ICON_S+'<span>Sound off</span></button><button type="button" class="oc-b" id="oc-wav">'+ICON_W+'<span>Pause waves</span></button>';h.appendChild(d)}
 if(!S.noGL&&!bg.querySelector('.oc-cv')){stop();if(S.ro){S.ro.disconnect();S.ro=null}if(S.io){S.io.disconnect();S.io=null}S.gl=null;S.cv=null;S.tex=false;if(!initGL(bg))S.noGL=true}
 paint()}
document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('.oc-b');if(!b)return;
 if(b.id==='oc-wav'){S.play=!S.play;S.chosen=true;if(S.play){if(S.tex){frame(performance.now(),true)}go()}else stop();paint()}
 else if(b.id==='oc-snd'){S.sound?soundOff():soundOn()}});
document.addEventListener('visibilitychange',function(){if(document.hidden){stop();if(S.ac&&S.ac.state==='running')S.ac.suspend()}else{go();if(S.sound&&S.ac){S.ac.resume();if(S.buf&&!S.src)startSound()}}});
function rm(){if(S.chosen)return;S.play=!mq.matches;if(S.play){if(S.tex)frame(performance.now(),true);go()}else{stop();if(S.cv)S.cv.classList.remove('on')}paint()}
if(mq.addEventListener)mq.addEventListener('change',rm);else if(mq.addListener)mq.addListener(rm);
var app=document.getElementById('app');if(app&&window.MutationObserver)new MutationObserver(function(){var h=hero();if(h&&(!h.querySelector('.oc')||(!S.noGL&&!h.querySelector('.oc-cv'))))mount()}).observe(app,{childList:true,subtree:true});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount);else mount();
window.__ocean={state:S};
})();
