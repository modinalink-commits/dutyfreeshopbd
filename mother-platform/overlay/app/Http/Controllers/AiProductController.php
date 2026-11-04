<?php
namespace App\Http\Controllers;
use App\Support\AiImage;
use App\Support\Procure;
use App\Support\ProductAutoFillService as AF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/** Create product from image: upload -> AI reads the pack -> preview -> staff accepts -> normal product form (no price filled). */
class AiProductController extends Controller {
  private function mine(int $id): object {
    $s = DB::table('ai_product_scans')->find($id); abort_unless($s, 404);
    abort_unless((int)$s->user_id === (int)auth()->id() || in_array(auth()->user()->role ?? '', ['super', 'admin'], true), 403);
    return $s;
  }
  private function payload(object $s): array {
    $d = json_decode((string)$s->detected, true) ?: [];
    $mode = $d['mode'] ?? null;
    return ['ok'=>true, 'scan_id'=>(int)$s->id, 'image'=>$s->image, 'identified'=>(bool)($d['identified'] ?? false), 'multiple'=>(bool)($d['multiple'] ?? false), 'candidates'=>$d['candidates'] ?? [], 'quality'=>$d['quality'] ?? 'ok',
      'notes'=>trim(($d['notes'] ?? '').' '.implode(' ', $d['app_notes'] ?? [])), 'rows'=>AF::rows($d), 'matches'=>$d['matches'] ?? [], 'mode'=>$mode, 'low'=>AF::LOW, 'model'=>$s->model];
  }
  public function page() {
    return view('ai_product_scan', ['ready'=>\App\Support\ProductImageAIService::ready(), 'admin'=>Procure::canApprove(), 'set'=>collect(AF::SETTINGS)->map(fn($l, $k) => ['label'=>$l, 'on'=>AF::on($k)])->all(), 'model'=>\App\Support\ProductImageAIService::model(),
      'recent'=>DB::table('ai_product_scans')->when(!Procure::canApprove(), fn($q) => $q->where('user_id', auth()->id()))->orderByDesc('id')->limit(10)->get()]);
  }
  public function scan(Request $r) {
    if (!AF::on('on')) return response()->json(['ok'=>false, 'error'=>'AI Product Auto-Fill is turned off in the settings.'], 403);
    if (!\Illuminate\Support\Facades\RateLimiter::attempt('aiscan:'.auth()->id(), 15, fn() => true, 60)) return response()->json(['ok'=>false, 'error'=>'Too many scans in a minute. Wait a little and try again.'], 429);
    @set_time_limit(150);
    $focus = trim((string)$r->input('focus', '')) ?: null;
    if ($r->filled('scan_id')) {
      $s = $this->mine((int)$r->input('scan_id')); $abs = AiImage::absOf($s->image);
      if (!$abs) return response()->json(['ok'=>false, 'error'=>'The uploaded image is no longer on the server. Please upload it again.'], 422);
      $id = (int)$s->id; $url = $s->image;
    } else {
      $r->validate(['file'=>'required|file|mimes:jpg,jpeg,png,webp|max:8192'], ['file.required'=>'Choose a product image first.', 'file.mimes'=>'Use a JPG, PNG or WebP image.', 'file.max'=>'The image is larger than 8 MB.']);
      $tmp = sys_get_temp_dir().'/aiscan-'.bin2hex(random_bytes(6)); copy($r->file('file')->getRealPath(), $tmp);
      $up = app(UploadController::class)->image($r); $j = $up->getData(true);
      if (empty($j['url'])) { @unlink($tmp); return response()->json(['ok'=>false, 'error'=>$j['message'] ?? 'Upload failed'], 422); }
      $url = $j['url']; $abs = $tmp;
      $id = DB::table('ai_product_scans')->insertGetId(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name ?? null, 'image'=>$url, 'model'=>\App\Support\ProductImageAIService::model(), 'status'=>'scanning', 'created_at'=>now(), 'updated_at'=>now()]);
    }
    try { $res = AF::detect($abs, $focus); } finally { if (str_starts_with((string)$abs, sys_get_temp_dir().'/aiscan-')) @unlink($abs); }
    if (!$res['ok']) {
      DB::table('ai_product_scans')->where('id', $id)->update(['status'=>'failed', 'error'=>mb_substr($res['error'], 0, 290), 'updated_at'=>now()]);
      Procure::audit('ai.product_scan_failed', 'Image scan failed: '.mb_substr($res['error'], 0, 120), null, ['scan'=>$id]);
      return response()->json(['ok'=>false, 'error'=>'The AI could not read the image ('.$res['error'].'). Try again or enter the product manually.', 'scan_id'=>$id], 502);
    }
    $d = $res['data'];
    DB::table('ai_product_scans')->where('id', $id)->update(['status'=>$d['identified'] ? 'scanned' : 'unidentified', 'detected'=>json_encode($d, JSON_UNESCAPED_UNICODE), 'edited'=>null, 'scanned_at'=>now(), 'updated_at'=>now()]);
    Procure::audit('ai.product_scan', 'Image scan: '.($d['fields']['name']['v'] ?? 'not identified'), null, ['scan'=>$id, 'image'=>$url, 'model'=>\App\Support\ProductImageAIService::model(), 'detected'=>collect($d['fields'])->map(fn($x) => $x['v'])->filter()->all()]);
    return response()->json($this->payload(DB::table('ai_product_scans')->find($id)));
  }
  public function accept(Request $r) {
    $s = $this->mine((int)$r->input('scan_id')); $d = json_decode((string)$s->detected, true) ?: [];
    abort_unless(!empty($d['identified']), 422, 'This scan did not identify a product.');
    $ok = ['name', 'brand', 'main_category', 'sub_category', 'child_category', 'short_description', 'description', 'seo_title', 'seo_description', 'sku']; $e = [];
    foreach ((array)$r->input('edit', []) as $k => $v) if (in_array($k, $ok, true) && is_string($v)) $e[$k] = mb_substr(trim($v), 0, 4000);
    $diff = []; foreach ($e as $k => $v) { $old = $k === 'sku' ? ($d['sku'] ?? '') : ($d['fields'][$k]['v'] ?? ''); if (trim((string)$old) !== $v) $diff[$k] = ['ai'=>$old, 'staff'=>$v]; }
    DB::table('ai_product_scans')->where('id', $s->id)->update(['status'=>'accepted', 'edited'=>json_encode($e, JSON_UNESCAPED_UNICODE), 'edits'=>$diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : null, 'updated_at'=>now()]);
    return response()->json(['ok'=>true, 'url'=>'/admin/products/new?ai='.$s->id]);
  }
  public function cancel(Request $r) {
    $s = $this->mine((int)$r->input('scan_id')); if (in_array($s->status, ['scanned', 'accepted', 'unidentified'], true)) DB::table('ai_product_scans')->where('id', $s->id)->update(['status'=>'cancelled', 'updated_at'=>now()]);
    return response()->json(['ok'=>true]);
  }
  public function settings(Request $r) {
    abort_unless(Procure::canApprove(), 403, 'Only the Admin can change these settings.');
    $old = []; $new = [];
    foreach (AF::SETTINGS as $k => $l) { $v = $r->boolean('s_'.$k) ? '1' : '0'; $old[$k] = AF::on($k) ? '1' : '0'; $new[$k] = $v; DB::table('settings')->updateOrInsert(['key'=>'ai_fill_'.$k], ['value'=>$v]); }
    if ($old !== $new) Procure::audit('ai.product_fill_settings', 'AI Product Auto-Fill settings changed', $old, $new);
    return redirect('/admin/products/from-image')->with('ok', 'Settings saved.');
  }
}
