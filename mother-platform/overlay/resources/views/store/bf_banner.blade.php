<?php $__bfRun = false; try { $__bfPv = \App\Support\BlackFriday::$previewOn; $__bfRun = $__bfPv || (\App\Support\BlackFriday::running() && count(\App\Support\BlackFriday::ids()) > 0); } catch (\Throwable $e) { $__bfPv = false; } $__bfHome = request()->path() === '/' || $__bfPv; $__bfSkip = \App\Support\CleanSite::on() || request()->is('black-friday') || request()->is('checkout*') || request()->is('order/*') || request()->is('invoice*'); ?>
@if($__bfRun && !$__bfSkip)
<?php [$__bfA, $__bfB] = \App\Support\BlackFriday::window(); $__bfLast = now('Asia/Dhaka')->isSameDay($__bfB); ?>
<a class="bfbn {{ $__bfHome ? 'big' : '' }}" href="/black-friday" aria-label="Black Friday on DutyFree Shop: see the offers">
<span class="bfbn-in"><span class="bfbn-t"><b>BLACK FRIDAY</b><i>on DutyFree Shop</i></span>
<span class="bfbn-d">{{ $__bfLast ? 'Cyber Monday · Last day · ends 11:59 PM' : $__bfA->format('j') . ' to ' . $__bfB->format('j F') . ' · ends ' . $__bfB->format('j F') . ', 11:59 PM' }}</span>
<span class="bfbn-c">See the offers</span></span></a>
<style>.bfbn{display:block;background:#0C161F;color:#fff;text-decoration:none;border-left:8px solid #F95505}.bfbn-in{max-width:1280px;margin:0 auto;padding:9px 16px;display:flex;align-items:center;justify-content:center;gap:10px 22px;flex-wrap:wrap;text-align:center}
.bfbn-t{display:inline-flex;align-items:baseline;gap:8px;flex-wrap:wrap;justify-content:center}.bfbn-t b{font-size:17px;font-weight:900;letter-spacing:.04em}.bfbn-t i{font-style:normal;font-size:14px;font-weight:600;color:#D6DEEA}
.bfbn-d{font-size:13px;color:#D6DEEA}.bfbn-c{display:inline-flex;align-items:center;min-height:32px;padding:0 14px;border-radius:999px;background:#F95505;color:#fff;font-size:13px;font-weight:800}
.bfbn.big .bfbn-in{padding:26px 16px;gap:12px 28px}.bfbn.big .bfbn-t b{font-size:clamp(30px,5.2vw,58px);line-height:1}.bfbn.big .bfbn-t i{font-size:clamp(16px,2.2vw,24px);color:#fff}.bfbn.big .bfbn-d{font-size:15px;flex-basis:100%}.bfbn.big .bfbn-c{min-height:44px;padding:0 24px;font-size:15px}
@media(max-width:760px){.bfbn{border-left-width:5px}.bfbn-d{flex-basis:100%;font-size:12px}.bfbn.big .bfbn-in{padding:18px 12px}}</style>
@endif
