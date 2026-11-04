<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Log in · NK Corporation</title>
<link rel="icon" href="/img/logo.webp">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap">
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f4f7fc;font-family:'Plus Jakarta Sans',system-ui,sans-serif;color:#14213d;padding:16px}
.box{width:100%;max-width:400px;background:#fff;border:1px solid #dbe3f0;border-radius:20px;padding:28px;display:flex;flex-direction:column;gap:16px;box-shadow:0 4px 24px rgba(20,33,61,.08)}
.hd{display:flex;align-items:center;gap:12px}.hd img{width:52px;height:52px;border-radius:12px}
.hd b{display:block;font-size:18px}.hd span{font-size:12px;color:#51607a}
label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600;color:#51607a}
input[type=email],input[type=password]{min-height:46px;border:1px solid #7b8aa8;border-radius:10px;padding:0 12px;font:500 15px 'Plus Jakarta Sans',sans-serif}
.rm{flex-direction:row;align-items:center;gap:8px;font-weight:500}
button{min-height:48px;border:0;border-radius:999px;background:#123a86;color:#fff;font:700 15px 'Plus Jakarta Sans',sans-serif;cursor:pointer}
.err{padding:10px 12px;border-radius:10px;background:#fde9e7;color:#b3261e;font-weight:600;font-size:13px}
</style>
</head>
<body>
<form class="box" method="post" action="/login">
  @csrf
  <div class="hd"><img src="/img/logo.webp" alt="NK Corporation logo"><div><b>NK Corporation</b><span>Business control · Log in</span></div></div>
  @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
  <label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
  <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
  <label class="rm"><input type="checkbox" name="remember" value="1"> Keep me logged in</label>
  <button type="submit">Log in</button>
</form>
</body>
</html>
