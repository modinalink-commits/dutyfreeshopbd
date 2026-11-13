<?php
namespace App\Http\Controllers;

use App\Support\DeployHealth;
use Illuminate\Support\Facades\DB;

/** JSON feed for the admin bell: open alerts (red badge) and the ones already seen (still active, no badge). */
class AdminAlertController extends Controller
{
  public function feed() {
    abort_unless(in_array(optional(auth()->user())->role, ['super', 'admin'], true), 403);
    $rows = DeployHealth::active(); $open = array_values(array_filter($rows, fn($r) => $r['status'] === 'open'));
    $items = array_map(fn($r) => $r + self::bn($r), array_slice($rows, 0, 15));
    return response()->json(['count' => count($open), 'items' => $items, 'at' => time()])->header('Cache-Control', 'no-store');
  }

  /** Bengali description/action for the popup. Title and module stay English. Numbers are taken from the English description; unknown alerts fall back to the English text. */
  private static function bn(array $r): array {
    $fp = (string)($r['fp'] ?? ''); preg_match_all('/\d+/', (string)$r['description'], $m); $n = $m[0] ?? [];
    $d = fn($i) => strtr((string)($n[$i] ?? ''), ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
    if ($fp === 'relay_live_stale') return ['description_bn' => 'গত ' . $d(0) . ' মিনিট ধরে রিলে থেকে লাইভ কলের কোনো তথ্য আসছে না। কল ও বিলিং সম্ভবত চলছে, শুধু Call Clients-এর লাইভ বোর্ড বন্ধ।', 'action_bn' => 'কল কম চলার সময় Hostinger Docker Manager থেকে sip-relay প্রজেক্ট রিস্টার্ট করুন (রিস্টার্টে চলমান কল কেটে যায়)।'];
    if ($fp === 'orders_drop') return ['description_bn' => 'এই সময়ে সাধারণত প্রায় ' . $d(0) . 'টি অর্ডার আসে, কিন্তু গত ৩ ঘণ্টায় একটিও আসেনি।', 'action_bn' => 'একটি টেস্ট অর্ডার দিন, সাইট ও বিজ্ঞাপন ক্যাম্পেইন দেখুন এবং Deployment Center > Issues খুলুন।'];
    if ($fp === 'sms_failing') return ['description_bn' => 'গত ৩০ মিনিটে ' . $d(0) . 'টি SMS পাঠানো ব্যর্থ হয়েছে।', 'action_bn' => 'SMS সেটিংস ও লগ খুলে প্রোভাইডারের ব্যালেন্স এবং ক্রেডেনশিয়াল দেখুন।'];
    if ($fp === 'wa_failing') return ['description_bn' => 'গত ৩০ মিনিটে ' . $d(0) . 'টি WhatsApp মেসেজ পাঠানো ব্যর্থ হয়েছে।', 'action_bn' => 'WhatsApp ইনবক্স/সেটিংসে কানেকশন এবং শেষ এরর দেখুন।'];
    if ($fp === 'courier_sync_stale') return ['description_bn' => $d(0) . 'টি পার্সেল শিপমেন্টে আছে, কিন্তু কুরিয়ারের স্ট্যাটাস আপডেট বন্ধ হয়ে আছে।', 'action_bn' => 'কুরিয়ার সেটিংসে API কানেকশন দেখুন, তারপর ম্যানুয়াল কুরিয়ার সিঙ্ক চালান।'];
    if ($fp === 'courier_unmapped') return ['description_bn' => $d(0) . 'টি অর্ডারে কুরিয়ারের এমন একটি স্ট্যাটাস এসেছে যা সিস্টেমে মেলানো নেই।', 'action_bn' => 'কুরিয়ার স্ট্যাটাস ম্যাপে নতুন স্ট্যাটাসটি যোগ করুন।'];
    if ($fp === 'capi_failing') return ['description_bn' => 'গত ১ ঘণ্টায় Meta-তে পাঠানো সার্ভার ইভেন্টের ' . $d(0) . 'টির মধ্যে ' . $d(1) . 'টি ব্যর্থ হয়েছে (CAPI)। ফলে বিজ্ঞাপনে পারচেজ ডেটা হারাচ্ছে।', 'action_bn' => 'Pixel সেটিংসে নতুন Conversions API টোকেন দিয়ে একটি টেস্ট ইভেন্ট পাঠান।'];
    if ($fp === 'payments_unverified') return ['description_bn' => $d(0) . 'টি পেমেন্ট ২৪ ঘণ্টার বেশি সময় ধরে যাচাইয়ের অপেক্ষায় আছে।', 'action_bn' => 'পেন্ডিং পেমেন্ট তালিকা খুলে ভেরিফাই বা রিজেক্ট করুন।'];
    if ($fp === 'deploy_stuck') return ['description_bn' => 'একটি রিলিজ ১৫ মিনিটের বেশি সময় ধরে আটকে আছে।', 'action_bn' => 'Deployment Center-এ পেন্ডিং রিলিজ বাতিল করে Quick Deploy চালান; তবু আটকে থাকলে Docker Manager থেকে motherplatform প্রজেক্ট রিস্টার্ট করুন।'];
    if ($fp === 'deploy_worker_dead') return ['description_bn' => 'ব্যাকগ্রাউন্ড ডিপ্লয় ওয়ার্কার ৫ মিনিটের বেশি সাড়া দিচ্ছে না এবং কোনো রিলিজ চলছে না।', 'action_bn' => 'অ্যালার্ট থেকে গেলে Docker Manager থেকে motherplatform প্রজেক্ট রিস্টার্ট করুন।'];
    if (str_starts_with($fp, 'deploy_failed')) return ['description_bn' => 'একটি ডিপ্লয়মেন্ট ব্যর্থ বা মাঝপথে বন্ধ হয়েছে। সাইট শেষ সুস্থ রিলিজেই চলছে।', 'action_bn' => 'লগ দেখে কারণ ঠিক করুন, তারপর আবার ডিপ্লয় করুন।'];
    return ['description_bn' => null, 'action_bn' => null];
  }
}
