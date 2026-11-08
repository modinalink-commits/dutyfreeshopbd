<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Safety net: any customer message that sounds like "I want to order" becomes either a real order (AI flow) or an Order Request lead, so no buyer is lost. */
class OrderIntent {
  /** Words/phrases that mean "I want to buy / please send" in Bangla, Banglish and English. */
  const WORDS = [
    'অর্ডার', 'অর্ডার করব', 'অর্ডার করতে চাই', 'অর্ডার দিব', 'অর্ডার দিতে চাই', 'অর্ডার করে দিন', 'অর্ডার করে দেন', 'অর্ডার কনফার্ম', 'অর্ডার নিন', 'অর্ডার নিবেন',
    'নিব', 'নিবো', 'নিমু', 'নেব', 'নেবো', 'নিতে চাই', 'কিনব', 'কিনবো', 'কিনতে চাই', 'কিনে নিব', 'কিনে দিন', 'চাই', 'লাগবে', 'লাগবো', 'দরকার', 'দিন ভাই', 'দেন ভাই', 'দিয়েন', 'দিবেন',
    'দিয়ে দিন', 'দিয়ে দেন', 'পাঠিয়ে দিন', 'পাঠিয়ে দেন', 'পাঠান', 'পাঠাবেন', 'পাঠায় দেন', 'পাঠায় দিন', 'ডেলিভারি দিন', 'ডেলিভারি দেন', 'হোম ডেলিভারি', 'কার্টন', 'পিস', 'একটা দিন', 'দুইটা দিন',
    'ক্যাশ অন ডেলিভারি', 'কনফার্ম করুন', 'কনফার্ম করে দিন', 'বুক করুন', 'বুক করে দিন', 'রেডি করুন', 'প্যাক করুন', 'আজই চাই', 'এখনই চাই', 'ঠিকানা দিচ্ছি', 'ঠিকানায় পাঠান', 'আমার ঠিকানা',
    'order', 'order kori', 'order korbo', 'order korte', 'order kore den', 'order kore din', 'order dibo', 'order dite', 'order confirm', 'confirm order', 'place order', 'place an order', 'new order',
    'nibo', 'nebo', 'niboo', 'nimu', 'niye nibo', 'kinbo', 'kinte chai', 'kinte pari', 'chai', 'lagbe', 'lagbo', 'dorkar', 'den vai', 'din vai', 'dien', 'diben', 'diye din', 'diye den',
    'pathan', 'pathaben', 'pathay den', 'pathay din', 'pathiye din', 'pathiye den', 'send kore den', 'send kore din', 'send me', 'delivery din', 'delivery den', 'home delivery', 'cod', 'cash on delivery',
    'carton', 'caron', 'carten', 'pcs', 'piece', 'dozen', 'confirm kore', 'confirm korun', 'book kore', 'ready korun', 'pack korun', 'ajke chai', 'ekhoni chai', 'ei product', 'eta nibo', 'eta chai', 'eta din',
    'i want', 'i need', 'i would like', "i'll take", 'i will take', 'i want to buy', 'want to order', 'want to buy', 'need 1', 'need 2', 'need 3', 'buy', 'purchase', 'please send', 'send it', 'deliver it', 'ship it', 'take 1', 'take 2', 'give me',
  ];
  /** Messages about an order that already exists are not new-order requests. */
  const NOT = ['status', 'স্ট্যাটাস', 'cancel', 'বাতিল', 'ক্যান্সেল', 'track', 'ট্র্যাক', 'কোথায়', 'kothay', 'kobe pabo', 'কবে পাব', 'কবে পাবো', 'refund', 'ফেরত', 'return', 'complain', 'অভিযোগ', 'পৌঁছায়নি', 'pouchay', 'পাইনি', 'pai ni', 'invoice dekhan'];
  public static function detect(string $body): ?string {
    $b = mb_strtolower(trim($body)); if ($b === '' || mb_strlen($b) > 600) return null;
    foreach (self::NOT as $n) if (mb_strpos($b, $n) !== false) return null;
    foreach (self::WORDS as $w) if (mb_strpos($b, $w) !== false) return $w;
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
    try { DB::table('chat_order_requests')->where('thread_id', $threadId)->where('status', 'review')->where('cart', '[]')->update(['status'=>'created', 'order_id'=>$orderId, 'invoice'=>$invoice, 'handled_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) { report($e); }
  }
}
