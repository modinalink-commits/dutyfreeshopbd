<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier communication record (owner, 17 Nov 2026). Nothing is copied: a supplier's conversation is the WhatsApp conversation the
 * Messages inbox already keeps (matched by the supplier's mobile / WhatsApp number) plus the Supplier Portal messages, shown together.
 * Who may answer is the inbox rule, enforced on the server when a message is sent: only the person who holds the conversation.
 */
class SupplierComm
{
  const FIELDS = ['name' => 'Name', 'business_name' => 'Business', 'contact_person' => 'Contact person', 'phone' => 'Mobile', 'whatsapp' => 'WhatsApp'];
  public static function ready(): bool { static $r = null; return $r ??= (function () { try { return Schema::hasTable('supplier_contact_log') && Schema::hasTable('supplier_comm_notes') && Schema::hasColumn('suppliers', 'whatsapp'); } catch (\Throwable $e) { return false; } })(); }
  /** 8801XXXXXXXXX forms of a supplier's mobile and WhatsApp numbers (valid Bangladeshi mobiles only). */
  public static function numbers(object $s): array {
    $out = []; foreach ([$s->whatsapp ?? '', $s->phone ?? ''] as $p) { $wa = WhatsApp::norm((string)$p); if (preg_match('/^8801[3-9]\d{8}$/', $wa)) $out[] = $wa; }
    return array_values(array_unique($out));
  }
  public static function valid(?string $p): bool { $p = trim((string)$p); return $p === '' || (bool)preg_match('/^8801[3-9]\d{8}$/', WhatsApp::norm($p)); }
  /** What is missing or wrong in the contact data. */
  public static function gaps(object $s): array {
    $g = []; if (trim((string)$s->name) === '') $g[] = 'name';
    if (trim((string)($s->phone ?? '')) === '') $g[] = 'mobile'; elseif (!self::valid($s->phone)) $g[] = 'mobile looks wrong';
    if (trim((string)($s->whatsapp ?? '')) === '') $g[] = 'WhatsApp'; elseif (!self::valid($s->whatsapp)) $g[] = 'WhatsApp looks wrong';
    if (trim((string)($s->business_name ?? '')) === '') $g[] = 'business';
    return $g;
  }
  /** wa id => supplier id, for every supplier number (cached one minute). */
  public static function map(): array {
    return Cache::remember('supcomm.map', 60, function () {
      $m = []; try { foreach (DB::table('suppliers')->get(['id', 'phone', 'whatsapp']) as $s) foreach (self::numbers($s) as $wa) $m[$wa] ??= (int)$s->id; } catch (\Throwable $e) {}
      return $m;
    });
  }
  public static function supplierOf(string $wa): ?int { $wa = WhatsApp::norm($wa); return self::map()[$wa] ?? null; }
  public static function threads(object $s) {
    $n = self::numbers($s); if (!$n) return collect();
    return DB::table('chat_threads')->where('channel', 'whatsapp')->whereIn('external_id', $n)->orderByDesc('last_at')->get();
  }
  /** Who wrote an outgoing inbox message: [kind, label]; kind is staff | auto | app. */
  public static function sender(object $m): array {
    if (!empty($m->is_ai)) return ['auto', 'Automatic assistant (AI)'];
    $by = trim((string)($m->user_name ?? ''));
    if ($by === 'WhatsApp app') return ['app', 'Staff, from the WhatsApp phone app'];
    if ($by === '' || Chat::isAuto($by) || empty($m->user_id)) return ['auto', 'Automatic' . ($by !== '' ? ': ' . str_replace(' (auto)', '', $by) : ' message')];
    return ['staff', $by];
  }
  /** One ordered timeline: inbox messages, portal messages and internal notes. Each row is a plain array for the page. */
  public static function timeline(object $s, $threads, int $limit = 400): array {
    $rows = []; $tids = $threads->pluck('id')->all();
    if ($tids) {
      foreach (DB::table('chat_messages')->whereIn('thread_id', $tids)->orderByDesc('id')->limit($limit)->get() as $m) {
        $in = $m->dir === 'in'; [$k, $who] = $in ? ['supplier', 'Supplier'] : self::sender($m);
        $rows[] = ['t' => (string)$m->created_at, 'src' => 'WhatsApp', 'side' => $in ? 'in' : 'out', 'who_kind' => $k, 'who' => $who, 'body' => (string)$m->body, 'att' => $m->attachment_url ? ['chat', (int)$m->id, (string)($m->attachment_type ?: 'file')] : null,
          'status' => $in ? 'received' : (string)($m->status ?: 'sent'), 'error' => $m->error ?? null, 'key' => 'c' . $m->id];
      }
      try { foreach (DB::table('chat_notes')->whereIn('thread_id', $tids)->orderByDesc('id')->limit(200)->get() as $n) $rows[] = ['t' => (string)$n->created_at, 'src' => 'Note', 'side' => 'note', 'who_kind' => 'staff', 'who' => (string)$n->user_name, 'body' => (string)$n->body, 'att' => null, 'status' => 'internal', 'error' => null, 'key' => 'n' . $n->id]; } catch (\Throwable $e) {}
    }
    try { foreach (DB::table('supplier_messages')->where('supplier_id', $s->id)->orderByDesc('id')->limit($limit)->get() as $m) {
      $in = $m->from_side === 'supplier';
      $rows[] = ['t' => (string)$m->created_at, 'src' => 'Supplier Portal', 'side' => $in ? 'in' : 'out', 'who_kind' => $in ? 'supplier' : 'staff', 'who' => $in ? 'Supplier' . ($m->sender ? ' (' . $m->sender . ')' : '') : (string)($m->sender ?: 'Staff'), 'body' => (string)$m->body,
        'att' => $m->file_id ? ['portal', (int)$m->file_id, 'file'] : null, 'status' => $in ? 'received' : ($m->read_at ? 'read' : 'sent'), 'error' => null, 'key' => 'p' . $m->id];
    } } catch (\Throwable $e) {}
    foreach (DB::table('supplier_comm_notes')->where('supplier_id', $s->id)->orderByDesc('id')->limit(200)->get() as $n) $rows[] = ['t' => (string)$n->created_at, 'src' => 'Note', 'side' => 'note', 'who_kind' => 'staff', 'who' => (string)$n->user_name, 'body' => (string)$n->body, 'att' => null, 'status' => 'internal', 'error' => null, 'key' => 's' . $n->id];
    usort($rows, fn($a, $b) => strcmp($a['t'], $b['t']) ?: strcmp($a['key'], $b['key']));
    return array_slice($rows, -$limit);
  }
  /** Save contact changes and write one history line per changed field. Returns the number of changed fields. */
  public static function saveContact(object $s, array $new, ?object $user): int {
    $u = []; $n = 0;
    foreach (array_keys(self::FIELDS) as $f) {
      if (!array_key_exists($f, $new)) continue; $v = trim((string)($new[$f] ?? '')); $old = trim((string)($s->{$f} ?? ''));
      if (in_array($f, ['phone', 'whatsapp'], true) && $v !== '') { $wa = WhatsApp::norm($v); if (preg_match('/^8801[3-9]\d{8}$/', $wa)) $v = '0' . substr($wa, 3); }
      if ($v === $old) continue;
      $u[$f] = $v === '' ? null : $v; $n++;
      DB::table('supplier_contact_log')->insert(['supplier_id' => $s->id, 'field' => $f, 'old_value' => $old === '' ? null : $old, 'new_value' => $v === '' ? null : $v, 'user_id' => $user->id ?? null, 'user_name' => $user->name ?? 'System', 'created_at' => now(), 'updated_at' => now()]);
    }
    if ($u) { if (array_key_exists('name', $u) && $u['name'] === null) unset($u['name']); DB::table('suppliers')->where('id', $s->id)->update($u + ['updated_at' => now()]); Cache::forget('supcomm.map'); }
    return $n;
  }
}
