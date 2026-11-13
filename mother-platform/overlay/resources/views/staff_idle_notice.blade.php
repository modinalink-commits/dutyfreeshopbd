<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>কারণ দর্শানোর নোটিশ</title>
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F3F4F6;font:15px/1.6 'Bornomala','Inter',system-ui,sans-serif;color:#0B1B33;padding:16px;box-sizing:border-box}
.bx{background:#fff;border:1px solid #E5E7EB;border-top:4px solid #F97316;border-radius:14px;max-width:560px;width:100%;padding:22px 24px;box-shadow:0 8px 30px rgba(15,23,42,.08)}
h1{font-size:19px;margin:0 0 4px}.sub{color:#6B7280;font-size:13px;margin-bottom:14px}
.fact{background:#FFF7ED;border:1px solid #FED7AA;border-radius:10px;padding:10px 12px;margin-bottom:14px}
.fact b{color:#9A3412}
textarea{width:100%;min-height:110px;border:1px solid #D1D5DB;border-radius:10px;padding:10px;font:inherit;box-sizing:border-box;resize:vertical}
textarea:focus{outline:2px solid #FDBA74;border-color:#F97316}
.qk{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 12px}.qk button{border:1px solid #E5E7EB;background:#F9FAFB;border-radius:999px;padding:5px 11px;font:inherit;font-size:13px;cursor:pointer}
.go{background:#F97316;color:#fff;border:0;border-radius:10px;padding:11px 18px;font:inherit;font-weight:700;cursor:pointer;width:100%}
.err{color:#B42318;font-size:13px;margin:6px 0}.ft{display:flex;justify-content:space-between;align-items:center;margin-top:12px;font-size:12.5px;color:#6B7280}
.ft button{background:none;border:0;color:#6B7280;text-decoration:underline;cursor:pointer;font:inherit}
</style></head><body><div class="bx">
<h1>{{ auth()->user()->name }}, আপনার নামে একটি নোটিশ</h1>
<div class="sub">কারণ লিখে জমা দিলে ড্যাশবোর্ড আবার খুলবে। আপনার লেখা কারণ অ্যাডমিনের কাছে যাবে।</div>
<div class="fact">আপনি <b>{{ \Carbon\Carbon::parse($n->idle_from)->timezone('Asia/Dhaka')->format('h:i A') }}</b> থেকে <b>{{ $n->idle_minutes }} মিনিট</b> কোনো কাজ করেননি।
@if($n->waiting_chats + $n->waiting_orders > 0)<br>ওই সময়ে অপেক্ষায় ছিল: @if($n->waiting_chats)<b>{{ $n->waiting_chats }}টা</b> কাস্টমারের মেসেজ @endif @if($n->waiting_orders)<b>{{ $n->waiting_orders }}টা</b> নতুন অর্ডার @endif @endif</div>
<form method="post" action="/admin/staff-idle/submit">@csrf
<label for="rs" style="font-weight:700">এতক্ষণ কাজ না করার কারণ</label>
<div class="qk">@foreach(['নামাজে ছিলাম','খাবারের বিরতিতে ছিলাম','পার্সেল প্যাক করছিলাম','কাস্টমারের সাথে ফোনে ছিলাম','অফিসের অন্য কাজে ছিলাম'] as $q)<button type="button" onclick="var t=document.getElementById('rs');t.value=(t.value?t.value+' ':'')+this.textContent+'। ';t.focus()">{{ $q }}</button>@endforeach</div>
<textarea id="rs" name="reason" maxlength="1000" required placeholder="কোথায় ছিলেন, কী করছিলেন, পরিষ্কার করে লিখুন">{{ old('reason') }}</textarea>
@error('reason')<div class="err">{{ $message }}</div>@enderror
<button class="go" type="submit" style="margin-top:10px">কারণ জমা দিন</button></form>
<div class="ft"><span>নোটিশ নং {{ $n->id }}</span><form method="post" action="/admin/logout">@csrf<button type="submit">লগ আউট</button></form></div>
</div></body></html>
