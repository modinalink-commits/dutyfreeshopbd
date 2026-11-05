<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<link rel="stylesheet" href="/css/nk-type.css?v=1"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>@yield('title') · NK Corporation</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;800&family=Hind+Siliguri:wght@400;600&display=swap">
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f4f7fc;font-family:'Hind Siliguri','Plus Jakarta Sans',sans-serif;color:#14213d;padding:16px;box-sizing:border-box}
.card{background:#fff;border:1px solid #dbe3f0;border-radius:16px;box-shadow:0 1px 2px rgba(20,33,61,.06),0 4px 12px rgba(20,33,61,.04);padding:28px;max-width:460px;width:100%;display:flex;flex-direction:column;gap:12px}
h1{margin:0;font-size:var(--fs-20);line-height:28px}
p{margin:0;font-size:var(--fs-15);line-height:24px;color:#51607a}
.code{font:800 11px 'Plus Jakarta Sans',sans-serif;letter-spacing:.1em;color:#7b8aa8}
.acts{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border-radius:999px;border:1px solid #7b8aa8;background:#fff;color:#14213d;font:600 14px 'Hind Siliguri',sans-serif;text-decoration:none;cursor:pointer}
.btn.pri{background:#123a86;border-color:#123a86;color:#fff}
</style>
</head>
<body>
<main class="card">
  <img src="/img/logo.webp" alt="NK Corporation" width="48" height="48" style="border-radius:12px" onerror="this.remove()">
  <span class="code">@yield('code')</span>
  <h1>@yield('heading')</h1>
  <p>@yield('message')</p>
  <div class="acts">
    <a class="btn pri" href="{{ url('/') }}">হোমে ফিরুন</a>
    <button class="btn" type="button" onclick="history.back()">আগের পাতায়</button>
  </div>
</main>
</body>
</html>
