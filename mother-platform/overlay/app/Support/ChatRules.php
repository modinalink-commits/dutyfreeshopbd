<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hand-off and follow-up rules (owner approval 27 Nov 2026, from the "Handoff and Follow-up Preview").
 *  1. A customer who asks for a person, or reports a refund / cancel / payment / wrong-product problem, goes to the hand-off queue at once
 *     (server-side words check, so it works even when the AI is slow or off). The AI stops, nothing else is automated for that chat.
 *  2. A customer who showed order interest, was asked "shall I place the order?" and stayed silent gets the "ফলোআপ দরকার" mark.
 *     The mark is only a mark: no message and no SMS is sent because of it. A customer reply removes it (Chat::ingest).
 *  3. When a representative takes the chat, the automatic mark is removed at once (ChatOwner::claim). A mark set by a person is never removed here.
 *  4. An automatic mark nobody picked up for 24 hours closes the chat as "Closed - No Response" (chat status only; an order is never touched).
 */
class ChatRules {
  const MARK = 'ফলোআপ দরকার'; // every automatic mark starts with this text; marks written by staff never do

  /** [reason, priority] or null. Priority values are the ones the chat module already uses: normal | high | urgent. */
  public static function detect(string $t): ?array {
    $t = trim($t); if ($t === '' || mb_strlen($t) > 600) return null;
    if (preg_match('/refund|রিফান্ড|ফেরত\s*(চাই|দিন|দেন|দাও)|taka\s*ferot|টাকা\s*ফেরত/iu', $t)) return ['Refund request', 'high'];
    if (preg_match('/টাকা\s*কেটে|টাকা\s*(দিয়েছি|পাঠিয়েছি|পাঠিয়ে\s*দিয়েছি)|taka\s*kete|taka\s*(di(ye|ee)|path(ai|iye))\s*(chi|ci|se|che)|paid\s+but|payment\s+(failed|problem|hoyni|hoy\s*ni)|পেমেন্ট\s*(হয়নি|হয় নি|ফেইল|সমস্যা)/iu', $t)) return ['Payment problem', 'high'];
    if (preg_match('/\bcancel\b|বাতিল|ক্যান্সেল|ক্যানসেল/iu', $t)) return ['Cancellation request', 'high'];
    if (preg_match('/wrong\s+(product|item)|ভুল\s*(পণ্য|প্রোডাক্ট|জিনিস)|vul\s*product/iu', $t)) return ['Wrong product', 'high'];
    if (self::asksForCall($t)) return ['Customer asked for a phone call', 'normal'];
    if (preg_match('/\b(agent|human|real\s+person|representative|support\s+team|manager)\b|এজেন্ট|মানুষের?\s*সাথে|কারো\s*সাথে\s*কথা|কাস্টমার\s*কেয়ার|প্রতিনিধি|agent\s*er\s*shathe|manush|kotha\s*bolte\s*chai|\bag[ae]n[td]\b|\bag[ae]nt\w*\s+\w*\s*kotha/iu', $t) && self::asksForPerson($t)) return ['Customer asked for an agent', 'normal'];
    if (preg_match('/\burgent\b|জরুরি|আজই\s*লাগবে|delivery.{0,25}(late|দেরি)|দেরি.{0,20}(ডেলিভারি|delivery)/iu', $t) && !preg_match('/(urgent|জরুরি)\s*(না|নয়|নাই|নেই|nai|nei|noy)(?![\p{L}])/iu', $t)) return ['Urgent delivery issue', 'normal'];
    return null;
  }


  /** 28 Nov 2026 (owner, from a live chat): "একটু ফোন দিতেন", "কথা বলতে চাচ্ছিলাম", "call me" is a request for a person, not something the AI can do. Polite requests only: "ফোন দিবেন না" (do not call) and "আমি কল করব" (I will call) do not count. */
  public static function asksForCall(string $t): bool {
    $t = trim($t); if ($t === '') return false;
    if (preg_match('/\b(call\s+me|give\s+me\s+a\s+call|phone\s+me|ring\s+me|call\s*back|callback|please\s+call|plz\s+call|pls\s+call)\b/iu', $t) && !preg_match('/(don\'?t|do\s+not|no)\s+call/iu', $t)) return true;
    if (preg_match('/(ফোন|ফোনে|কল|কলব্যাক|কল\s*ব্যাক)\s*(একটু\s*|কষ্ট\s*করে\s*)?(দিন|দিতেন|দেন|দেবেন|দিবেন|দিয়েন|দিলে|করুন|করবেন|করেন|করতেন|করলে)(?!\s*(না|নাই|নেই|\x{09A8}\x{09BE}))/u', $t)) return true;
    if (preg_match('/কথা\s*(একটু\s*)?(বলতে\s*(চাই|চাচ্ছি|চাচ্ছিলাম|চাইছি|চাইছিলাম|চাইতেছি|চেয়েছিলাম|পারি|পারবো|পারব)|বলব|বলবো|বলার\s*জন্য)/u', $t)) return true;
    if (preg_match('/\b(fon|phone|call|kol)\s*(dien|dian|den|diben|dite|korun|korben|koren|koiren|kore\s*den)\b/iu', $t) && !preg_match('/\b(den|diben|dien)\s*na\b/iu', $t)) return true;
    return (bool)preg_match('/\bkotha\s*bol(te|bo|ben)\b/iu', $t);
  }
  /** The customer is answering a message about an order: our last message (within 24 hours) was an automatic order message, an invoice, or a person's message about the order, and the customer's words are about the order. */
  public static function repliesToOrderMessage(int $tid, string $body): bool {
    try {
      if (!preg_match('/অর্ডার|order|কনফার্ম|confirm|ডেলিভারি|delivery|ঠিকানা|address|পরিবর্তন|change|পাঠাবেন|পাঠিয়ে|পাঠান/iu', $body)) return false;
      $o = DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'out')->where('status', '<>', 'failed')->orderByDesc('id')->first(['body', 'kind', 'user_name', 'is_ai', 'created_at']);
      if (!$o || $o->created_at < now()->subHours(24)->toDateTimeString()) return false;
      if (!empty($o->is_ai)) return false;
      $by = (string)($o->user_name ?? '');
      if (str_ends_with($by, '(auto)') || $o->kind === 'invoice') return true;
      return (bool)preg_match('/অর্ডার|order|invoice|ইনভয়েস/iu', (string)$o->body);
    } catch (\Throwable $e) { return false; }
  }
  /** The last few messages, so the person who takes the hand-off sees what was said before without opening anything first. */
  public static function context(int $tid, int $n = 6): string {
    try {
      $rows = DB::table('chat_messages')->where('thread_id', $tid)->orderByDesc('id')->limit($n)->get(['dir', 'body', 'user_name', 'is_ai'])->reverse();
      return $rows->map(fn($m) => ($m->dir === 'in' ? 'Customer' : (!empty($m->is_ai) ? 'AI' : (str_ends_with((string)$m->user_name, '(auto)') ? 'Auto' : 'Team'))).': '.mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$m->body)), 0, 140))->implode(' | ');
    } catch (\Throwable $e) { return ''; }
  }
  /** The one line the customer gets after a hand-off made by the rules. A phone request is never promised a time. */
  public static function ackText(object $t): string {
    return ($t->handoff_reason ?? '') === 'Customer asked for a phone call'
      ? 'আপনার ফোনের অনুরোধটি আমাদের সাপোর্ট টিমে পাঠিয়েছি। একজন প্রতিনিধি আপনার সঙ্গে যোগাযোগ করবেন।'
      : self::ACK;
  }

  /** 28 Nov 2026: an ordinary sentence that merely contains "manager" / "agent" / "মানুষ" is not a request for a person. It counts when the customer asks for one (a request word is near) or the message is only 1-3 words ("agent", "human please"). A self-description ("I am a manager", "travel agent") without a request word does not. */
  public static function asksForPerson(string $t): bool {
    $t = trim($t); if ($t === '') return false;
    if (count(preg_split('/\s+/u', $t)) <= 3) return true;
    $req = '/(talk|speak|chat|connect|contact|transfer|want|need|please|plz|pls|call me|কথা|বলতে|বলব|বলবো|চাই|লাগবে|দরকার|দিন|দেন|দাও|দিবেন|যোগাযোগ|পাঠান|kotha|bolte|bolbo|bolben|chai|lagbe|dorkar|den\b|dao\b|dian|diben|jogajog|pathan)/iu';
    $strong = '/(talk|speak|chat|connect|transfer|call me|কথা|বলতে|বলব|বলবো|যোগাযোগ|kotha|bolte|bolbo|bolben|jogajog)/iu';
    if (preg_match('/\b(i\s*am|i\'m|im|my|amar|ami)\b.{0,25}\b(manager|agent)\b|(travel|insurance|sales|booking|property)\s+agent|আমি.{0,25}(ম্যানেজার|এজেন্ট)|আমার\s*(ম্যানেজার|এজেন্ট)/iu', $t)) return (bool)preg_match($strong, $t); // describing himself, not asking for a person
    return (bool)preg_match($req, $t);
  }

  public static function on(): bool { return Sms::secret('chat_handoff_rules') !== '0'; }

  /** Called from Chat::ingest for every new customer message. Creates the hand-off when the words say so. Never throws. */
  public static function onCustomer(object $t, string $body): void {
    try {
      if (!self::on() || empty($t->id) || ($t->channel ?? '') === 'sms') return;
      if (!empty($t->handoff_at) || ($t->assigned_to ?? null) !== null) return; // already with a person / in the queue
      $d = self::detect($body);
      if (!$d && self::repliesToOrderMessage((int)$t->id, $body)) $d = ['Reply to an order message', 'normal'];
      if (!$d) return;
      if (!Schema::hasTable('chat_handoffs')) return;
      $tid = (int)$t->id;
      if (DB::table('chat_handoffs')->where('thread_id', $tid)->whereIn('status', ['open', 'accepted'])->exists()) return;
      [$reason, $pri] = $d;
      $sum = 'Customer wrote: '.mb_substr(trim($body), 0, 300); $ctx = self::context($tid); if ($ctx !== '') $sum .= "\nEarlier in this chat: ".$ctx;
      DB::table('chat_handoffs')->insert(['thread_id'=>$tid, 'assigned_to'=>null, 'reason'=>$reason, 'summary'=>$sum, 'priority'=>$pri, 'status'=>'open', 'created_at'=>now(), 'updated_at'=>now()]);
      DB::table('chat_threads')->where('id', $tid)->update(['needs_human'=>true, 'handoff_at'=>now(), 'handoff_reason'=>$reason, 'priority'=>$pri, 'ai_paused'=>true, 'updated_at'=>now()] + Chat::fuClear());
      Cache::put('ai.hoack.'.$tid, 1, 600); // the AI worker sends the one short "sent to the support team" line
      try { AiMemory::event($tid, null, 'system', 'handoff', null, ['reason'=>$reason, 'priority'=>$pri, 'by'=>'rules']); } catch (\Throwable $e) {}
      try { DB::table('audit_logs')->insert(['user_id'=>null, 'user_name'=>'System (rules)', 'action'=>'chat.handoff', 'subject'=>'Conversation #'.$tid.' sent to the agent queue: '.$reason.' ('.$pri.')', 'ip'=>null, 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    } catch (\Throwable $e) { report($e); }
  }
  /** True once per hand-off made by the rules: the AI worker should send the fixed line. */
  public static function ackDue(object $t): bool {
    try { return !empty($t->handoff_at) && Cache::pull('ai.hoack.'.$t->id) !== null; } catch (\Throwable $e) { return false; }
  }
  public const ACK = 'আপনার অনুরোধটি আমাদের সাপোর্ট টিমে পাঠিয়েছি। একজন প্রতিনিধি এই চ্যাটেই উত্তর দেবেন।';

  /** The follow-up mark was written by this system (not by staff). */
  public static function isAuto(?string $note): bool { return $note !== null && str_starts_with($note, self::MARK); }

  /** The conversation was taken by a person: remove an automatic follow-up mark (a staff-written one stays). Returns true when something was removed. */
  public static function onClaim(int $tid): bool {
    try {
      if (!Chat::fuReady()) return false;
      $n = DB::table('chat_threads')->where('id', $tid)->whereNotNull('follow_up_at')->where('follow_up_note', 'like', self::MARK.'%')->update(Chat::fuClear() + ['updated_at'=>now()]);
      if ($n) { try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name ?? '', 'action'=>'chat.follow_up_clear', 'subject'=>'Conversation #'.$tid.': automatic follow-up cancelled because a representative took the chat', 'ip'=>null, 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {} }
      return (bool)$n;
    } catch (\Throwable $e) { report($e); return false; }
  }

  /** Our last message asks the customer to confirm the order. */
  public static function asksConfirm(string $b): bool {
    if (preg_match('/কার্ট\s*(এখন\s*)?খালি|cart\s+is\s+empty|কী\s*কনফার্ম|কোন\s*পণ্য|নামটি\s*বা\s*ছবি/iu', $b)) return false; // a clarifying question, not the order question
    return (bool)preg_match('/অর্ডার(টা|টি)?\s*(কি\s*)?(করে\s*)?(দেব|দিব|করব|করবো)\s*\?|অর্ডার\s*নিশ্চিত\s*করতে\s*["\x{201C}]?হ্যাঁ|["\x{201C}]?হ্যাঁ["\x{201D}]?\s*লিখুন|ঠিকানাটা\s*পাঠালেই|shall\s+i\s+(place|confirm)|order\s*(ta\s*)?(kore|korbo)\s*(di|den|dibo)/iu', $b);
  }
  /** The customer already has an order: a confirmed order is not a silent order question. */
  public static function hasRecentOrder(?string $phone): bool {
    $p = preg_replace('/\D/', '', (string)$phone); if (strlen($p) < 10) return false; $p = substr($p, -10);
    try { return DB::table('orders')->where('phone', 'like', '%'.$p)->where('created_at', '>=', now()->subHours(48))->exists(); } catch (\Throwable $e) { return false; }
  }
  /** Remove an automatic mark that no longer holds (the question was a clarification, or the customer now has an order). Staff-written marks are never touched. */
  public static function revalidate(): int {
    $n = 0;
    $rows = DB::table('chat_threads')->where('status', '!=', 'resolved')->whereNotNull('follow_up_at')->where('follow_up_note', 'like', self::MARK.'%')->whereNull('follow_up_by')->limit(200)->get();
    foreach ($rows as $t) {
      try {
        $last = DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'out')->orderByDesc('id')->first(['body']);
        $bad = !$last || !self::asksConfirm((string)$last->body) || self::hasRecentOrder($t->phone ?? null);
        if (!$bad) continue;
        if (DB::table('chat_threads')->where('id', $t->id)->where('follow_up_note', 'like', self::MARK.'%')->update(Chat::fuClear() + ['updated_at'=>now()])) $n++;
      } catch (\Throwable $e) { report($e); }
    }
    return $n;
  }

  /** Worker tick (every 5 minutes): put the mark on silent order chats, and close the ones nobody picked up for 24 hours. */
  public static function tick(): void {
    if (!Chat::fuReady() || !Cache::add('chat.rules.tick', 1, 280)) return;
    try { self::revalidate(); } catch (\Throwable $e) { report($e); }
    try { self::mark(); } catch (\Throwable $e) { report($e); }
    try { self::closeNoResponse(); } catch (\Throwable $e) { report($e); }
  }
  public static function markMin(): int { $v = (int)(Sms::secret('chat_fu_auto_min') ?: 45); return max(10, min(1440, $v)); }
  public static function closeHours(): int { $v = Sms::secret('chat_fu_close_hours'); return $v === null || $v === '' ? 24 : max(0, min(240, (int)$v)); }

  /** Returns how many chats got the mark. $dry only counts. */
  public static function mark(bool $dry = false): int {
    $min = self::markMin(); $n = 0;
    $rows = DB::table('chat_threads')->whereIn('channel', ['web', 'whatsapp', 'facebook', 'instagram'])->whereIn('status', ['new', 'open', 'waiting'])->where('last_dir', 'out')
      ->whereNull('follow_up_at')->whereNull('assigned_to')->where(fn($w) => $w->whereNull('needs_human')->orWhere('needs_human', false))->whereNull('handoff_at')
      ->where('last_at', '<=', now()->subMinutes($min))->where('last_at', '>=', now()->subHours(72))->orderBy('last_at')->limit(200)->get();
    foreach ($rows as $t) {
      try {
        $tid = (int)$t->id;
        $interest = (string)($t->intent ?? '') === 'order_request';
        try { if (!$interest && Schema::hasTable('chat_order_requests') && DB::table('chat_order_requests')->where('thread_id', $tid)->where('status', 'review')->exists()) $interest = true; } catch (\Throwable $e) {}
        if (!$interest) continue;
        $last = DB::table('chat_messages')->where('thread_id', $tid)->orderByDesc('id')->first(['dir', 'body', 'status', 'kind']);
        if (!$last || $last->dir !== 'out' || $last->status === 'failed' || !self::asksConfirm((string)$last->body)) continue;
        if (DB::table('chat_handoffs')->where('thread_id', $tid)->whereIn('status', ['open', 'accepted'])->exists() || self::hasRecentOrder($t->phone ?? null)) continue;
        $n++; if ($dry) continue;
        $u = DB::table('chat_threads')->where('id', $tid)->whereNull('follow_up_at')->whereNull('assigned_to')->where('last_dir', 'out')
          ->update(['follow_up_at'=>now(), 'follow_up_note'=>self::MARK.': অর্ডার নিশ্চিত করার প্রশ্নে গ্রাহক সাড়া দেননি', 'follow_up_by'=>null, 'follow_up_set_at'=>now(), 'updated_at'=>now()]);
        if ($u) { try { DB::table('audit_logs')->insert(['user_id'=>null, 'user_name'=>'System (rules)', 'action'=>'chat.follow_up', 'subject'=>'Conversation #'.$tid.' marked "'.self::MARK.'" (order question unanswered for '.$min.' min). Nothing was sent to the customer.', 'ip'=>null, 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {} } else $n--;
      } catch (\Throwable $e) { report($e); }
    }
    return $n;
  }

  /** An automatic mark that nobody picked up and the customer never answered: Closed - No Response. Chat status only; no message is sent and no order is touched. */
  public static function closeNoResponse(bool $dry = false): int {
    $h = self::closeHours(); if ($h < 1) return 0; $n = 0;
    $rows = DB::table('chat_threads')->where('status', '!=', 'resolved')->whereNotNull('follow_up_at')->where('follow_up_note', 'like', self::MARK.'%')->whereNull('follow_up_by')
      ->where('follow_up_set_at', '<=', now()->subHours($h))->whereNull('assigned_to')->limit(100)->get();
    foreach ($rows as $t) {
      try {
        $tid = (int)$t->id;
        if ($t->last_dir !== 'out' || !empty($t->awaiting_reply) || !empty($t->needs_human) || !empty($t->handoff_at)) continue; // the customer wrote, or a person is needed: not for this rule
        if (DB::table('chat_handoffs')->where('thread_id', $tid)->whereIn('status', ['open', 'accepted'])->exists()) continue;
        $n++; if ($dry) continue;
        DB::table('chat_threads')->where('id', $tid)->update(['status'=>'resolved', 'unread'=>0, 'awaiting_reply'=>false, 'updated_at'=>now()] + Chat::fuClear());
        try { ChatOwner::system($tid, null, 'release', 'Closed - No Response: no answer for '.$h.' hours after the follow-up mark.'); } catch (\Throwable $e) {}
        try { DB::table('audit_logs')->insert(['user_id'=>null, 'user_name'=>'System (rules)', 'action'=>'chat.close', 'subject'=>'Conversation #'.$tid.' closed: Closed - No Response ('.$h.' h after the follow-up mark). Any order is untouched.', 'ip'=>null, 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
      } catch (\Throwable $e) { report($e); }
    }
    return $n;
  }
}
