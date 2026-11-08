<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
/** Bearer-token auth for the Staff mobile app. Same users, same role permissions as the web admin (ModulePermission); nothing is duplicated. */
class StaffApi {
  /** first path segment after api/staff -> permission module (null = any signed-in staff) */
  const MODULE = ['dashboard'=>'orders','orders'=>'orders','chat'=>'orders','customers'=>'customers','courier'=>'couriers','tasks'=>null,'me'=>null,'devices'=>null,'logout'=>null];
  public static function hash(string $t): string { return hash('sha256', $t); }
  public function handle(Request $r, Closure $next) {
    $tok = (string)$r->bearerToken();
    if ($tok === '') return response()->json(['error'=>'unauthenticated'], 401);
    $row = DB::table('staff_api_tokens')->where('token_hash', self::hash($tok))->first();
    if (!$row || $row->revoked_at || $row->blocked || ($row->expires_at && now()->gt($row->expires_at))) return response()->json(['error'=>'session_expired'], 401);
    $u = \App\Models\User::find($row->user_id);
    if (!$u || !$u->active) return response()->json(['error'=>'account_disabled'], 403);
    Auth::setUser($u); $r->setUserResolver(fn() => $u); $r->attributes->set('staff_token_id', $row->id);
    if (!$row->last_used_at || now()->diffInMinutes($row->last_used_at) >= 2) DB::table('staff_api_tokens')->where('id', $row->id)->update(['last_used_at'=>now(), 'ip'=>$r->ip()]);
    $seg = explode('/', trim(substr($r->path(), strlen('api/staff')), '/'))[0] ?? '';
    if (array_key_exists($seg, self::MODULE) && ($m = self::MODULE[$seg]) !== null) {
      $need = $r->isMethod('get') ? 'view' : 'edit';
      if (!ModulePermission::can($u, $m, $need)) return response()->json(['error'=>'forbidden', 'message'=>'Your role does not allow this.'], 403);
    }
    return $next($r);
  }
}
