(function () {
  var D = window.DFD;
  function heroArt() {
    var aw = ''; for (var i = 0; i < 10; i++) aw += '<path d="M' + (120 + i * 30) + ' 82h30v24a15 15 0 0 1-30 0z" fill="' + (i % 2 ? '#c9000b' : '#232323') + '"/>';
    var badge = D.config.ageGate ? '<circle cx="221" cy="194" r="30" fill="#c9000b"/><circle cx="221" cy="194" r="36" fill="none" stroke="#232323" stroke-width="3"/><text x="221" y="203" text-anchor="middle" font-family="Plus Jakarta Sans, Arial, sans-serif" font-weight="800" font-size="26" fill="#fff">18+</text>'
      : '<circle cx="221" cy="194" r="30" fill="#c9000b"/><circle cx="221" cy="194" r="36" fill="none" stroke="#232323" stroke-width="3"/><path d="M205 194l12 12 22-24" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>';
    return '<svg viewBox="0 0 560 360" role="presentation" preserveAspectRatio="xMidYMid meet"><path d="M60 210C40 110 120 30 250 30c120 0 230 50 250 150 18 90-60 160-200 160S76 300 60 210Z" fill="#c9000b" opacity=".10"/>' +
      '<rect x="120" y="108" width="300" height="204" rx="18" fill="#fff" stroke="#232323" stroke-width="4"/><g>' + aw + '</g><rect x="116" y="76" width="308" height="10" rx="5" fill="#232323"/>' +
      '<rect x="146" y="148" width="150" height="92" rx="12" fill="#f3eee3"/>' + badge +
      '<rect x="318" y="158" width="78" height="154" rx="10" fill="#232323"/><circle cx="380" cy="238" r="5" fill="#c9000b"/><rect x="30" y="312" width="500" height="8" rx="4" fill="#232323" opacity=".85"/>' +
      '<g><rect x="436" y="254" width="96" height="58" rx="8" fill="#232323"/><rect x="480" y="254" width="8" height="58" fill="#fff" opacity=".85"/><rect x="448" y="198" width="74" height="56" rx="8" fill="#c9000b"/><rect x="480" y="198" width="8" height="56" fill="#fff" opacity=".85"/></g>' +
      '<g><rect x="36" y="262" width="76" height="50" rx="8" fill="#c9000b"/><rect x="68" y="262" width="8" height="50" fill="#232323" opacity=".8"/></g></svg>';
  }
  function count(cat) { return D.products.filter(function (p) { return p.category === cat; }).length; }
  D.route(/^\/$/, function (app) {
    var order = ['c-hard', 'c-slim', 'c-nano', 'c-cigar', 'c-vape'];
    // Owner, 28 Nov 2026: every category in the owner's screenshot order (D.categories is already sorted server-side). Under each title: a slider with the first five products of that category's list
    // (same default order as the Store list: name A-Z; fewer than five = show what exists). View All opens the full category list. Lighters & accessories stay off the home page.
    var brands = '';
    D.categories.forEach(function (c) {
      if (/lighter/i.test(c.top || '') || /^lighters?$/i.test(c.name)) return;
      var l = D.products.filter(function (p) { return p.category === c.id; }).sort(function (x, y) { return x.name.localeCompare(y.name); });
      if (!l.length) return;
      var href = '#/store?cat=' + encodeURIComponent(c.id);
      brands += '<section class="catrow" aria-label="' + D.esc(c.name) + '"><div class="crow-h"><h2>' + D.esc(c.name) + '</h2><a class="viewall" href="' + href + '">View All <span aria-hidden="true">\u2192</span><span class="vn">' + l.length + '</span></a></div>' +
        '<div class="slider"><button class="sbtn prev" type="button" aria-label="Previous ' + D.esc(c.name) + '">\u2039</button><div class="strack" tabindex="-1">' +
        l.slice(0, 5).map(function (p) { return D.card(p).replace('loading="lazy"', 'loading="eager"'); }).join('') +
        '</div><button class="sbtn next" type="button" aria-label="Next ' + D.esc(c.name) + '">\u203a</button></div></section>';
    });
    var tiles = '';
    tiles += '<div class="t i-1"><span class="ico" aria-hidden="true">৳</span><div><h3 style="font-size:1.1rem">Cash on delivery</h3><small>Pay when it arrives</small></div></div>' +
      '<div class="t i-2"><span class="ico" aria-hidden="true">→</span><div><h3 style="font-size:1.1rem">Nationwide delivery</h3><small>Across Bangladesh</small></div></div>' +
      '<div class="t i-3"><span class="ico" aria-hidden="true">✓</span><div><h3 style="font-size:1.1rem">' + (D.config.ageGate ? 'Age verification' : 'Easy ordering') + '</h3><small>' + (D.config.ageGate ? 'For adults 18 and over' : 'Confirmed by phone') + '</small></div></div>';
    app.innerHTML = (D.config.ageGate ? '<p class="notice">Smoking is harmful to health. Not sold to anyone under 18.</p>' : '') +
      '<section class="hero" aria-label="Hero"><div class="txt"><span class="crumb">Home</span><h1>Duty free, <em>delivered</em></h1><p>' + D.esc(D.config.heroText) + '</p><div class="acts"><a class="btn" href="#/store">Shop now</a>' + (D.config.cta2 ? '<a class="btn line" href="' + D.config.cta2.href + '">' + D.esc(D.config.cta2.label) + '</a>' : '') + '</div></div><div class="art" aria-hidden="true">' + heroArt() + '</div></section>' +
      (brands ? '<div class="catrows">' + brands + '</div>' : '') +
      '<section class="bento" aria-label="Delivery and payment">' + tiles + '</section>';

    [].forEach.call(app.querySelectorAll('.slider'), function (root) {
      var tr = root.querySelector('.strack'), sl = [].slice.call(tr.children), prev = root.querySelector('.prev'), next = root.querySelector('.next');
      var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
      function idx() { var best = 0, bd = 1e9; sl.forEach(function (e, i) { var d = Math.abs(e.offsetLeft - tr.offsetLeft - tr.scrollLeft); if (d < bd) { bd = d; best = i; } }); return best; }
      function mark() { prev.disabled = tr.scrollLeft <= 2; next.disabled = tr.scrollLeft + tr.clientWidth >= tr.scrollWidth - 2; root.classList.toggle('nomore', prev.disabled && next.disabled); }
      function go(i) { i = Math.max(0, Math.min(sl.length - 1, i)); tr.scrollTo({ left: sl[i].offsetLeft - tr.offsetLeft, behavior: reduce ? 'auto' : 'smooth' }); }
      prev.addEventListener('click', function () { go(idx() - 1); });
      next.addEventListener('click', function () { go(idx() + 1); });
      tr.addEventListener('scroll', function () { clearTimeout(tr._t); tr._t = setTimeout(mark, 50); }, { passive: true });
      mark(); window.addEventListener('resize', mark);
    });

    return 'Home';
  });
})();
