/* DutyFree BD: realistic globe (WebGL Earth from NASA Blue Marble / Black Marble + 2D overlay for planes). No libraries. */
(function () {
  'use strict';
  var D = Math.PI / 180;
  var CITIES = {
    DXB: ['Dubai', 25.25, 55.36, 1], DAC: ['Dhaka', 23.84, 90.40, 1], CGP: ['Chattogram', 22.25, 91.81, 1],
    SIN: ['Singapore', 1.36, 103.99], BKK: ['Bangkok', 13.69, 100.75], IST: ['Istanbul', 41.26, 28.74], LHR: ['London', 51.47, -0.45],
    DOH: ['Doha', 25.27, 51.61], JED: ['Jeddah', 21.68, 39.15], KUL: ['Kuala Lumpur', 2.74, 101.70], DEL: ['Delhi', 28.56, 77.10],
    HKG: ['Hong Kong', 22.31, 113.92], JFK: ['New York', 40.64, -73.78], FRA: ['Frankfurt', 50.03, 8.57], NBO: ['Nairobi', -1.32, 36.93], CCU: ['Kolkata', 22.65, 88.45], SYD: ['Sydney', -33.94, 151.18], JNB: ['Johannesburg', -26.14, 28.25], GRU: ['Sao Paulo', -23.43, -46.47],
    LAX: ['Los Angeles', 33.94, -118.41], NRT: ['Tokyo', 35.77, 140.39], PEK: ['Beijing', 40.08, 116.58], CAI: ['Cairo', 30.12, 31.41], MCT: ['Muscat', 23.59, 58.28],
    KHI: ['Karachi', 24.91, 67.16], CMB: ['Colombo', 7.18, 79.88], KTM: ['Kathmandu', 27.70, 85.36], RUH: ['Riyadh', 24.96, 46.70]
  };
  var ROUTES = [['DXB', 'DAC'], ['DXB', 'CGP'], ['DOH', 'DAC'], ['SIN', 'DAC'], ['BKK', 'CGP'], ['IST', 'DXB'], ['LHR', 'DXB'], ['KUL', 'DAC'],
    ['JED', 'DAC'], ['HKG', 'DAC'], ['FRA', 'DXB'], ['DEL', 'DXB'], ['JFK', 'LHR'], ['NBO', 'DXB'], ['SIN', 'DXB'], ['DXB', 'JNB'], ['SIN', 'SYD'], ['HKG', 'SIN'], ['NRT', 'HKG'], ['LAX', 'NRT'], ['JFK', 'LAX'], ['GRU', 'LHR'], ['CAI', 'DXB'], ['KHI', 'DAC'], ['CMB', 'DAC'],
    ['MCT', 'CGP'], ['RUH', 'DAC'], ['DXB', 'NRT'], ['PEK', 'DAC'], ['LHR', 'DEL'], ['CCU', 'DXB'], ['KTM', 'DAC'], ['DXB', 'JFK'], ['SYD', 'DXB'], ['DOH', 'CGP']];
  function vec(lat, lon) { var la = lat * D, lo = lon * D, c = Math.cos(la); return [c * Math.sin(lo), Math.sin(la), c * Math.cos(lo)]; }
  function slerp(a, b, t, om) { var s = Math.sin(om), k1 = Math.sin((1 - t) * om) / s, k2 = Math.sin(t * om) / s; return [a[0] * k1 + b[0] * k2, a[1] * k1 + b[1] * k2, a[2] * k1 + b[2] * k2]; }
  /* top-down airliner, nose to +x, about 25 units long */
  var PLANE = (function () {
    var p = new Path2D();
    p.moveTo(12.5, 0); p.quadraticCurveTo(11.6, 1.5, 7, 1.5); p.lineTo(-9, 1.05); p.quadraticCurveTo(-12.4, .6, -12.8, 0); p.quadraticCurveTo(-12.4, -.6, -9, -1.05); p.lineTo(7, -1.5); p.quadraticCurveTo(11.6, -1.5, 12.5, 0); p.closePath();
    p.moveTo(3.2, 1.2); p.lineTo(-4.6, 10.4); p.lineTo(-6.9, 10.4); p.lineTo(-4.4, 1.2); p.closePath();
    p.moveTo(3.2, -1.2); p.lineTo(-4.6, -10.4); p.lineTo(-6.9, -10.4); p.lineTo(-4.4, -1.2); p.closePath();
    p.moveTo(-8.6, .9); p.lineTo(-11.8, 4.8); p.lineTo(-13, 4.8); p.lineTo(-11.6, .9); p.closePath();
    p.moveTo(-8.6, -.9); p.lineTo(-11.8, -4.8); p.lineTo(-13, -4.8); p.lineTo(-11.6, -.9); p.closePath();
    p.ellipse(-.6, 4.6, 1.5, .8, 0, 0, 7); p.moveTo(0, 0); p.ellipse(-.6, -4.6, 1.5, .8, 0, 0, 7);
    return p;
  })();
  var VS = 'attribute vec2 a;void main(){gl_Position=vec4(a,0.,1.);}';
  var FS = [
    '#ifdef GL_FRAGMENT_PRECISION_HIGH', 'precision highp float;', '#else', 'precision mediump float;', '#endif',
    'uniform vec2 uC;uniform float uR,uYaw,uTilt,uTime;uniform vec3 uSun;uniform sampler2D uDay,uNight,uWater;',
    'const float PI=3.14159265;',
    'float hash(vec3 p){p=fract(p*.3183099+.1);p*=17.;return fract(p.x*p.y*p.z*(p.x+p.y+p.z));}',
    'float nz(vec3 x){vec3 i=floor(x),f=fract(x);f=f*f*(3.-2.*f);',
    ' return mix(mix(mix(hash(i),hash(i+vec3(1,0,0)),f.x),mix(hash(i+vec3(0,1,0)),hash(i+vec3(1,1,0)),f.x),f.y),',
    '            mix(mix(hash(i+vec3(0,0,1)),hash(i+vec3(1,0,1)),f.x),mix(hash(i+vec3(0,1,1)),hash(i+vec3(1,1,1)),f.x),f.y),f.z);}',
    'float fbm(vec3 p){float a=.5,s=0.;for(int i=0;i<3;i++){s+=a*nz(p);p=p*2.03+vec3(1.7,9.2,3.1);a*=.5;}return s;}',
    'void main(){',
    ' vec2 q=(gl_FragCoord.xy-uC)/uR;float r2=dot(q,q);vec3 L=normalize(uSun);',
    ' if(r2>1.){float r=sqrt(r2);float g=exp(-(r-1.)*10.)*step(r,1.5);float side=.35+.65*clamp(dot(normalize(q),normalize(L.xy))*.5+.5,0.,1.);',
    '  vec3 c=mix(vec3(.28,.52,1.),vec3(.55,.75,1.),side);float al=g*side*.85;gl_FragColor=vec4(c*al,al);return;}',
    ' float z=sqrt(1.-r2);vec3 n=vec3(q,z);',
    ' float ct=cos(uTilt),st=sin(uTilt);vec3 w=vec3(n.x,n.y*ct+n.z*st,-n.y*st+n.z*ct);',
    ' float cy=cos(uYaw),sy=sin(uYaw);w=vec3(w.x*cy+w.z*sy,w.y,-w.x*sy+w.z*cy);',
    ' vec2 uv=vec2(atan(w.x,w.z)/(2.*PI)+.5,.5-asin(clamp(w.y,-1.,1.))/PI);',
    ' vec3 day=texture2D(uDay,uv).rgb;vec3 nt=texture2D(uNight,uv).rgb;float wat=texture2D(uWater,uv).r;',
    ' float nl=dot(n,L);float dm=smoothstep(-.12,.24,nl);float df=clamp(nl,0.,1.);',
    ' vec3 lit=day*(.16+1.12*pow(df,.75));',
    ' vec3 hh=normalize(L+vec3(0.,0.,1.));float sp=pow(max(dot(n,hh),0.),70.)*wat;lit+=vec3(1.,.93,.8)*sp*.85;',
    ' vec3 nightc=day*.05+nt*vec3(1.25,.95,.65)*1.5;',
    ' vec3 col=mix(nightc,lit,dm);',
    ' float tw=smoothstep(-.14,.0,nl)*smoothstep(.26,.03,nl);col+=vec3(1.,.42,.16)*tw*.22;',
    ' vec3 cp=w*2.7+vec3(uTime*.006,0.,0.);float cl=smoothstep(.50,.76,fbm(cp))*.6;',
    ' vec3 cc=vec3(.96,.98,1.)*(.10+.95*clamp(nl*1.15+.12,0.,1.));col=mix(col,cc,cl*mix(.28,1.,dm));',
    ' float fr=pow(1.-z,2.4);vec3 at=mix(vec3(.12,.24,.5),vec3(.32,.6,1.),dm);col+=at*fr*(.4+.9*dm)*1.35;',
    ' float e=smoothstep(1.,.994,sqrt(r2));gl_FragColor=vec4(col*e,e);',
    '}'
  ].join('\n');

  function start(root) {
    var gc = root.querySelector('canvas.gl'), oc = root.querySelector('canvas.ov'); if (!gc || !oc) return fallback(root);
    var gl = null; try { gl = gc.getContext('webgl', { alpha: true, premultipliedAlpha: true, antialias: false }) || gc.getContext('experimental-webgl'); } catch (e) {}
    if (!gl) return fallback(root);
    function sh(t, s) { var o = gl.createShader(t); gl.shaderSource(o, s); gl.compileShader(o); return gl.getShaderParameter(o, gl.COMPILE_STATUS) ? o : null; }
    var vs = sh(gl.VERTEX_SHADER, VS), fs = sh(gl.FRAGMENT_SHADER, FS); if (!vs || !fs) return fallback(root);
    var pr = gl.createProgram(); gl.attachShader(pr, vs); gl.attachShader(pr, fs); gl.linkProgram(pr); if (!gl.getProgramParameter(pr, gl.LINK_STATUS)) return fallback(root);
    gl.useProgram(pr);
    var buf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, buf); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
    var al = gl.getAttribLocation(pr, 'a'); gl.enableVertexAttribArray(al); gl.vertexAttribPointer(al, 2, gl.FLOAT, false, 0, 0);
    var U = {}; ['uC', 'uR', 'uYaw', 'uTilt', 'uTime', 'uSun', 'uDay', 'uNight', 'uWater'].forEach(function (n) { U[n] = gl.getUniformLocation(pr, n); });
    var base = root.getAttribute('data-tex') || '/globe/';
    [['uDay', 0, 'day.jpg', [10, 30, 60]], ['uNight', 1, 'night.jpg', [0, 0, 0]], ['uWater', 2, 'water.jpg', [255, 255, 255]]].forEach(function (d) {
      var t = gl.createTexture(); gl.activeTexture(gl.TEXTURE0 + d[1]); gl.bindTexture(gl.TEXTURE_2D, t);
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, 1, 1, 0, gl.RGB, gl.UNSIGNED_BYTE, new Uint8Array(d[3]));
      function par() { gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE); }
      par(); gl.uniform1i(U[d[0]], d[1]);
      var im = new Image(); im.onload = function () { gl.activeTexture(gl.TEXTURE0 + d[1]); gl.bindTexture(gl.TEXTURE_2D, t); gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, im); par(); }; im.src = base + d[2];
    });
    gc.addEventListener('webglcontextlost', function (e) { e.preventDefault(); stopped = true; }, false);

    var cx2 = oc.getContext('2d'), cards = [].slice.call(root.querySelectorAll('[data-tag]'));
    var tags = cards.map(function (c) { return c.getAttribute('data-tag'); }); if (!tags.length) tags = ['Duty Free'];
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var dpr = Math.min(1.75, window.devicePixelRatio || 1), W = 0, H = 0, R = 0, CX = 0, CY = 0, stopped = false;
    var NP = 999, yaw = 74, tilt = 20 * D, spin = -4.5, drag = null, last = 0, vis = true, feat = -1, featAt = 0, active = -1, raf = 0, T = 0;
    var SUN = [-.62, .30, .50], cityV = {}, k; for (k in CITIES) cityV[k] = vec(CITIES[k][1], CITIES[k][2]);
    var planes = [];
    ROUTES.forEach(function (r, i) {
      var a = cityV[r[0]], b = cityV[r[1]], om = Math.acos(Math.max(-1, Math.min(1, a[0] * b[0] + a[1] * b[1] + a[2] * b[2])));
      var dur = 8 + om / Math.PI * 22, h = .035 + .11 * om / Math.PI;
      planes.push({ a: a, b: b, om: om, dur: dur, h: h, t: (i * .37) % 1 }, { a: b, b: a, om: om, dur: dur, h: h, t: (i * .37 + .5) % 1 });
    });
    function size() { var w = gc.clientWidth || 300, h = gc.clientHeight || 300; W = gc.width = oc.width = Math.round(w * dpr); H = gc.height = oc.height = Math.round(h * dpr); R = Math.min(W, H) * .43; NP = (w < 520) ? 44 : planes.length; CX = W / 2; CY = H / 2; gl.viewport(0, 0, W, H); }
    function rot(v) { var p = yaw * D, c = Math.cos(p), s = Math.sin(p), x = v[0] * c - v[2] * s, z = v[0] * s + v[2] * c, y = v[1], ct = Math.cos(tilt), st = Math.sin(tilt); return [x, y * ct - z * st, y * st + z * ct]; }
    function pt(v, alt) { var q = rot(v), m = alt || 1; return [CX + R * q[0] * m, CY - R * q[1] * m, q[2] * m, q[0] * q[0] + q[1] * q[1]]; }
    function seen(p) { return p[2] > 0 || p[3] > 1; }

    function frame(now) {
      raf = 0; if (!vis || document.hidden || stopped) return;
      var dt = last ? Math.min(.05, (now - last) / 1000) : 0; last = now; T += dt;
      if (!drag && !reduce) yaw += spin * dt;
      gl.useProgram(pr); gl.uniform2f(U.uC, CX, H - CY); gl.uniform1f(U.uR, R); gl.uniform1f(U.uYaw, yaw * D); gl.uniform1f(U.uTilt, tilt); gl.uniform1f(U.uTime, T); gl.uniform3f(U.uSun, SUN[0], SUN[1], SUN[2]);
      gl.clearColor(0, 0, 0, 0); gl.clear(gl.COLOR_BUFFER_BIT); gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
      var i, p, s, c = cx2; c.clearRect(0, 0, W, H);
      // flight paths
      c.lineWidth = Math.max(1, dpr * .8); c.strokeStyle = 'rgba(255,214,170,.20)'; c.setLineDash([3 * dpr, 6 * dpr]); c.beginPath();
      for (i = 0; i < Math.min(NP, planes.length); i += 2) { var pl = planes[i], on = false; for (s = 0; s <= 36; s++) { var tt = s / 36; p = pt(slerp(pl.a, pl.b, tt, pl.om), 1 + pl.h * Math.sin(Math.PI * tt)); if (seen(p)) { if (!on) { c.moveTo(p[0], p[1]); on = true; } else c.lineTo(p[0], p[1]); } else on = false; } }
      c.stroke(); c.setLineDash([]);
      // cities: warm glow
      for (k in CITIES) {
        var ci = CITIES[k]; p = pt(cityV[k]); if (p[2] <= .05) continue; var big = ci[3], rr = (big ? 3.4 : 1.9) * dpr;
        var g = c.createRadialGradient(p[0], p[1], 0, p[0], p[1], rr * 4); g.addColorStop(0, 'rgba(255,170,80,.75)'); g.addColorStop(1, 'rgba(255,120,30,0)'); c.fillStyle = g; c.beginPath(); c.arc(p[0], p[1], rr * 4, 0, 7); c.fill();
        c.fillStyle = big ? '#FFB066' : '#ffe3c8'; c.beginPath(); c.arc(p[0], p[1], rr * .75, 0, 7); c.fill();
        if (big) { var ph = (T * .7 + (k.charCodeAt(0) % 7) * .3) % 1; c.strokeStyle = 'rgba(255,150,60,' + (.55 * (1 - ph)) + ')'; c.lineWidth = 1.4 * dpr; c.beginPath(); c.arc(p[0], p[1], rr + ph * 13 * dpr, 0, 7); c.stroke(); }
        if (big && p[2] > .25) { c.font = '700 ' + (11 * dpr) + 'px system-ui,sans-serif'; c.fillStyle = 'rgba(255,255,255,.95)'; c.shadowColor = 'rgba(0,0,0,.85)'; c.shadowBlur = 4 * dpr; var lw = c.measureText(ci[0]).width, ox = k === 'DXB' ? -lw - 8 * dpr : (k === 'DAC' ? -lw * .5 : 8 * dpr), oy = k === 'CGP' ? 15 * dpr : -9 * dpr; c.fillText(ci[0], p[0] + ox, p[1] + oy); c.shadowBlur = 0; }
      }
      // planes: contrail, shadow, airliner
      var vpl = [], sc0 = Math.max(.7, Math.min(1.5, R / 250)) * dpr * .9;
      for (i = 0; i < Math.min(NP, planes.length); i++) {
        var P = planes[i]; if (!reduce) P.t += dt / P.dur; if (P.t >= 1) P.t -= 1; var t = P.t;
        var alt0 = 1 + P.h * Math.sin(Math.PI * t), v0 = slerp(P.a, P.b, t, P.om), p0 = pt(v0, alt0), prev = null; c.lineCap = 'round';
        for (s = 10; s >= 0; s--) {
          var t2 = t - s * .014; if (t2 < 0) continue; var pp = pt(slerp(P.a, P.b, t2, P.om), 1 + P.h * Math.sin(Math.PI * t2));
          if (seen(pp)) { if (prev) { var f = 1 - s / 11; c.strokeStyle = 'rgba(255,255,255,' + (.5 * f * f) + ')'; c.lineWidth = (.8 + 2.2 * f) * dpr * (R / 250 > .8 ? 1 : .8); c.beginPath(); c.moveTo(prev[0], prev[1]); c.lineTo(pp[0], pp[1]); c.stroke(); } prev = pp; } else prev = null;
        }
        if (!seen(p0)) continue;
        var t3 = Math.min(1, t + .01), p1 = pt(slerp(P.a, P.b, t3, P.om), 1 + P.h * Math.sin(Math.PI * t3)), ang = Math.atan2(p1[1] - p0[1], p1[0] - p0[0]);
        var vis0 = Math.max(0, Math.min(1, p0[2] * 4 + .5)), sc = sc0 * (.8 + .3 * Math.max(0, Math.min(1, p0[2] + .3)));
        var ps = pt(v0, 1); if (ps[2] > 0) { c.save(); c.translate(ps[0] + 3 * dpr, ps[1] + 6 * dpr); c.rotate(ang); c.scale(sc * .8, sc * .8); c.globalAlpha = .28 * vis0; c.fillStyle = '#000'; c.fill(PLANE); c.restore(); }
        c.save(); c.translate(p0[0], p0[1]); c.rotate(ang); c.scale(sc, sc); c.globalAlpha = vis0; c.shadowColor = 'rgba(0,0,0,.55)'; c.shadowBlur = 5 * dpr; c.fillStyle = '#F7F9FC'; c.fill(PLANE); c.shadowBlur = 0; c.fillStyle = 'rgba(255,90,0,.9)'; c.fillRect(-13, -.5, 1.2, 1); c.restore();
        if (p0[2] > .3) vpl.push([i, p0]);
      }
      // offer tag on one plane
      if (vpl.length) {
        if (T - featAt > 4.2 || feat < 0 || !vpl.some(function (e) { return e[0] === feat; })) { if (T - featAt > 4.2 || feat < 0) active = (active + 1) % tags.length; feat = vpl[(Math.random() * vpl.length) | 0][0]; featAt = T; mark(active); }
        var tp = null; vpl.forEach(function (e) { if (e[0] === feat) tp = e[1]; });
        if (tp) {
          var txt = tags[active], fs2 = 12 * dpr; c.font = '800 ' + fs2 + 'px system-ui,sans-serif'; var tw2 = c.measureText(txt).width + 16 * dpr, th = fs2 + 10 * dpr;
          var tx = Math.min(W - tw2 - 4 * dpr, Math.max(4 * dpr, tp[0] + 14 * dpr)), ty = Math.max(4 * dpr, tp[1] - th - 12 * dpr);
          c.strokeStyle = 'rgba(255,255,255,.6)'; c.lineWidth = dpr; c.beginPath(); c.moveTo(tp[0], tp[1]); c.lineTo(tx + 6 * dpr, ty + th); c.stroke();
          c.shadowColor = 'rgba(0,0,0,.4)'; c.shadowBlur = 8 * dpr; c.fillStyle = '#FF5A00'; c.beginPath(); (c.roundRect ? c.roundRect(tx, ty, tw2, th, 7 * dpr) : c.rect(tx, ty, tw2, th)); c.fill(); c.shadowBlur = 0;
          c.fillStyle = '#fff'; c.textBaseline = 'middle'; c.fillText(txt, tx + 8 * dpr, ty + th / 2 + dpr * .5); c.textBaseline = 'alphabetic';
        }
      }
      if (!reduce) raf = requestAnimationFrame(frame);
    }
    function mark(i) { cards.forEach(function (cd, j) { cd.classList.toggle('on', j === i); }); }
    function go() { if (!raf && vis && !document.hidden && !stopped) raf = requestAnimationFrame(frame); }
    size(); if (window.ResizeObserver) new ResizeObserver(function () { size(); if (reduce) frame(performance.now()); }).observe(gc); else addEventListener('resize', size);
    if (window.IntersectionObserver) new IntersectionObserver(function (e) { vis = e[0].isIntersecting; if (vis) { last = 0; go(); } }, { threshold: .05 }).observe(root);
    document.addEventListener('visibilitychange', function () { last = 0; go(); });
    oc.addEventListener('pointerdown', function (e) { drag = { x: e.clientX }; try { oc.setPointerCapture(e.pointerId); } catch (x) {} });
    oc.addEventListener('pointermove', function (e) { if (!drag) return; var dx = e.clientX - drag.x; drag.x = e.clientX; yaw -= dx / (R / dpr) / D * .9; if (reduce) frame(performance.now()); });
    function up() { drag = null; } oc.addEventListener('pointerup', up); oc.addEventListener('pointercancel', up);
    if (reduce) { setTimeout(function () { frame(performance.now()); }, 600); } else go();
  }
  /* no WebGL: use the dotted globe instead */
  function fallback(root) {
    var w = root.querySelector('.dfg-gl'); if (!w) return; w.innerHTML = '<canvas role="img" aria-label="Globe"></canvas>';
    var s = document.createElement('script'); s.src = '/dfglobe.js?v=3'; document.head.appendChild(s);
  }
  function boot() { var r = document.querySelectorAll('.dfg2'); for (var i = 0; i < r.length; i++) start(r[i]); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
