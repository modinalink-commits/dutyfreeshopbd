/* DutyFree BD: live globe with flying planes. Pure canvas, no libraries. Land is a 240x120 bit mask of Natural Earth 110m. */
(function () {
  'use strict';
  var MASK = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAADA/wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAPj/z////3EAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD/++f///wcAAPADgAEAAOADAAAAAAAAAAAAAAAg3N4f/v///wcAAB8AAAAAAAA4AAAAAAAAAAAAAAADAOAH/P///wcAAA4AAAAAAAAwAAAAAAAAAAAAAAC8cQwBAP///wcAAAAAAIAHAMD/DwDwAAAAAAAAAOAAAAAAAPz//wMAAAAAAGAAAPz/AQAAAAAAAAAAAOBdc/MDAPj//wEAAAAAABjAwP///z9gAAAAA4AAAED8A/P/AOD//wEAAAAAADjg/v///z//HwCAAPj/A0/8X4PwB/D//wAAAOA/AADh/v///////wM+A/7///8Pw56BB+D/DwAAAPz/Y+zf/f//////////N/D///////+Bf/D/AQAAAP7/x////v//////////GP7//////7/wJ+AfAH4AAD9++P//////////////AOD//////88GH8AfAAwAwJ////////////////9/APz//////wNxDIAPAAAA8M///////////////98/APzf/////wHwAwAGAAAA+M///////////////+ADAPAB+P///wHwMwAAAAAA8A//////////////ZBgAAIACgP///wPgfwAAAAAQAAf///////////8fAA4AACAAAP///z/gfwAAAAAwYMf///////////8PAB8AAAQAAP7////5/wMAAABoQOj///////////8DAA8AAAAAgPz////5/wcAAADs+P////////////9/AAcAAAAAAPj////7/wMAAADg+f////////////9/AAEAAAAAAPj/////zwAAAAAw/v////////////+/AAAAAAAAAPD/////Gw4AAACg//////////////8fAAAAAAAAAOD/////HxAAAADA//////////////8fAAAAAAAAAOD//////wAAAACA//9P/vj///////8PAAAAAAAAAOD/////EwAAAACA//wHfPz////////HAAAAAAAAAOD/////AQAAAAD8g/EH8Pj////////gAAAAAAAAAOD/////AAAAAAD8Aebn+fH//////z8AAAAAAAAAAOD///9/AAAAAAD8AGT8//H//////xpgAAAAAAAAAMD///8/AAAAAAD8AMT8//H/////fzggAAAAAAAAAID///8fAAAAAABwdAD8/////////zE4AAAAAAAAAID///8fAAAAAACwfwBD/////////zA/AAAAAAAAAAD+//8PAAAAAAD4fwAA/////////wAHAAAAAAAAAAD8//8DAAAAAAD8/2OA/////////4EAAAAAAAAAAADo//8DAAAAAAD8/+///////////wEAAAAAAAAAAADo/wkCAAAAAAD+//////z//////wEAAAAAAAAAAADYfwACAAAAAID///8///n//////wEAAAAAAAAAAACgfwAWAAAAAMD///9//+H//////wAAAAAAAAAAAAAgfwAAAAAAAMD///9//jPg////fwAAAAAAAAAAAAAAfgAAAAAAAOD//////H/A////PwEAAAAAAAAAAAAAfAAIAAAAAOD//////f/A/+f/BwAAAAAAAAAAAAAAfDBwAAAAAOD/////+X8A/sN/AAAAAAAAAAAAAAAA+DgAAwAAAOD/////+T8A/oB/AwAAAAAAAAAAAAAA4B8AAAAAAOD/////8x8AfoB/AAMAAAAAAAAAAAAAgPwAAAAAAOD/////8wcAPoD+AAEAAAAAAAAAAAAAAPgBAAAAAOD/////7wEAHAD+AQEAAAAAAAAAAAAAAMAAAAAAAOD/////PwAAHAD8AQQAAAAAAAAAAAAAAICAAgAAAMD/////HwMAGADgAAoAAAAAAAAAAAAAAADBfgAAAID//////wMAGABAAAAAAAAAAAAAAAAAAADy/wAAAID//////wEAIAAGAAwAAAAAAAAAAAAAAADw/wEAAAD//////wEAIAAIAAgAAAAAAAAAAAAAAADw/x8AAAD8+P///wAAAAAZYAAAAAAAAAAAAAAAAADg/z8AAAAAwP///wAAAAAbMAAAAAAAAAAAAAAAAADw/z8AAAAAwP//fwAAAAAWfAAAAAAAAAAAAAAAAAD4/38AAAAAwP//HwAAAAAcficAAAAAAAAAAAAAAAD8//8BAAAAwP//DwAAAAAYPiABAAAAAAAAAAAAAAD8//8DAAAAwP//BwAAAAA4vgEaAAAAAAAAAAAAAAD8//8/AAAAgP//BwAAAABwEBL+AAAAAAAAAAAAAAD8////AAAAAP//AwAAAABgAADwAQAAAAAAAAAAAAD8////AQAAAP//AwAAAADABADyAwEAAAAAAAAAAAD4////AQAAAP7/AwAAAAAAHADwBgQAAAAAAAAAAADw////AAAAAP7/BwAAAAAAAAQADAAAAAAAAAAAAADw//9/AAAAAP7/BwAAAAAAAAAAAAAAAAAAAAAAAADg//9/AAAAAP7/BwAAAAAAAICHAAAAAAAAAAAAAADg//8/AAAAAP//BwEAAAAAANDHAAAAAAAAAAAAAADA//8/AAAAAP//hwMAAAAAAPjHAQAAAAAAAAAAAAAA//8/AAAAAP//4QEAAAAAAPzfAQAAAAAAAAAAAAAA/v8/AAAAAP//4AEAAAAAAP7/AwAAAAAAAAAAAAAA/v8fAAAAAP5/wAAAAAAAgP//ByAAAAAAAAAAAAAA/v8fAAAAAP7/4AAAAAAA4P//D0AAAAAAAAAAAAAA/v8HAAAAAPz/4AAAAAAA8P//HwAAAAAAAAAAAAAA/v8AAAAAAPx/YAAAAAAA8P//PwAAAAAAAAAAAAAA/v8AAAAAAPw/AAAAAAAA8P//PwAAAAAAAAAAAAAA/v8AAAAAAPw/AAAAAAAA8P//PwAAAAAAAAAAAAAA/38AAAAAAPgfAAAAAAAA4P//PwAAAAAAAAAAAAAA/z8AAAAAAPAPAAAAAAAA4P//PwAAAAAAAAAAAAAA/x8AAAAAAPAHAAAAAAAA4B/+PwAAAAAAAAAAAAAA/w8AAAAAAPABAAAAAAAA4Af0HwAAAAAAAAAAAAAA/wMAAAAAAAAAAAAAAAAAAADwDwAIAAAAAAAAAACA/wMAAAAAAAAAAAAAAAAAAADgDwAQAAAAAAAAAACA/wEAAAAAAAAAAAAAAAAAAADAAgBwAAAAAAAAAACAfwAAAAAAAAAAAAAAAAAAAAAAAAAwAAAAAAAAAACAHwAAAAAAAAAAAAAAAAAAAAAABgAQAAAAAAAAAACAHwAAAAAAAAAAAAAAAAAAAAAABgAMAAAAAAAAAADADwAAAAAAAAAAAAAAAAAAAAAAAAADAAAAAAAAAADABwAAAAAAAAAAAAAAAAAAAAAAAIADAAAAAAAAAADADwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAADABwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAADAAwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAADAgwEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACABwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAHwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAGAAAAAAAAAAAADwAAAR4Pv4HAAAAAAAAAAAAAAAACAAAAAAAAAAA4P8/4P//////BwAAAAAAAAAAAAAAPwAAAAAAAADg//8//P///////wMAAAAAAAAAAACAewAAAADI/v////8///////////8BAAAAAAAAABAAeAAAAID///////////////////8DAAAAAAAe4P//fwAAAMD//////////////////38AAADA////////BwAAAPz//////////////////x8AAEDz//////8/AAAA8P///////////////////x8AABj///////8PAIAH/////////////////////38AAADA//////8/gPAD4P///////////////////wcAAAD+////////P4Dx/////////////////////w8AAAD8//////////////////////////////////8A/wMA/v//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////', MW = 240, MH = 120, D = Math.PI / 180;
  var CITIES = {
    DXB: ['Dubai', 25.25, 55.36, 1], DAC: ['Dhaka', 23.84, 90.40, 1], CGP: ['Chattogram', 22.25, 91.81, 1],
    SIN: ['Singapore', 1.36, 103.99], BKK: ['Bangkok', 13.69, 100.75], IST: ['Istanbul', 41.26, 28.74], LHR: ['London', 51.47, -0.45],
    DOH: ['Doha', 25.27, 51.61], JED: ['Jeddah', 21.68, 39.15], KUL: ['Kuala Lumpur', 2.74, 101.70], DEL: ['Delhi', 28.56, 77.10],
    HKG: ['Hong Kong', 22.31, 113.92], JFK: ['New York', 40.64, -73.78], FRA: ['Frankfurt', 50.03, 8.57], NBO: ['Nairobi', -1.32, 36.93]
  };
  var ROUTES = [['DXB', 'DAC'], ['DXB', 'CGP'], ['DOH', 'DAC'], ['SIN', 'DAC'], ['BKK', 'CGP'], ['IST', 'DXB'], ['LHR', 'DXB'], ['KUL', 'DAC'],
    ['JED', 'DAC'], ['HKG', 'DAC'], ['FRA', 'DXB'], ['DEL', 'DXB'], ['JFK', 'LHR'], ['NBO', 'DXB'], ['SIN', 'DXB']];
  function vec(lat, lon) { var la = lat * D, lo = lon * D, c = Math.cos(la); return [c * Math.sin(lo), Math.sin(la), c * Math.cos(lo)]; }
  function land() {
    var bin = atob(MASK), out = [];
    for (var y = 0; y < MH; y++) for (var x = 0; x < MW; x++) {
      var i = y * MW + x; if (bin.charCodeAt(i >> 3) & (1 << (i & 7))) out.push(vec(90 - (y + .5) * 180 / MH, -180 + (x + .5) * 360 / MW));
    }
    return out;
  }
  function slerp(a, b, t, om) {
    var s = Math.sin(om), k1 = Math.sin((1 - t) * om) / s, k2 = Math.sin(t * om) / s;
    return [a[0] * k1 + b[0] * k2, a[1] * k1 + b[1] * k2, a[2] * k1 + b[2] * k2];
  }
  var PLANE = (function () {
    var up = [[8, 0], [5, .9], [2, 1.5], [-1, 7.2], [-3, 7.2], [-2.4, 1.7], [-6, 1.3], [-7.4, 3.6], [-8.8, 3.6], [-8, 0]], p = new Path2D(), i;
    p.moveTo(up[0][0], up[0][1]); for (i = 1; i < up.length; i++) p.lineTo(up[i][0], up[i][1]);
    for (i = up.length - 2; i >= 0; i--) p.lineTo(up[i][0], -up[i][1]); p.closePath(); return p;
  })();

  function start(root) {
    var cv = root.querySelector('canvas'); if (!cv || !cv.getContext) return;
    var cx2 = cv.getContext('2d'), LAND = land(), cards = [].slice.call(root.querySelectorAll('[data-tag]'));
    var tags = cards.map(function (c) { return c.getAttribute('data-tag'); }); if (!tags.length) tags = ['Duty Free'];
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var dpr = Math.min(2, window.devicePixelRatio || 1), W = 0, H = 0, R = 0, CX = 0, CY = 0;
    var yaw = 72, tilt = 22 * D, spin = -5.5, drag = null, last = 0, vis = true, feat = -1, featAt = 0, active = -1, raf = 0, T = 0;
    var cityV = {}, k; for (k in CITIES) cityV[k] = vec(CITIES[k][1], CITIES[k][2]);
    var planes = [];
    ROUTES.forEach(function (r, i) {
      var a = cityV[r[0]], b = cityV[r[1]], om = Math.acos(Math.max(-1, Math.min(1, a[0] * b[0] + a[1] * b[1] + a[2] * b[2])));
      var dur = 8 + om / Math.PI * 22, h = .035 + .11 * om / Math.PI;
      planes.push({ a: a, b: b, om: om, dur: dur, h: h, t: (i * .37) % 1, rev: false }, { a: b, b: a, om: om, dur: dur, h: h, t: (i * .37 + .5) % 1, rev: true });
    });
    function size() {
      var w = cv.clientWidth || 300, h = cv.clientHeight || 300; W = cv.width = Math.round(w * dpr); H = cv.height = Math.round(h * dpr);
      R = Math.min(W, H) * .43; CX = W / 2; CY = H / 2;
    }
    function rot(v) {
      var p = yaw * D, c = Math.cos(p), s = Math.sin(p), x = v[0] * c - v[2] * s, z = v[0] * s + v[2] * c, y = v[1];
      var ct = Math.cos(tilt), st = Math.sin(tilt); return [x, y * ct - z * st, y * st + z * ct];
    }
    function pt(v, alt) { var q = rot(v), m = alt || 1; return [CX + R * q[0] * m, CY - R * q[1] * m, q[2] * m, q[0] * q[0] + q[1] * q[1]]; }
    function vis3(q) { return q[2] > 0 || q[3] * 1 > 1; }
    var L = (function () { var x = -.5, y = .55, z = .66, n = Math.sqrt(x * x + y * y + z * z); return [x / n, y / n, z / n]; })();

    function frame(now) {
      raf = 0; if (!vis || document.hidden) return;
      var dt = last ? Math.min(.05, (now - last) / 1000) : 0; last = now; T += dt;
      if (!drag && !reduce) yaw += spin * dt;
      var g, i, p, q, b;
      cx2.clearRect(0, 0, W, H);
      // atmosphere
      g = cx2.createRadialGradient(CX, CY, R * .96, CX, CY, R * 1.42); g.addColorStop(0, 'rgba(255,120,40,.30)'); g.addColorStop(.35, 'rgba(70,140,255,.16)'); g.addColorStop(1, 'rgba(70,140,255,0)');
      cx2.fillStyle = g; cx2.beginPath(); cx2.arc(CX, CY, R * 1.42, 0, 7); cx2.fill();
      // ocean
      g = cx2.createRadialGradient(CX - R * .35, CY - R * .4, R * .1, CX, CY, R); g.addColorStop(0, '#1c3a6b'); g.addColorStop(.6, '#0e2140'); g.addColorStop(1, '#070f1f');
      cx2.fillStyle = g; cx2.beginPath(); cx2.arc(CX, CY, R, 0, 7); cx2.fill();
      // land dots in 6 brightness buckets
      var B = 6, paths = [], d = Math.max(1.4, R / 150), n;
      for (i = 0; i < B; i++) paths.push(new Path2D());
      for (i = 0; i < LAND.length; i++) {
        q = rot(LAND[i]); if (q[2] <= .02) continue;
        n = q[0] * L[0] + q[1] * L[1] + q[2] * L[2]; b = Math.max(0, n) * .75 + q[2] * .25; b = Math.min(.999, b);
        paths[(b * B) | 0].rect(CX + R * q[0] - d / 2, CY - R * q[1] - d / 2, d, d);
      }
      var cols = ['#27508c', '#2f64ad', '#3f7fcb', '#5a9be0', '#86bdf2', '#bfe0ff'];
      for (i = 0; i < B; i++) { cx2.fillStyle = cols[i]; cx2.fill(paths[i]); }
      // rim light
      g = cx2.createRadialGradient(CX, CY, R * .86, CX, CY, R); g.addColorStop(0, 'rgba(120,180,255,0)'); g.addColorStop(1, 'rgba(120,180,255,.28)');
      cx2.fillStyle = g; cx2.beginPath(); cx2.arc(CX, CY, R, 0, 7); cx2.fill();
      // route lines
      cx2.lineWidth = Math.max(1, dpr * .8); cx2.strokeStyle = 'rgba(255,170,110,.16)'; cx2.setLineDash([3 * dpr, 5 * dpr]); cx2.beginPath();
      for (i = 0; i < planes.length; i += 2) {
        var pl = planes[i], on = false;
        for (var s = 0; s <= 36; s++) {
          var tt = s / 36, v = slerp(pl.a, pl.b, tt, pl.om), alt = 1 + pl.h * Math.sin(Math.PI * tt); p = pt(v, alt);
          if (p[2] > 0 || p[3] > 1) { if (!on) { cx2.moveTo(p[0], p[1]); on = true; } else cx2.lineTo(p[0], p[1]); } else on = false;
        }
      }
      cx2.stroke(); cx2.setLineDash([]);
      // cities
      for (k in CITIES) {
        var c = CITIES[k]; p = pt(cityV[k]); if (p[2] <= .05) continue;
        var big = c[3], rr = (big ? 3.2 : 1.8) * dpr;
        if (big) { var ph = (T * .8 + (k.charCodeAt(0) % 7) * .3) % 1; cx2.strokeStyle = 'rgba(255,110,30,' + (.55 * (1 - ph)) + ')'; cx2.lineWidth = 1.5 * dpr; cx2.beginPath(); cx2.arc(p[0], p[1], rr + ph * 12 * dpr, 0, 7); cx2.stroke(); }
        cx2.fillStyle = big ? '#FF6A1A' : '#ffd2b0'; cx2.beginPath(); cx2.arc(p[0], p[1], rr, 0, 7); cx2.fill();
        if (big && p[2] > .25) { cx2.font = '700 ' + (11 * dpr) + 'px system-ui,sans-serif'; cx2.fillStyle = 'rgba(255,255,255,.92)'; var lw = cx2.measureText(c[0]).width, ox = k === 'DXB' ? -lw - 8 * dpr : (k === 'DAC' ? -lw * .5 : 8 * dpr), oy = k === 'CGP' ? 15 * dpr : -9 * dpr; cx2.fillText(c[0], p[0] + ox, p[1] + oy); }
      }
      // planes
      var tagPl = null, tagP = null, vplanes = [];
      for (i = 0; i < planes.length; i++) {
        var P = planes[i]; if (!reduce) P.t += dt / P.dur; if (P.t >= 1) P.t -= 1;
        var t = P.t, v0 = slerp(P.a, P.b, t, P.om), a0 = 1 + P.h * Math.sin(Math.PI * t), p0 = pt(v0, a0);
        // trail
        var on2 = false; cx2.lineWidth = 1.6 * dpr;
        for (var s2 = 10; s2 >= 0; s2--) {
          var t2 = t - s2 * .012; if (t2 < 0) continue; var pp = pt(slerp(P.a, P.b, t2, P.om), 1 + P.h * Math.sin(Math.PI * t2));
          if (pp[2] > 0 || pp[3] > 1) { if (on2) { cx2.strokeStyle = 'rgba(255,190,140,' + (.5 * (1 - s2 / 11)) + ')'; cx2.beginPath(); cx2.moveTo(on2[0], on2[1]); cx2.lineTo(pp[0], pp[1]); cx2.stroke(); } on2 = pp; } else on2 = false;
        }
        if (!(p0[2] > 0 || p0[3] > 1)) continue;
        var t3 = Math.min(1, t + .01), p1 = pt(slerp(P.a, P.b, t3, P.om), 1 + P.h * Math.sin(Math.PI * t3)), ang = Math.atan2(p1[1] - p0[1], p1[0] - p0[0]);
        var sc = (.75 + .35 * Math.max(0, Math.min(1, p0[2] + .3))) * dpr * (R / 230 < .75 ? .75 : Math.min(1.4, R / 230));
        cx2.save(); cx2.translate(p0[0], p0[1]); cx2.rotate(ang); cx2.scale(sc, sc);
        cx2.shadowColor = 'rgba(255,120,30,.9)'; cx2.shadowBlur = 8 * dpr; cx2.fillStyle = '#fff'; cx2.fill(PLANE); cx2.restore();
        if (p0[2] > .3) vplanes.push([i, p0]);
      }
      // featured offer tag follows one visible plane
      if (vplanes.length) {
        if (T - featAt > 4.2 || feat < 0 || !vplanes.some(function (e) { return e[0] === feat; })) {
          if (T - featAt > 4.2 || feat < 0) { active = (active + 1) % tags.length; }
          feat = vplanes[(Math.random() * vplanes.length) | 0][0]; featAt = T; mark(active);
        }
        vplanes.forEach(function (e) { if (e[0] === feat) tagP = e[1]; });
        if (tagP) {
          var txt = tags[active], fs = 12 * dpr; cx2.font = '800 ' + fs + 'px system-ui,sans-serif'; var tw = cx2.measureText(txt).width + 16 * dpr, th = fs + 10 * dpr;
          var tx = Math.min(W - tw - 4 * dpr, Math.max(4 * dpr, tagP[0] + 12 * dpr)), ty = Math.max(4 * dpr, tagP[1] - th - 10 * dpr);
          cx2.strokeStyle = 'rgba(255,255,255,.55)'; cx2.lineWidth = dpr; cx2.beginPath(); cx2.moveTo(tagP[0], tagP[1]); cx2.lineTo(tx + 6 * dpr, ty + th); cx2.stroke();
          cx2.fillStyle = '#FF5A00'; cx2.beginPath(); (cx2.roundRect ? cx2.roundRect(tx, ty, tw, th, 7 * dpr) : cx2.rect(tx, ty, tw, th)); cx2.fill();
          cx2.fillStyle = '#fff'; cx2.textBaseline = 'middle'; cx2.fillText(txt, tx + 8 * dpr, ty + th / 2 + dpr * .5); cx2.textBaseline = 'alphabetic';
        }
      }
      if (!reduce) raf = requestAnimationFrame(frame);
    }
    function mark(i) { cards.forEach(function (c, j) { c.classList.toggle('on', j === i); }); }
    function go() { if (!raf && vis && !document.hidden) raf = requestAnimationFrame(frame); }
    size(); if (window.ResizeObserver) new ResizeObserver(function () { size(); if (reduce) frame(performance.now()); }).observe(cv); else addEventListener('resize', size);
    if (window.IntersectionObserver) new IntersectionObserver(function (e) { vis = e[0].isIntersecting; if (vis) { last = 0; go(); } }, { threshold: .05 }).observe(root);
    document.addEventListener('visibilitychange', function () { last = 0; go(); });
    cv.addEventListener('pointerdown', function (e) { drag = { x: e.clientX, y: e.clientY, moved: false }; try { cv.setPointerCapture(e.pointerId); } catch (x) {} });
    cv.addEventListener('pointermove', function (e) { if (!drag) return; var dx = e.clientX - drag.x; drag.x = e.clientX; yaw -= dx / (R / dpr) / D * .9; if (reduce) frame(performance.now()); });
    function up() { drag = null; } cv.addEventListener('pointerup', up); cv.addEventListener('pointercancel', up);
    if (reduce) frame(performance.now()); else go();
  }
  function boot() { var r = document.querySelectorAll('.dfg'); for (var i = 0; i < r.length; i++) start(r[i]); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
