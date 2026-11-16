{{-- Download a call recording (PBX dialer or WhatsApp). The server checks the permission and logs every download; the browser then makes an MP3 COPY for phones and computers. The stored original is never changed. RecDL.get(url, callId, button) --}}
@once
<script>
window.RecDL=(function(){
  var lameP=null,MAX=6*1024*1024; // larger files are handed over in their original format (converting a very long call needs too much memory)
  function lame(){if(window.lamejs)return Promise.resolve();if(!lameP)lameP=new Promise(function(res,rej){var s=document.createElement('script');s.src='/vendor/lame.min.js';s.onload=function(){res()};s.onerror=function(){lameP=null;rej(new Error('MP3 encoder could not be loaded'))};document.head.appendChild(s)});return lameP}
  function toMp3(blob){var SR=22050;return lame().then(function(){return blob.arrayBuffer()}).then(function(ab){var AC=window.AudioContext||window.webkitAudioContext,ac=new AC();
      return ac.decodeAudioData(ab).then(function(buf){try{ac.close()}catch(e){}var off=new (window.OfflineAudioContext||window.webkitOfflineAudioContext)(1,Math.max(1,Math.ceil(buf.duration*SR)),SR),src=off.createBufferSource();src.buffer=buf;src.connect(off.destination);src.start();return off.startRendering()})})
    .then(function(r){var x=r.getChannelData(0),enc=new lamejs.Mp3Encoder(1,SR,48),out=[],N=1152,i=0;
      return new Promise(function(res){(function step(){var end=Math.min(x.length,i+N*400);for(;i<end;i+=N){var n=Math.min(N,x.length-i),pcm=new Int16Array(n);for(var j=0;j<n;j++){var v=Math.max(-1,Math.min(1,x[i+j]));pcm[j]=v<0?v*32768:v*32767}var m=enc.encodeBuffer(pcm);if(m.length)out.push(new Uint8Array(m))}
        if(i<x.length)setTimeout(step,0);else{var e=enc.flush();if(e.length)out.push(new Uint8Array(e));res({blob:new Blob(out,{type:'audio/mpeg'}),seconds:r.duration})}})()})})}
  function save(blob,name){var a=document.createElement('a'),u=URL.createObjectURL(blob);a.href=u;a.download=name;document.body.appendChild(a);a.click();setTimeout(function(){a.remove();URL.revokeObjectURL(u)},4000)}
  function note(btn,t,ok){var s=btn.nextElementSibling;if(!s||!s.classList||!s.classList.contains('rdl-n')){s=document.createElement('small');s.className='rdl-n';s.style.cssText='display:block;font-size:11.5px;margin-top:3px';btn.parentNode.insertBefore(s,btn.nextSibling)}s.style.color=ok?'#067647':'#B42318';s.textContent=t||''}
  /** Fetch one call's recording. Returns {blob, name, callId}; refuses when the server says the audio belongs to another call. */
  function fetchRec(url,callId){return fetch(url,{credentials:'same-origin'}).then(function(r){if(!r.ok)throw new Error(r.status===403?'You are not allowed to download this recording.':r.status===404?'The recording file was not found.':r.status===409?'This recording belongs to another call. Not downloaded.':'Download failed ('+r.status+').');
      var cid=r.headers.get('X-Call-Id'),name=r.headers.get('X-File-Name')||('call-'+callId),type=(r.headers.get('Content-Type')||'').indexOf('ogg')>-1?'ogg':'webm';
      if(callId&&cid&&String(cid)!==String(callId))throw new Error('This recording belongs to another call. Not downloaded.');
      return r.blob().then(function(b){return{blob:b,name:name,ext:type,callId:cid}})})}
  function get(url,callId,btn){if(btn.disabled)return;var t0=btn.textContent;btn.disabled=true;btn.textContent='Preparing…';note(btn,'');
    fetchRec(url,callId).then(function(f){
      if(f.blob.size>MAX){save(f.blob,f.name+'.'+f.ext);note(btn,'Long call: saved in the original format (.'+f.ext+'). It plays in Chrome, VLC and Android.',true);return}
      return toMp3(f.blob).then(function(m){save(m.blob,f.name+'.mp3');note(btn,'Saved: '+f.name+'.mp3',true)},function(){save(f.blob,f.name+'.'+f.ext);note(btn,'MP3 copy could not be made here; saved in the original format (.'+f.ext+').',true)})})
    .catch(function(e){note(btn,e.message||'Download failed.',false)}).then(function(){btn.disabled=false;btn.textContent=t0})}
  return{get:get,toMp3:toMp3,fetchRec:fetchRec};
})();
</script>
@endonce
