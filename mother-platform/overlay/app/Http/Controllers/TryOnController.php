<?php
namespace App\Http\Controllers;
use App\Support\TryOn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/** Sunglass Virtual Try-On. Customer side: the selfie never leaves the browser unless the customer ticks a SEPARATE consent box and presses Save. Admin side: overlay PNG generation + consented photos (admin-only, auto-expiring, deletable). */
class TryOnController extends Controller {
  // ---- admin ----
  public function index() {
    TryOn::purge();
    $prods = TryOn::sunglassQuery()->orderByDesc('products.id')->limit(500)->get();
    $assets = DB::table('tryon_assets')->get()->keyBy('product_id');
    $photos = DB::table('tryon_photos')->orderByDesc('id')->limit(200)->get();
    return view('tryon_admin', ['prods' => $prods, 'assets' => $assets, 'photos' => $photos, 'days' => TryOn::days()]);
  }
  public function generate(Request $r) {
    $id = (int)$r->input('id'); abort_unless($id > 0 && TryOn::isSunglass($id), 404);
    $res = TryOn::build($id);
    if ($res['ok']) DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name, 'action' => 'tryon-asset', 'subject' => 'product#' . $id, 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]);
    return response()->json($res);
  }
  public function settings(Request $r) {
    $d = max(1, min(365, (int)$r->input('days', 30)));
    DB::table('settings')->updateOrInsert(['key' => 'tryon_retention_days'], ['value' => (string)$d, 'updated_at' => now(), 'created_at' => now()]);
    return back()->with('ok', 'Photo retention set to ' . $d . ' days.');
  }
  public function photo($id) {
    $p = DB::table('tryon_photos')->find($id); abort_unless($p, 404);
    $f = storage_path('app/tryon/' . $p->file); abort_unless(is_file($f), 404);
    DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name, 'action' => 'tryon-photo-view', 'subject' => 'photo#' . $id, 'ip' => request()->ip(), 'created_at' => now(), 'updated_at' => now()]);
    return response()->file($f, ['Content-Type' => $p->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
  }
  public function photoDelete(Request $r, $id) {
    $p = DB::table('tryon_photos')->find($id);
    if ($p) { @unlink(storage_path('app/tryon/' . $p->file)); DB::table('tryon_photos')->where('id', $id)->delete();
      DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name, 'action' => 'tryon-photo-delete', 'subject' => 'photo#' . $id, 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]); }
    return back()->with('ok', 'Photo deleted.');
  }
  // ---- customer (storefront) ----
  public function save(Request $r) {
    if ($r->input('consent') !== '1') return response()->json(['ok' => false, 'error' => 'Consent is required to save a photo.'], 422);
    $f = $r->file('photo'); if (!$f || !$f->isValid() || $f->getSize() > 6 * 1048576) return response()->json(['ok' => false, 'error' => 'Photo missing or too large (max 6 MB).'], 422);
    $bin = (string)file_get_contents($f->getRealPath()); $i = @getimagesizefromstring($bin);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$i[2] ?? 0] ?? null; if (!$ext) return response()->json(['ok' => false, 'error' => 'Only JPG, PNG or WebP photos are accepted.'], 422);
    TryOn::purge();
    $dir = storage_path('app/tryon'); if (!is_dir($dir)) @mkdir($dir, 0770, true);
    $name = bin2hex(random_bytes(16)) . '.' . $ext; file_put_contents($dir . '/' . $name, $bin); // original bytes, exactly as sent: never re-encoded or edited
    $token = bin2hex(random_bytes(20)); $pid = (int)$r->input('product_id') ?: null;
    DB::table('tryon_photos')->insert(['token_hash' => hash('sha256', $token), 'file' => $name, 'mime' => ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext], 'bytes' => strlen($bin), 'product_id' => $pid, 'consent_version' => 'v1', 'consent_at' => now(), 'expires_at' => now()->addDays(TryOn::days()), 'ip_hash' => hash('sha256', (string)$r->ip() . config('app.key')), 'created_at' => now(), 'updated_at' => now()]);
    return response()->json(['ok' => true, 'token' => $token, 'days' => TryOn::days()]);
  }
  public function forget(Request $r) {
    $t = (string)$r->input('token'); if (strlen($t) < 20) return response()->json(['ok' => false], 422);
    foreach (DB::table('tryon_photos')->where('token_hash', hash('sha256', $t))->get() as $p) { @unlink(storage_path('app/tryon/' . $p->file)); DB::table('tryon_photos')->where('id', $p->id)->delete(); }
    return response()->json(['ok' => true]);
  }
}
