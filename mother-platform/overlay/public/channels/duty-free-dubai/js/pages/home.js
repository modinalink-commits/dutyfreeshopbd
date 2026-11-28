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
    var tiles = '<a class="t f-all" href="#/store"><svg viewBox="0 0 200 200" aria-hidden="true"><g fill="none" stroke="#fff" stroke-opacity=".18" stroke-width="6"><path d="M0 40h140"/><path d="M0 70h120"/><path d="M0 100h100"/><path d="M0 130h80"/><path d="M0 160h60"/></g></svg><span class="go" aria-hidden="true">→</span><h3>All products</h3><small>' + D.products.length + ' products</small></a>';
    D.categories.slice().sort(function (a, b) { return count(b.id) - count(a.id); }).slice(0, 5).forEach(function (c, i) {
      tiles += '<a class="t ' + order[i] + '" href="#/store?cat=' + c.id + '"><span class="ico" aria-hidden="true">' + c.glyph + '</span><span class="go" aria-hidden="true">→</span><div><h3>' + D.esc(c.name) + '</h3><small>' + count(c.id) + (count(c.id) === 1 ? ' product' : ' products') + '</small></div></a>';
    });
    tiles += '<div class="t i-1"><span class="ico" aria-hidden="true">৳</span><div><h3 style="font-size:1.1rem">Cash on delivery</h3><small>Pay when it arrives</small></div></div>' +
      '<div class="t i-2"><span class="ico" aria-hidden="true">→</span><div><h3 style="font-size:1.1rem">Nationwide delivery</h3><small>Across Bangladesh</small></div></div>' +
      '<div class="t i-3"><span class="ico" aria-hidden="true">✓</span><div><h3 style="font-size:1.1rem">' + (D.config.ageGate ? 'Age verification' : 'Easy ordering') + '</h3><small>' + (D.config.ageGate ? 'For adults 18 and over' : 'Confirmed by phone') + '</small></div></div>';
    var feat = D.products.filter(function (p) { return D.priced(p) && p.stock > 0; }).slice(0, 4);
    app.innerHTML = (D.config.ageGate ? '<p class="notice">Smoking is harmful to health. Not sold to anyone under 18.</p>' : '') +
      '<section class="hero" aria-label="Hero"><div class="txt"><span class="crumb">Home</span><h1>Duty free, <em>delivered</em></h1><p>' + D.esc(D.config.heroText) + '</p><div class="acts"><a class="btn" href="#/store">Shop now</a>' + (D.config.cta2 ? '<a class="btn line" href="' + D.config.cta2.href + '">' + D.esc(D.config.cta2.label) + '</a>' : '') + '</div></div><div class="art" aria-hidden="true">' + heroArt() + '</div></section>' +
      '<section class="bento" aria-label="Categories">' + tiles + '</section>' +
      '<section class="related" aria-label="Featured"><h2>Featured products</h2><div class="grid" id="feat"></div><p style="margin-top:16px"><a class="btn line" href="#/store">View all products</a></p></section>';
    document.getElementById('feat').innerHTML = feat.map(D.card).join('');
    var g = document.getElementById('feat'); g.className = 'grid feat';
    return 'Home';
  });
})();
