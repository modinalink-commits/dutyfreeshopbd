<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recording of WhatsApp calls (owner 16 Nov 2026).
 * How: WhatsApp calls are answered in the representative's browser (WebRTC straight to Meta, no PBX in between), and Meta gives no server-side recording.
 * So the answering browser mixes the customer's audio and the microphone and sends the recording here in small pieces while the call runs; a lost browser still leaves everything up to the last piece.
 * Files live in storage/app/wa_recordings (never public) and are only served through the permission-checked route. Nothing here can end or change a call.
 */
class WaRec {
  const MAX_BYTES = 80 * 1024 * 1024; // about 5 hours at the recording bitrate
  const NOTICE = 'গুণগত মান ও সেবা যাচাইয়ের জন্য এই কলটি রেকর্ড করা হচ্ছে।';
  public static function ready(): bool { static $r = null; if ($r === null) { try { $r = Schema::hasTable('wa_call_recordings') && Schema::hasTable('wa_call_recording_logs'); } catch (\Throwable $e) { $r = false; } } return $r; }
  private static function get(string $k, ?string $def = null): ?string { try { $v = DB::table('settings')->where('key', $k)->value('value'); return $v === null || $v === '' ? $def : (string)$v; } catch (\Throwable $e) { return $def; } }
  public static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); }
  public static function on(): bool { return self::ready() && self::get('wa_rec_on', '1') === '1'; }
  public static function days(): int { return max(1, min(3650, (int)self::get('wa_rec_days', '90'))); }
  public static function notice(): string { return (string)self::get('wa_rec_notice', self::NOTICE); }
  /** Who may listen: admin (only Super/Admin), handler (those and the representative who took the call), all (everyone who can open the Call Center). Download and delete are always Super/Admin. */
  public static function listenRule(): string { $v = self::get('wa_rec_listen', 'handler'); return in_array($v, ['admin', 'handler', 'all'], true) ? $v : 'handler'; }
  public static function canListen($user, object $rec): bool {
    if (!$user) return false; if (ProfitFloor::admin()) return true; $rule = self::listenRule();
    return $rule === 'all' || ($rule === 'handler' && (int)$rec->user_id === (int)$user->id);
  }
  public static function dir(): string { $d = storage_path('app/wa_recordings'); if (!is_dir($d)) @mkdir($d, 0770, true); return $d; }
  public static function file(object $rec): ?string { if (!$rec->path) return null; $f = self::dir() . '/' . $rec->path; return is_file($f) ? $f : null; }
  public static function log(int $rid, string $action, ?string $detail = null): void {
    try { DB::table('wa_call_recording_logs')->insert(['recording_id' => $rid, 'user_id' => auth()->id(), 'user_name' => mb_substr((string)(auth()->user()->name ?? 'System'), 0, 120), 'action' => $action, 'detail' => $detail ? mb_substr($detail, 0, 200) : null, 'ip' => request()?->ip(), 'created_at' => now()]); } catch (\Throwable $e) {}
  }
  /** The call must be connected and belong to this representative: a missed or unanswered call can never get a recording row. */
  private static function mine(int $callId, int $uid): ?object { $c = WaCalls::ready() ? DB::table('wa_calls')->find($callId) : null; return $c && (int)$c->user_id === $uid && $c->status === 'active' && $c->answered_at ? $c : null; }
  public static function start(int $callId, int $uid, string $uname, string $mime): array {
    if (!self::on()) return [false, 'Recording is switched off.', null];
    $c = self::mine($callId, $uid); if (!$c) return [false, 'This call is not connected to you.', null];
    $ex = DB::table('wa_call_recordings')->where('call_id', $callId)->first(); if ($ex) return [$ex->status === 'recording', $ex->status === 'recording' ? 'ok' : 'This call already has a recording.', $ex->status === 'recording' ? (int)$ex->id : null];
    $sub = now('Asia/Dhaka')->format('Y/m'); if (!is_dir(self::dir() . '/' . $sub) && !@mkdir(self::dir() . '/' . $sub, 0770, true)) return self::failNew($c, $uid, $uname, 'Storage folder could not be created');
    $path = $sub . '/call' . $callId . '_' . bin2hex(random_bytes(12)) . '.webm';
    if (@file_put_contents(self::dir() . '/' . $path, '') === false) return self::failNew($c, $uid, $uname, 'Storage is not writable');
    $local = Chat::phone((string)$c->from_wa); $t = WaCalls::thread($c->from_wa);
    try { $id = (int)DB::table('wa_call_recordings')->insertGetId(['call_id' => $callId, 'status' => 'recording', 'path' => $path, 'mime' => mb_substr($mime ?: 'audio/webm', 0, 60), 'customer_wa' => $c->from_wa, 'customer_phone' => $local && preg_match('/^01\d{9}$/', $local) ? $local : null, 'customer_name' => $c->from_name,
      'phone_id' => $c->phone_id, 'business_number' => mb_substr(WaCalls::numberLabel((string)$c->phone_id), 0, 60), 'user_id' => $uid, 'user_name' => mb_substr($uname, 0, 120), 'thread_id' => $t->id ?? null, 'started_at' => now(), 'expires_at' => now()->addDays(self::days()), 'created_at' => now(), 'updated_at' => now()]);
    } catch (\Illuminate\Database\UniqueConstraintViolationException $e) { @unlink(self::dir() . '/' . $path); return [false, 'This call already has a recording.', null]; }
    return [true, 'ok', $id];
  }
  private static function failNew(object $c, int $uid, string $uname, string $why): array {
    try { DB::table('wa_call_recordings')->insertOrIgnore(['call_id' => $c->id, 'status' => 'failed', 'customer_wa' => $c->from_wa, 'customer_name' => $c->from_name, 'phone_id' => $c->phone_id, 'user_id' => $uid, 'user_name' => mb_substr($uname, 0, 120), 'fail_reason' => $why, 'started_at' => now(), 'ended_at' => now(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    self::alert(); return [false, $why, null];
  }
  /** One piece of audio. Pieces arrive in order; a repeated piece is ignored; a gap marks the recording incomplete but keeps what is there. */
  public static function chunk(int $callId, int $uid, int $seq, string $data): array {
    $rec = self::ready() ? DB::table('wa_call_recordings')->where('call_id', $callId)->first() : null;
    if (!$rec || (int)$rec->user_id !== $uid) return [false, 'No recording for this call.'];
    if ($rec->status !== 'recording') return [false, 'Recording is closed.'];
    if ($seq < (int)$rec->chunks) return [true, 'dup']; if ($data === '') return [true, 'empty'];
    if ((int)$rec->bytes + strlen($data) > self::MAX_BYTES) { self::close((int)$rec->id, 'incomplete', 'Size limit reached'); return [false, 'Recording size limit reached.']; }
    $f = self::dir() . '/' . $rec->path; $ok = is_file($f) && @file_put_contents($f, $data, FILE_APPEND | LOCK_EX) === strlen($data);
    if (!$ok) { self::close((int)$rec->id, (int)$rec->bytes > 0 ? 'incomplete' : 'failed', 'Could not write to storage'); return [false, 'Storage write failed.']; }
    $u = ['bytes' => (int)$rec->bytes + strlen($data), 'chunks' => $seq + 1, 'updated_at' => now()]; if ($seq > (int)$rec->chunks) $u['fail_reason'] = 'A piece of audio is missing (network)';
    DB::table('wa_call_recordings')->where('id', $rec->id)->update($u);
    return [true, 'ok'];
  }
  public static function close(int $rid, string $status, ?string $why = null, ?int $duration = null): void {
    $rec = DB::table('wa_call_recordings')->find($rid); if (!$rec || $rec->status !== 'recording') return;
    if ($status === 'complete' && ((int)$rec->bytes <= 0)) { $status = 'failed'; $why = $why ?: 'No audio was received'; }
    if ($status === 'complete' && $rec->fail_reason) $status = 'incomplete';
    $c = DB::table('wa_calls')->find($rec->call_id);
    DB::table('wa_call_recordings')->where('id', $rid)->where('status', 'recording')->update(['status' => $status, 'fail_reason' => $why ? mb_substr($why, 0, 200) : $rec->fail_reason, 'duration' => $duration ?? ($c->duration ?? max(0, time() - strtotime((string)$rec->started_at))), 'ended_at' => now(), 'updated_at' => now()]);
    if ($status === 'failed' && $rec->path) { @unlink(self::dir() . '/' . $rec->path); }
    if ($status !== 'complete') self::alert();
  }
  public static function finish(int $callId, int $uid, ?int $duration, ?string $error): array {
    $rec = self::ready() ? DB::table('wa_call_recordings')->where('call_id', $callId)->first() : null;
    if (!$rec || (int)$rec->user_id !== $uid) { if ($error && self::on() && ($c = DB::table('wa_calls')->find($callId)) && (int)$c->user_id === $uid && $c->answered_at && !$rec) self::failNew($c, $uid, (string)$c->user_name, mb_substr($error, 0, 200)); return [false, 'No recording for this call.']; }
    if ($rec->status === 'recording') self::close((int)$rec->id, $error ? ((int)$rec->bytes > 0 ? 'incomplete' : 'failed') : 'complete', $error, $duration && $duration > 0 && $duration < 86400 ? $duration : null);
    return [true, (string)DB::table('wa_call_recordings')->where('id', $rec->id)->value('status')];
  }
  public static function noticeGiven(int $callId, int $uid): bool { return self::ready() && (bool)DB::table('wa_call_recordings')->where('call_id', $callId)->where('user_id', $uid)->whereNull('notice_at')->update(['notice_at' => now(), 'updated_at' => now()]); }
  /** Bell alert while failed or incomplete recordings of the last 3 days are unseen by an admin. */
  public static function alert(): void {
    try { $n = (int)DB::table('wa_call_recordings')->whereIn('status', ['failed', 'incomplete'])->where('created_at', '>', now()->subDays(3))->where('id', '>', (int)self::get('wa_rec_seen', '0'))->count();
      if ($n > 0) DeployHealth::raise('wa_rec_failed', 'medium', 'Call Center', $n . ' WhatsApp call recording' . ($n > 1 ? 's' : '') . ' failed or incomplete', 'Some answered WhatsApp calls in the last 3 days have no full recording. They are marked on Call Center > Call Recordings.', 'The representative browser lost its connection, closed during the call, or the server could not store the audio.', 'Open Call Center > Call Recordings and check the marked calls. The calls themselves were not affected.');
      else DeployHealth::resolve('wa_rec_failed', 'System (no failed recording in the last 3 days)');
    } catch (\Throwable $e) {}
  }
  /** An admin opened the recordings page: the failed ones listed there count as seen. */
  public static function seen(): void { try { self::put('wa_rec_seen', (string)(int)DB::table('wa_call_recordings')->max('id')); self::alert(); } catch (\Throwable $e) {} }
  /** Every minute: close recordings whose call is over but whose browser never said "finished", and remove expired files. */
  public static function tick(): void {
    try {
      if (!self::ready()) return;
      foreach (DB::table('wa_call_recordings as r')->join('wa_calls as c', 'c.id', '=', 'r.call_id')->where('r.status', 'recording')->where('c.status', '!=', 'active')->where('r.updated_at', '<', now()->subSeconds(90))->limit(20)->get(['r.id', 'r.bytes']) as $r)
        self::close((int)$r->id, (int)$r->bytes > 0 ? 'incomplete' : 'failed', 'The browser closed or lost its connection before the recording was finished');
      if (Cache::add('wa.rec.purge', 1, 3600)) self::purge();
    } catch (\Throwable $e) { report($e); }
  }
  public static function purge(): int {
    $n = 0;
    foreach (DB::table('wa_call_recordings')->whereIn('status', ['complete', 'incomplete'])->whereNotNull('expires_at')->where('expires_at', '<', now())->limit(200)->get() as $r) {
      if ($r->path) @unlink(self::dir() . '/' . $r->path);
      DB::table('wa_call_recordings')->where('id', $r->id)->update(['status' => 'expired', 'bytes' => 0, 'removed_by' => 'System (retention)', 'removed_at' => now(), 'updated_at' => now()]);
      try { DB::table('wa_call_recording_logs')->insert(['recording_id' => $r->id, 'user_name' => 'System', 'action' => 'expire', 'detail' => 'Kept ' . self::days() . ' days', 'created_at' => now()]); } catch (\Throwable $e) {} $n++;
    }
    return $n;
  }
  public static function remove(int $rid, string $by): bool {
    $r = DB::table('wa_call_recordings')->find($rid); if (!$r || in_array($r->status, ['deleted', 'expired', 'recording'], true)) return false;
    if ($r->path) @unlink(self::dir() . '/' . $r->path);
    DB::table('wa_call_recordings')->where('id', $rid)->update(['status' => 'deleted', 'bytes' => 0, 'removed_by' => mb_substr($by, 0, 120), 'removed_at' => now(), 'updated_at' => now()]);
    self::log($rid, 'delete'); return true;
  }
  /** Retention changed: move the expiry of the recordings that still exist. */
  public static function setDays(int $d): void { $d = max(1, min(3650, $d)); self::put('wa_rec_days', (string)$d); DB::table('wa_call_recordings')->whereIn('status', ['recording', 'complete', 'incomplete'])->update(['expires_at' => DB::raw("started_at + interval '" . $d . " days'")]); }
  public static function usage(): array { try { $r = DB::table('wa_call_recordings')->whereIn('status', ['complete', 'incomplete', 'recording'])->selectRaw('count(*) as n, coalesce(sum(bytes),0) as b, coalesce(sum(duration),0) as s')->first(); return ['count' => (int)$r->n, 'bytes' => (int)$r->b, 'seconds' => (int)$r->s, 'free' => (int)@disk_free_space(self::dir())]; } catch (\Throwable $e) { return ['count' => 0, 'bytes' => 0, 'seconds' => 0, 'free' => 0]; } }
  /** Link to an order: only an order of the SAME customer number (never a guess). */
  public static function linkOrder(int $rid, string $invoiceOrId): array {
    $r = DB::table('wa_call_recordings')->find($rid); if (!$r) return [false, 'Recording not found.'];
    $v = trim($invoiceOrId); if ($v === '') { DB::table('wa_call_recordings')->where('id', $rid)->update(['order_id' => null, 'updated_at' => now()]); self::log($rid, 'unlink_order'); return [true, 'Order link removed.']; }
    $d = preg_replace('/\D/', '', $v); $o = $d === '' ? null : (DB::table('orders')->where('invoice', $d)->first() ?: DB::table('orders')->where('invoice', 'like', '%' . $d)->orderByDesc('id')->first() ?: DB::table('orders')->find((int)$d));
    if (!$o) return [false, 'No order found for "' . $v . '".'];
    if (!$r->customer_phone) return [false, 'WhatsApp gave no phone number for this caller, so the recording cannot be tied to an order safely.'];
    if (substr(preg_replace('/\D/', '', (string)$o->phone), -11) !== $r->customer_phone) return [false, 'Order ' . Inv::show($o->invoice) . ' belongs to another phone number. Not linked.'];
    DB::table('wa_call_recordings')->where('id', $rid)->update(['order_id' => (int)$o->id, 'updated_at' => now()]); self::log($rid, 'link_order', Inv::show($o->invoice));
    return [true, 'Linked to order ' . Inv::show($o->invoice) . '.'];
  }
  /** Rows for a list (history page, call window, order page). $f: wa, phone, order_id, q, status. */
  public static function rows(array $f, $user, int $limit = 50): array {
    if (!self::ready()) return [];
    $q = DB::table('wa_call_recordings as r')->leftJoin('orders as o', 'o.id', '=', 'r.order_id')->orderByDesc('r.id')->limit($limit);
    if (!empty($f['wa'])) $q->where('r.customer_wa', preg_replace('/\D/', '', (string)$f['wa']));
    if (!empty($f['order_id'])) $q->where('r.order_id', (int)$f['order_id']);
    if (!empty($f['status'])) $q->where('r.status', $f['status']);
    if (!empty($f['not_call'])) $q->where('r.call_id', '!=', (int)$f['not_call']);
    if (!empty($f['q'])) { $s = trim((string)$f['q']); $d = preg_replace('/\D/', '', $s); $q->where(function ($w) use ($s, $d) { $w->where('r.customer_name', 'ilike', '%' . $s . '%')->orWhere('r.user_name', 'ilike', '%' . $s . '%'); if (strlen($d) >= 4) $w->orWhere('r.customer_wa', 'like', '%' . $d . '%')->orWhere('o.invoice', 'like', '%' . $d . '%'); }); }
    $out = [];
    foreach ($q->get(['r.*', 'o.invoice as order_invoice']) as $r) {
      $out[] = ['id' => (int)$r->id, 'call_id' => (int)$r->call_id, 'status' => $r->status, 'at' => $r->started_at ? \Carbon\Carbon::parse($r->started_at)->timezone('Asia/Dhaka')->format('d M Y h:i A') : '', 'duration' => $r->duration !== null ? (int)$r->duration : null,
        'customer' => (string)$r->customer_name, 'wa' => $r->customer_wa ? '+' . $r->customer_wa : '', 'business' => (string)$r->business_number, 'by' => (string)$r->user_name, 'bytes' => (int)$r->bytes, 'notice' => $r->notice_at !== null,
        'order_id' => $r->order_id ? (int)$r->order_id : null, 'order' => $r->order_invoice ? Inv::show($r->order_invoice) : null, 'thread_id' => $r->thread_id ? (int)$r->thread_id : null, 'verify' => (string)$r->verify_result, 'verify_by' => (string)$r->verify_by, 'why' => (string)$r->fail_reason,
        'can_listen' => in_array($r->status, ['complete', 'incomplete'], true) && self::canListen($user, $r), 'expires' => $r->expires_at ? \Carbon\Carbon::parse($r->expires_at)->timezone('Asia/Dhaka')->format('d M Y') : '', 'removed_by' => (string)$r->removed_by];
    }
    return $out;
  }
}
