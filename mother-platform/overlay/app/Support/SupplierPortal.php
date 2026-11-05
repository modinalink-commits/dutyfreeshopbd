<?php
namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Supplier Partner Portal helpers. Every portal query is scoped by self::sid(); the portal never keeps its own ledger. */
class SupplierPortal
{
  const ROLES = ['owner'=>'Supplier Owner','accounts'=>'Supplier Accounts','sales'=>'Supplier Sales','delivery'=>'Supplier Delivery'];
  const ACCESS = ['owner'=>['po','rfq','bills','ledger','payments','profile_edit','delivery','returns','docs','support','perf'],'accounts'=>['bills','ledger','payments','docs','support'],'sales'=>['po','rfq','docs','support'],'delivery'=>['po','delivery','returns','support']];
  const REJECT = ['Price Changed','Stock Unavailable','Delivery Delay','Minimum Quantity Issue','Product Unavailable','Other'];
  const TERMS = 'v1';

  public static function user(): ?object {
    static $u = false; if ($u !== false) return $u;
    $id = (int)session('sp_uid'); if (!$id) return $u = null;
    $x = DB::table('supplier_users as u')->join('suppliers as s','s.id','=','u.supplier_id')->where('u.id',$id)->first(['u.*','s.name as supplier_name','s.code as supplier_code']);
    return $u = ($x && $x->status === 'active') ? $x : null;
  }
  public static function sid(): int { $u = self::user(); return $u ? (int)$u->supplier_id : 0; }
  public static function can(string $area): bool { $u = self::user(); return $u && in_array($area, self::ACCESS[$u->role] ?? [], true); }
  /** Save an uploaded file for a supplier (private disk); returns file id or null. */
  public static function store(?\Illuminate\Http\UploadedFile $f, int $sid, string $refType, ?int $refId, ?string $kind, string $side = 'supplier'): ?int {
    if (!$f || !$f->isValid()) return null;
    $ext = strtolower($f->getClientOriginalExtension()); if (!in_array($ext, ['jpg','jpeg','png','webp','pdf','doc','docx','xls','xlsx','csv'], true) || $f->getSize() > 6*1024*1024) return null;
    $path = $f->storeAs('supplier-files/'.$sid, bin2hex(random_bytes(10)).'.'.$ext, 'local');
    return DB::table('supplier_files')->insertGetId(['supplier_id'=>$sid,'ref_type'=>$refType,'ref_id'=>$refId,'kind'=>$kind,'name'=>mb_substr($f->getClientOriginalName(),0,190),'path'=>$path,'mime'=>$f->getClientMimeType(),'size'=>(int)$f->getSize(),'by_side'=>$side,'created_at'=>now(),'updated_at'=>now()]);
  }
  public static function brand(): string { return Procure::set('sp_brand', 'DutyFreeBD Supplier Partner'); }
  public static function tk($n): string { return '৳'.number_format((float)$n, 2); }

  public static function log(Request $r, ?object $u, string $event, ?string $ident = null): void {
    try { DB::table('supplier_login_logs')->insert(['supplier_user_id'=>$u->id ?? null,'supplier_id'=>$u->supplier_id ?? null,'identifier'=>$ident ? mb_substr($ident,0,160) : null,'event'=>$event,'ip'=>$r->ip(),'device'=>mb_substr((string)$r->userAgent(),0,200),'created_at'=>now(),'updated_at'=>now()]); } catch (\Throwable $e) {}
  }
  /** Activity of a supplier user (portal side). */
  public static function act(string $action, ?string $subject = null, $meta = null): void {
    try { $u = self::user(); if (!$u) return; DB::table('supplier_activity')->insert(['supplier_id'=>$u->supplier_id,'supplier_user_id'=>$u->id,'action'=>$action,'subject'=>$subject,'meta'=>$meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),'ip'=>request()->ip(),'created_at'=>now(),'updated_at'=>now()]); } catch (\Throwable $e) {}
  }
  public static function notify(int $sid, string $type, string $title, ?string $body = null, ?string $link = null, string $priority = 'info'): void {
    try { DB::table('supplier_notifications')->insert(['supplier_id'=>$sid,'type'=>$type,'priority'=>$priority,'title'=>$title,'body'=>$body,'link'=>$link,'created_at'=>now(),'updated_at'=>now()]); } catch (\Throwable $e) {}
  }
  public static function next(string $table, string $col, string $prefix): string {
    $n = (int)DB::table($table)->max('id') + 1; return $prefix.str_pad((string)$n, 5, '0', STR_PAD_LEFT);
  }
  public static function phone(string $v): string { $d = preg_replace('/\D+/', '', $v); if (str_starts_with($d,'880')) $d = substr($d,2); if (strlen($d) === 10 && $d[0] === '1') $d = '0'.$d; return $d; }

  /** Find an active portal user by supplier code, mobile or e-mail. */
  public static function find(string $ident): ?object {
    $i = trim($ident); if ($i === '') return null;
    $m = self::phone($i);
    return DB::table('supplier_users as u')->join('suppliers as s','s.id','=','u.supplier_id')
      ->where(fn($w) => $w->whereRaw('lower(u.username) = ?', [mb_strtolower($i)])->orWhereRaw('lower(s.code) = ?', [mb_strtolower($i)])->orWhereRaw('lower(u.email) = ?', [mb_strtolower($i)])->orWhere('u.mobile', $m)->orWhere('u.mobile', $i))
      ->orderBy('u.id')->first(['u.*','s.name as supplier_name','s.code as supplier_code']);
  }

  /** One-time codes (password reset, sensitive profile change): 6 digits, 10 minutes, 5 tries. */
  public static function otpSend(string $purpose, object $u): bool {
    if (!$u->mobile) return false;
    $code = (string)random_int(100000, 999999);
    Cache::put("spotp.$purpose.{$u->id}", ['h'=>hash('sha256', $code.config('app.key')),'n'=>0], now()->addMinutes(10));
    [$ok] = Sms::send($u->mobile, "DutyFreeBD Supplier Portal: আপনার OTP $code (১০ মিনিট বৈধ)। কাউকে জানাবেন না।");
    return $ok;
  }
  public static function otpCheck(string $purpose, int $uid, string $code): bool {
    $k = "spotp.$purpose.$uid"; $o = Cache::get($k); if (!$o) return false;
    if ($o['n'] >= 5) { Cache::forget($k); return false; }
    if (!hash_equals($o['h'], hash('sha256', trim($code).config('app.key')))) { $o['n']++; Cache::put($k, $o, now()->addMinutes(10)); return false; }
    Cache::forget($k); return true;
  }

  /** Dashboard figures. Money comes from Procure (the canonical ledger). */
  public static function kpis(int $sid): array {
    $L = Procure::events($sid);
    $paidTot = (float)DB::table('supplier_payments')->where('supplier_id',$sid)->where('status','paid')->sum('amount');
    return [
      'open_po' => (int)DB::table('supplier_pos')->where('supplier_id',$sid)->whereIn('status',['sent','accepted','partial','revised'])->count(),
      'need_accept' => (int)DB::table('supplier_pos')->where('supplier_id',$sid)->where('status','sent')->count(),
      'pending_supply' => (int)DB::table('supplier_pos')->where('supplier_id',$sid)->whereIn('status',['accepted','revised','partial'])->count(),
      'pending_rfq' => (int)DB::table('supplier_rfq_invites as i')->join('supplier_rfqs as r','r.id','=','i.rfq_id')->where('i.supplier_id',$sid)->where('i.status','pending')->where('r.status','sent')->where(fn($w)=>$w->whereNull('r.expires_at')->orWhere('r.expires_at','>',now()))->count(),
      'pending_bill' => (int)DB::table('purchases')->where('supplier_id',$sid)->whereIn('status',['draft','pending'])->count(),
      'outstanding' => (float)$L['closing'],
      'total_supply' => (float)$L['tot']['purchase'],
      'paid' => $paidTot,
      'returns' => (int)DB::table('purchase_returns')->where('supplier_id',$sid)->count() + (int)DB::table('damaged_products')->where('supplier_id',$sid)->where('responsibility','supplier')->count(),
      'unread' => (int)DB::table('supplier_notifications')->where('supplier_id',$sid)->whereNull('read_at')->count(),
    ];
  }

  /** Bills = approved purchase invoices of this supplier only. */
  public static function bills(int $sid)
  {
    $paid = DB::table('payment_allocations as a')->join('supplier_payments as p','p.id','=','a.payment_id')->where('p.status','paid')->where('p.supplier_id',$sid)->groupBy('a.purchase_id')->selectRaw('a.purchase_id, sum(a.amount) s')->pluck('s','purchase_id');
    return DB::table('purchases')->where('supplier_id',$sid)->whereNotIn('status', Procure::BAD)->orderByDesc('purchase_date')->orderByDesc('id')->get()->map(function ($b) use ($paid) {
      $b->paid_amt = (float)($paid[$b->id] ?? 0); $b->due_amt = max(0, (float)$b->total - $b->paid_amt); $b->pay_status = Procure::payStatus($b, $b->paid_amt); return $b;
    });
  }
}
