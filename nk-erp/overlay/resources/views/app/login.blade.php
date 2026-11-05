@extends('app.layout', ['noNav' => true])
@section('title', 'লগইন')
@section('content')
<div style="display:flex;justify-content:flex-end;gap:6px"><span class="pill" style="background:#123a86;color:#fff;padding:6px 12px">বাংলা</span><span class="pill" style="border:1px solid #7b8aa8;padding:6px 12px" title="English coming soon">English</span></div>
<div style="display:flex;flex-direction:column;align-items:center;gap:8px;margin-top:24px">
  <img src="{{ \App\Models\Setting::logo() }}" alt="NK Corporation logo" style="width:84px;height:84px;border-radius:20px">
  <b style="font-size:var(--fs-22)">{{ \App\Models\Setting::get('company_name', 'NK Corporation') }}</b>
  <span style="color:#51607a">সেলসম্যান অ্যাপ · {{ \App\Models\Setting::get('brand', 'Aygaz United') }} LPG</span>
</div>
<form method="post" action="{{ route('app.login.post') }}" style="display:flex;flex-direction:column;gap:12px;margin-top:16px">@csrf
  <label class="lb" style="display:flex;flex-direction:column;gap:6px">মোবাইল নম্বর / ইউজারনেম<input class="inp" name="login" inputmode="numeric" autocomplete="username" required value="{{ old('login') }}"></label>
  <label class="lb" style="display:flex;flex-direction:column;gap:6px">পাসওয়ার্ড<input class="inp" type="password" name="password" autocomplete="current-password" required></label>
  <button class="big" type="submit" style="background:#123a86">লগইন</button>
  <span style="text-align:center;font-size:var(--fs-13);color:#51607a">পাসওয়ার্ড ভুলে গেলে অফিসে যোগাযোগ করুন</span>
</form>
<div class="card" style="background:#f4f7fc;border:0"><b style="font-size:var(--fs-14)">লগইনের পর যা দেখবেন</b><br><span style="font-size:var(--fs-13);color:#51607a">শুধু আপনার আজকের ট্রিপ, গাড়ি ও রুট। অন্য সেলসম্যানের তথ্য দেখা যাবে না।</span></div>
<p style="text-align:center;font-size:var(--fs-12);color:#51607a;margin-top:auto">{{ \App\Models\Setting::get('phone') }} · {{ \App\Models\Setting::get('area', 'Jorarganj') }}, {{ \App\Models\Setting::get('upazila', 'Mirsarai') }}</p>
@endsection
