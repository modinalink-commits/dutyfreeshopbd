<!doctype html>
<html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Google SEO সংযোগ · Live Dashboard</title>
<style>
body{background:#0a0e18;color:#e8edf6;font-family:'Hind Siliguri',system-ui,sans-serif;font-size:15px;margin:0;padding:24px 16px}
.w{max-width:760px;margin:0 auto}a{color:#ff7a1a}h1{font-size:22px;margin:0 0 4px}p{color:#8591a8;margin:4px 0 14px}
.box{background:#121827;border:1px solid #232c42;border-radius:8px;padding:14px 16px;margin-top:14px}
.ok{border-color:#1f6e45;color:#8fe6b8}.err{border-color:#ff4d4d;color:#ff9d9d}
label{display:block;font-size:13px;color:#8591a8;margin:10px 0 4px}
input,textarea{width:100%;box-sizing:border-box;background:#0e1422;color:#e8edf6;border:1px solid #232c42;border-radius:6px;padding:8px 10px;font:inherit}
textarea{height:150px;font-family:ui-monospace,monospace;font-size:12.5px}
button{font:inherit;font-weight:600;background:#ff7a1a;color:#1a0d00;border:0;border-radius:6px;padding:8px 16px;cursor:pointer;margin-top:12px}
button.g{background:transparent;color:#e8edf6;border:1px solid #232c42;font-weight:500}
ol{padding-left:20px;margin:6px 0}li{margin:5px 0}code{background:#0e1422;border:1px solid #232c42;border-radius:4px;padding:1px 6px;font-size:13px;user-select:all}
</style></head><body><div class="w">
<a href="/admin/live-dashboard">← লাইভ ড্যাশবোর্ড</a>
<h1 style="margin-top:10px">Google SEO (Search Console) সংযোগ</h1>
<p>একবার চাবি বসালে লাইভ ড্যাশবোর্ডে শেষ ৭ দিনের ক্লিক, ইমপ্রেশন, CTR ও গড় অবস্থান নিজে থেকে আসবে। শুধু পড়ার অনুমতি লাগে।</p>
@if(session('ok'))<div class="box ok">{{ session('ok') }}</div>@endif
@if(session('err'))<div class="box err">{{ session('err') }}</div>@endif
@if($errors->any())<div class="box err">{{ $errors->first() }}</div>@endif

<div class="box">
  <b>বর্তমান অবস্থা:</b>
  @if(!$email) চাবি বসানো হয়নি।
  @elseif(($g['state'] ?? '') === 'ok') সংযুক্ত ✔ · শেষ ৭ দিন: {{ number_format($g['clicks']) }} ক্লিক, {{ number_format($g['impressions']) }} ইমপ্রেশন, CTR {{ $g['ctr'] }}%, গড় অবস্থান {{ $g['position'] }}
  @else চাবি আছে, কিন্তু তথ্য আসছে না: {{ $g['msg'] ?? '' }}
  @endif
  @if($email)<div style="margin-top:8px">সার্ভিস অ্যাকাউন্ট: <code>{{ $email }}</code> (এই ইমেইলটাই Search Console-এ ইউজার হিসেবে যোগ করতে হবে)</div>@endif
</div>

<div class="box">
  <b>যেভাবে করবেন (একবারই)</b>
  <ol>
    <li><a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noopener">Google Cloud Console</a>-এ একটা প্রজেক্ট বেছে "Google Search Console API" চালু (Enable) করুন।</li>
    <li><a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank" rel="noopener">Service Accounts</a> পেজে নতুন সার্ভিস অ্যাকাউন্ট বানান (নাম যেমন: dutyfreebd-seo)। কোনো রোল দিতে হবে না।</li>
    <li>ওই অ্যাকাউন্টে ঢুকে Keys → Add key → Create new key → JSON। একটা ফাইল ডাউনলোড হবে।</li>
    <li>ফাইলটা নোটপ্যাডে খুলে পুরো লেখা নিচের ঘরে পেস্ট করে "সংরক্ষণ ও পরীক্ষা" চাপুন।</li>
    <li><a href="https://search.google.com/search-console/users?resource_id={{ urlencode($property) }}" target="_blank" rel="noopener">Search Console → Settings → Users and permissions</a>-এ ওপরের সার্ভিস অ্যাকাউন্টের ইমেইলটা "Restricted" অনুমতিতে যোগ করুন, তারপর এখানে "আবার পরীক্ষা" চাপুন।</li>
  </ol>
</div>

<form method="post" action="/admin/live-dashboard/google" class="box" autocomplete="off">
  @csrf
  <label for="property">Search Console প্রপার্টি</label>
  <input id="property" name="property" value="{{ $property }}">
  <label for="key">সার্ভিস অ্যাকাউন্টের JSON চাবি (সংরক্ষণের পর আর দেখানো হয় না; খালি রাখলে আগেরটাই থাকে)</label>
  <textarea id="key" name="key" placeholder='{ "type": "service_account", ... }' spellcheck="false"></textarea>
  <button name="act" value="save">সংরক্ষণ ও পরীক্ষা</button>
  <button class="g" name="act" value="test">আবার পরীক্ষা</button>
  @if($email)<button class="g" name="act" value="remove">চাবি মুছুন</button>@endif
</form>
</div></body></html>
