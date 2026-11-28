{{-- Duty Free Dubai storefront: the approved prototype design (same CSS and JS as the ZIP), filled with this channel's real products. Used only by this channel's home, category, product and search pages. Orders stay closed (liveOrders false). --}}
@php
  $A = fn($f) => \App\Support\ChannelScope::asset($f);
  $ch = \App\Support\ChannelScope::cur();
  $json = \Illuminate\Support\Facades\Cache::remember('dfd.app2.' . \App\Support\ChannelScope::key() . (\Illuminate\Support\Facades\Cache::get('store.ver') ?: 0), 300, function () {
    $SC = \App\Http\Controllers\StoreController::class;
    $cats = \Illuminate\Support\Facades\DB::table('categories')->whereNotNull('slug')->get(['id', 'slug', 'name'])->keyBy('id');
    $rows = \App\Support\StockGuard::listable(\Illuminate\Support\Facades\DB::table('products')->where('published', true))->orderBy('name')->limit(1500)->get();
    $C = []; $P = []; $T = [];
    foreach ($rows as $p) {
      $top = $cats[$p->category_id] ?? null; if (!$top) continue;
      $sub = trim((string)($p->sub ?? ''));
      $cid = $sub !== '' ? (string)\App\Support\Slugs::subSlug($sub) : (string)$top->slug; if ($cid === '') $cid = (string)$top->slug;
      $cname = $sub !== '' ? $sub : (string)$top->name;
      if (!isset($C[$cid])) $C[$cid] = ['id' => $cid, 'name' => $cname, 'glyph' => mb_strtoupper(mb_substr($cname, 0, 1)), 'sort' => count($C) + 1, 'top' => (string)$top->slug, 'sum' => 0, 'n' => 0];
      if ((float)$p->price > 0) { $C[$cid]['sum'] += (float)$p->price; $C[$cid]['n']++; }
      $T[(string)$top->slug] = (string)$top->name;
      $ps = (string)($p->path_slug ?? '');
      $desc = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($p->description ?? ''))));
      $specs = [['Category', $cname]]; if ($sub !== '') $specs[] = ['Section', (string)$top->name];
      $P[] = ['id' => (int)$p->id, 'slug' => (preg_match('/^[a-z0-9-]+$/', $ps) ? $ps . '-' : 'p') . $p->id, 'name' => (string)$p->name, 'category' => $cid, 'top' => (string)$top->slug,
        'price' => (float)$p->price > 0 ? (int)round((float)$p->price) : 0, 'currency' => 'BDT', 'unit' => '', 'stock' => \App\Support\StockGuard::sellable($p) ? 99 : 0,
        'description' => mb_substr($desc, 0, 320), 'specs' => $specs, 'image' => (string)($p->image ?: '/product-placeholder'), 'url' => $SC::url($p)];
    }
    // Order (owner, 28 Nov 2026): premium brands first (highest average price), lighters & accessories always last
    $C = array_values($C);
    usort($C, function ($a, $b) { $la = str_contains($a['top'], 'lighter') ? 1 : 0; $lb = str_contains($b['top'], 'lighter') ? 1 : 0; if ($la !== $lb) return $la <=> $lb; $aa = $a['n'] ? $a['sum'] / $a['n'] : 0; $ab = $b['n'] ? $b['sum'] / $b['n'] : 0; return $ab <=> $aa ?: strcmp($a['name'], $b['name']); });
    foreach ($C as $i => &$c) { $c['sort'] = $i + 1; unset($c['sum'], $c['n']); } unset($c);
    return json_encode(['c' => $C, 'p' => $P, 't' => $T], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
  });
  $homeTitle = trim((string)($ch->cfg['home_title'] ?? '')) ?: 'Duty Free Dubai';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? $homeTitle }}</title>
@if(!empty($desc))<meta name="description" content="{{ $desc }}">@endif
@if(!empty($canon))<link rel="canonical" href="{{ $canon }}">@endif
<meta name="robots" content="{{ $robots ?? (!empty($noindex) ? 'noindex,follow' : 'index,follow,max-image-preview:large') }}">
<meta name="theme-color" content="#c9000b">
<link rel="icon" href="{{ $A('logo/favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ $A('logo/favicon-32x32.png') }}" sizes="32x32" type="image/png">
<link rel="icon" href="{{ $A('logo/favicon.ico') }}" sizes="any">
<link rel="apple-touch-icon" href="{{ $A('logo/apple-touch-icon.png') }}">
<link rel="preload" href="/channels/duty-free-dubai/fonts/plus-jakarta-sans-latin-700-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ $A('css/base.css') }}">
<link rel="stylesheet" href="{{ $A('css/flow.css') }}">
</head>
<body>
<dialog id="gate" aria-labelledby="gate-t" data-age>
  <h2 id="gate-t">Are you 18 years or older?</h2>
  <p id="gate-p">This store lists tobacco products. Smoking is harmful to health. Products are not sold to anyone under 18.</p>
  <div class="acts" id="gate-a"><button class="btn" id="gate-yes" type="button">Yes, I am 18 or older</button><button class="btn line" id="gate-no" type="button">No</button></div>
</dialog>
<header class="top">
  <div class="top-in glass">
    <a class="logo" href="#/" aria-label="Duty Free Dubai, home"><img src="{{ $A('logo/header-light@2x.png') }}" alt="Duty Free Dubai" width="200" height="54"></a>
    <nav class="nav" aria-label="Main"><a href="#/" data-nav="/">Home</a><a href="#/store" data-nav="/store">Store</a><a href="#/cart" data-nav="/cart">Cart</a></nav>
    <form class="search" id="searchform" role="search"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><input id="q" type="search" placeholder="Search products" aria-label="Search products" autocomplete="off"></form>
    <button class="cartbtn" id="open-cart" type="button" aria-haspopup="dialog">Cart <span class="badge" id="count">0</span></button>
    <nav class="mnav" aria-label="Main (mobile)"><a href="#/" data-nav="/">Home</a><a href="#/store" data-nav="/store">Store</a><a href="#/cart" data-nav="/cart">Cart</a></nav>
  </div>
</header>
<div class="awning" aria-hidden="true" style="position:sticky;top:0;margin-top:-1px"></div>
<main class="wrap page" id="app" aria-live="off"></main>
<footer class="wrap"><img class="flogo" src="{{ $A('logo/footer-light@2x.png') }}" alt="Duty Free Dubai" width="320" height="86"><p>&copy; {{ date('Y') }} Duty Free Dubai. Smoking is harmful to health. Not sold to anyone under 18.</p></footer>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<dialog id="cart" aria-labelledby="cart-t">
  <div class="c-head"><h2 id="cart-t">Your cart</h2><button class="btn line" id="close-cart" type="button" style="min-height:42px;padding:0 16px">Close</button></div>
  <div class="c-body">
    <div id="lines"></div>
    <div class="sum"><span>Total</span><span id="total">BDT 0</span></div>
    <div class="c-foot"><a class="btn" id="go-cart" href="#/cart" hidden>View cart</a><a class="btn navy" id="go-checkout" href="#/checkout" hidden>Checkout</a></div>
  </div>
</dialog>
<script>
window.DFD = window.DFD || {};
(function () {
  var d = {!! $json !!};
  DFD.categories = d.c; DFD.products = d.p; DFD.tops = d.t;
  var tk = Object.keys(d.t), vape = d.c.filter(function (c) { return /vape/i.test(c.name); })[0];
  DFD.config = { brand: 'Duty Free Dubai', currency: 'BDT', ageGate: true, liveOrders: false, storageKey: 'dfd-cart-v1',
    homeTitle: @json($homeTitle), heroText: 'Order by the carton. Cash on delivery across Bangladesh.',
    cta2: vape ? { label: vape.name, href: '#/store?cat=' + encodeURIComponent(vape.id) } : (tk[1] ? { label: d.t[tk[1]], href: '#/store?top=' + encodeURIComponent(tk[1]) } : null) };
  var boot = @json($boot ?? '');
  if (boot && (!location.hash || location.hash === '#' || location.hash === '#/')) { try { history.replaceState(null, '', location.pathname + location.search + '#' + boot); } catch (e) { location.hash = boot; } }
})();
</script>
<script src="{{ $A('js/core.js') }}"></script>
<script src="{{ $A('js/pages/shared.js') }}"></script>
<script src="{{ $A('js/pages/home.js') }}"></script>
<script src="{{ $A('js/pages/store.js') }}"></script>
<script src="{{ $A('js/pages/product.js') }}"></script>
<script src="{{ $A('js/pages/cart.js') }}"></script>
<script src="{{ $A('js/pages/checkout.js') }}"></script>
<script src="{{ $A('js/app.js') }}"></script>
</body>
</html>
