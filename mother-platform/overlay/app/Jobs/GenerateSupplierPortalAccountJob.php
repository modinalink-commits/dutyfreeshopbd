<?php
namespace App\Jobs;

use App\Support\Sms;
use App\Support\SupplierPortal as SP;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Creates (once) the portal owner login for an active supplier. Username = name + supplier code; password is random and never shown — the supplier sets their own via the activation link. */
class GenerateSupplierPortalAccountJob
{
  use Dispatchable;
  public function __construct(public int $supplierId) {}

  public static function username(object $s): string {
    $a = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$s->name); $slug = preg_replace('/[^a-z0-9]+/', '', strtolower((string)$a)); $slug = substr($slug ?: 'supplier', 0, 18);
    $code = strtolower(preg_replace('/[^A-Za-z0-9]+/', '', (string)($s->code ?: 'S'.str_pad((string)$s->id, 5, '0', STR_PAD_LEFT))));
    $u = $slug.'-'.$code; $n = 1; while (DB::table('supplier_users')->where('username', $u)->exists()) $u = $slug.'-'.$code.'-'.(++$n); return $u;
  }
  /** @return array{0:string,1:?int} [result, user id]  result: created|exists|no-mobile|invalid-mobile|missing */
  public function handle(): array {
    $s = DB::table('suppliers')->find($this->supplierId); if (!$s || !$s->active) return ['missing', null];
    if ($e = DB::table('supplier_users')->where('supplier_id', $s->id)->orderBy('id')->first()) return ['exists', $e->id];
    $raw = trim((string)($s->phone ?: $s->whatsapp)); if ($raw === '') return ['no-mobile', null];
    $num = Sms::number($raw); if (!$num || !preg_match('/^8801[3-9]\d{8}$/', $num)) return ['invalid-mobile', null];
    if (!$s->code) { $s->code = 'SUP-'.str_pad((string)$s->id, 5, '0', STR_PAD_LEFT); DB::table('suppliers')->where('id', $s->id)->update(['code'=>$s->code]); }
    $id = DB::table('supplier_users')->insertGetId(['supplier_id'=>$s->id,'name'=>$s->contact_person ?: $s->name,'mobile'=>'0'.substr($num, 3),'email'=>$s->email ? mb_strtolower($s->email) : null,'username'=>self::username($s),'password'=>Hash::make(bin2hex(random_bytes(24))),'role'=>'owner','status'=>'active','must_change'=>true,'created_at'=>now(),'updated_at'=>now()]);
    return ['created', $id];
  }
}
