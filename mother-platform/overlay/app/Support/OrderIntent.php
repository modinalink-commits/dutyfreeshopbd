<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Safety net: any customer message that sounds like "I want to order" becomes either a real order (AI flow) or an Order Request lead, so no buyer is lost. */
class OrderIntent {
  /** STRONG: the customer states he wants to order / buy. Counts even when the sentence also asks how. */
  const STRONG = ['অর্ডার করব', 'অর্ডার করবো', 'অর্ডার করতে চাই', 'অর্ডার দিব', 'অর্ডার দিবো', 'অর্ডার দিতে চাই', 'অর্ডার করে দিন', 'অর্ডার করে দেন', 'অর্ডার নিন', 'অর্ডার কনফার্ম', 'অর্ডার করছি', 'অর্ডার করলাম',
    'কিনতে চাই', 'কিনব', 'কিনবো', 'কিনে নিব', 'কিনে দিন', 'নিতে চাই', 'কিনে দেন',
    'order korbo', 'order korte chai', 'order dibo', 'order dite chai', 'order kore den', 'order kore din', 'order confirm', 'confirm order', 'place order', 'place an order', 'new order dibo',
    'kinte chai', 'kinbo', 'niye nibo', 'send kore den', 'send kore din', 'want to order', 'want to buy', 'like to order', 'like to buy', "i'll take", 'i will take', 'i want to purchase', 'ready to order'];
  /** COMMIT: short commitment words ("I'll take it", "send it"). Ignored if the message is a question. */
  const COMMIT = ['নিব', 'নিবো', 'নিমু', 'নেব', 'নেবো', 'লাগবে', 'লাগবো', 'দিন ভাই', 'দেন ভাই', 'দিয়ে দিন', 'দিয়ে দেন', 'পাঠিয়ে দিন', 'পাঠিয়ে দেন', 'পাঠান', 'পাঠাবেন', 'পাঠায় দেন', 'পাঠায় দিন', 'কনফার্ম করুন', 'কনফার্ম করে দিন', 'রেডি করুন', 'প্যাক করুন', 'ঠিকানায় পাঠান', 'চাইতেছি', 'চাচ্ছি',
    'nibo', 'nebo', 'niboo', 'nimu', 'lagbe', 'lagbo', 'den vai', 'din vai', 'diye din', 'diye den', 'pathan', 'pathaben', 'pathay den', 'pathay din', 'pathiye din', 'pathiye den', 'send it', 'send me', 'deliver it', 'ship it', 'confirm korun', 'confirm kore din', 'ready korun', 'pack korun', 'please send', 'give me', 'need 1', 'need 2', 'need 3', 'i need a carton', 'i want a carton'];
  /** A number with a unit ("2 carton", "৩ কার্টন", "5 pcs") is an order line. */
  const QTY = '/(\d+|[০-৯]+|এক|দুই|তিন|চার|পাঁচ)\s*(carton|ctn|cartoon|caron|carten|pcs|pc|piece|pis|dozen|কার্টন|কাটন|কার্টুন|পিস|ডজন)/iu';
  const QUESTION = ['?', '？', 'কিভাবে', 'কীভাবে', 'কি ভাবে', 'কবে', 'কেন', 'করা যাবে', 'করা যায়', 'jabe', 'jay ', 'how ', 'can i', 'can you', 'do you', 'কত', 'koto', 'দাম', 'price', 'আছে কি', 'ache ki', 'আছে?', 'জানতে', 'jante', 'sales rep', 'representative', 'help', 'সাহায্য'];
  /** Messages about an order that already exists are not new-order requests. */
  const NOT = ['status', 'স্ট্যাটাস', 'cancel', 'বাতিল', 'ক্যান্সেল', 'track', 'ট্র্যাক', 'কোথায়', 'kothay', 'kobe pabo', 'কবে পাব', 'কবে পাবো', 'refund', 'ফেরত', 'return', 'complain', 'অভিযোগ', 'পৌঁছায়নি', 'pouchay', 'পাইনি', 'pai ni', 'invoice', 'order number', 'অর্ডার নম্বর', 'অর্ডার ডেলিভারি', 'order delivery', 'chilo', 'ছিল'];
  public static function detect(string $body): ?string {
    $b = mb_strtolower(trim($body)); if ($b === '' || mb_strlen($b) > 220) return null;
    foreach (self::NOT as $n) if (mb_strpos($b, $n) !== false) return null;
    foreach (self::STRONG as $w) if (mb_strpos($b, $w) !== false) return $w;
    foreach (self::QUESTION as $q) if (mb_strpos($b, $q) !== false) return null;
    foreach (self::COMMIT as $w) if (mb_strpos($b, $w) !== false) return $w;
    if (preg_match(self::QTY, $b, $m)) return trim($m[0]);
    return null;
  }
  /** Called for every customer message. Makes one open lead per conversation; the AI order flow or staff upgrades/closes it later. */
  public static function onCustomer(object $t, string $body): void {
    try {
      if (!($w = self::detect($body))) return;
      if (DB::table('chat_order_requests')->where('thread_id', $t->id)->where('status', 'review')->exists()) return;
      if (!empty($t->phone) && DB::table('orders')->where('phone', $t->phone)->where('created_at', '>=', now()->subMinutes(30))->exists()) return;
      DB::table('chat_order_requests')->insert(['thread_id'=>$t->id, 'channel'=>$t->channel ?? null, 'name'=>mb_substr((string)($t->name ?: 'Customer'), 0, 160), 'phone'=>mb_substr((string)($t->phone ?? ''), 0, 20), 'address'=>'', 'area'=>1,
        'note'=>mb_substr($body, 0, 300), 'cart'=>'[]', 'items'=>'[]', 'total'=>0, 'status'=>'review', 'reason'=>'Wants to order (word: '.$w.'). Details pending, open the chat.', 'created_at'=>now(), 'updated_at'=>now()]);
    } catch (\Throwable $e) { report($e); }
  }
  /** An order now exists for this conversation (AI or staff): close its open leads. */
  public static function close(int $threadId, ?int $orderId = null, ?string $invoice = null): void {
    try { DB::table('chat_order_requests')->where('thread_id', $threadId)->where('status', 'review')->where('reason', 'like', 'Wants to order (word:%')->update(['status'=>'created', 'order_id'=>$orderId, 'invoice'=>$invoice, 'handled_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) { report($e); }
  }
}
