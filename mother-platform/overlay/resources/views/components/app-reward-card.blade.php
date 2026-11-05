@php($__r = $reward ?? null)
@if($__r)
@php($__st = $__r['status'])
@php($__amt = number_format((float)($__r['amount'] ?? 0)))
<div class="bx arw arw-{{ $__st }}" id="appReward">
  <div class="arw-i">🎁</div>
  <div class="arw-b">
    @if($__st === 'locked')
      <b>৳{{ $__amt }} App Install Reward</b>
      <p>অ্যাপ ইনস্টল করে আপনার মোবাইল নম্বর ভেরিফাই করুন — প্রথম App অর্ডারে ৳{{ $__amt }} ছাড় অটো-অ্যাপ্লাই হবে। কুপন কোড লাগবে না।</p>
      @if(empty($rewardVerified))<small class="arw-n">মোবাইল নম্বর এখনো ভেরিফাই করা নেই — Profile থেকে ভেরিফাই করুন।</small>@endif
      <button type="button" class="arw-btn" onclick="window.DFPWA&&DFPWA.install('auto')">Install App</button>
    @elseif($__st === 'available')
      <b>🎁 ৳{{ $__amt }} App Install Reward</b><span class="arw-tag arw-ok">Available</span>
      <p>Available for your first App Order. অ্যাপ থেকে অর্ডার করলে চেকআউটে অটো-অ্যাপ্লাই হবে।</p>
    @elseif($__st === 'reserved')
      <b>৳{{ $__amt }} App Install Reward</b><span class="arw-tag arw-rs">Reserved</span>
      <p>Invoice {{ $__r['invoice'] }}-এর অর্ডারে যুক্ত আছে। অর্ডার বাতিল হলে আবার Available হবে।</p>
    @else
      <b>৳{{ $__amt }} App Install Reward</b><span class="arw-tag arw-us">Used</span>
      <p>Used on Invoice {{ $__r['invoice'] }}@if(!empty($__r['applied'])) · -৳{{ number_format((float)$__r['applied']) }}@endif</p>
    @endif
  </div>
</div>
<style>.arw{display:flex;gap:14px;align-items:flex-start;background:linear-gradient(135deg,#FFF8F3,#FFEFE2);border:1px solid #FFD9BF;border-radius:16px;padding:14px 16px;margin:0 0 16px}.arw-i{font-size:30px;line-height:1}.arw-b b{font-size:16px;color:#0B1220}.arw-b p{margin:4px 0 0;color:#4B5563;font-size:13.5px}.arw-n{display:block;margin-top:6px;color:#B45309;font-size:12.5px;font-weight:600}.arw-tag{display:inline-block;margin-left:8px;border-radius:999px;padding:2px 10px;font-size:11.5px;font-weight:700;vertical-align:middle}.arw-ok{background:#DCFCE7;color:#166534}.arw-rs{background:#FEF3C7;color:#92400E}.arw-us{background:#E5E7EB;color:#374151}.arw-btn{margin-top:10px;border:0;background:linear-gradient(135deg,#FF7A1A,#FF5A00);color:#fff;font-weight:700;font-size:14px;border-radius:12px;padding:9px 18px;cursor:pointer}.arw-used,.arw-reserved{background:#F8FAFC;border-color:#E2E8F0}</style>
@endif
