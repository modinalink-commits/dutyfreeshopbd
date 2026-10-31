<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class UploadController extends Controller {
  function image(Request $r) {
    $r->validate(['file' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:8192']);
    $f = $r->file('file');
    $dir = public_path('uploads');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = date('ymd').'-'.bin2hex(random_bytes(5)).'.'.strtolower($f->getClientOriginalExtension() ?: 'jpg');
    $f->move($dir, $name);
    // optimise (resize, compress, WebP + thumbnails). Any failure keeps the original upload untouched.
    $info = ['url' => '/uploads/'.$name];
    try {
      $abs = $dir.'/'.$name; $kind = \App\Support\Img::kindFor($r->input('slug'), $r->input('field'));
      if ($r->filled('kind') && isset(\App\Support\Img::KINDS[$r->input('kind')])) $kind = $r->input('kind');
      if (\App\Support\Img::ok()) {
        if (\App\Support\Img::backupOn()) \App\Support\Img::backup($abs);
        $res = \App\Support\Img::process($abs, $kind);
        if (!$res['ok'] && in_array($res['error'] ?? '', ['not a valid image', 'corrupted image'], true)) { @unlink($abs); return response()->json(['message' => 'This file is not a valid image. Use a JPG, PNG or WebP file.'], 422); }
        \Illuminate\Support\Facades\DB::table('media_images')->updateOrInsert(['path' => $name], ['orig_bytes'=>$res['before'] ?? 0, 'opt_bytes'=>$res['after'] ?? ($res['before'] ?? 0), 'width'=>$res['w'] ?? null, 'height'=>$res['h'] ?? null, 'format'=>$res['format'] ?? null, 'status'=>$res['ok'] ? 'optimized' : 'failed', 'error'=>$res['ok'] ? null : mb_substr((string)($res['error'] ?? ''), 0, 150), 'optimized_at'=>now(), 'updated_at'=>now(), 'created_at'=>now()]);
        if ($res['ok']) { $info['w'] = $res['w'] ?? null; $info['h'] = $res['h'] ?? null; $info['saved'] = max(0, ($res['before'] ?? 0) - ($res['after'] ?? 0)); }
      }
    } catch (\Throwable $e) { report($e); }
    return response()->json($info);
  }

  function errorLog() {
    $f = storage_path('logs/laravel.log');
    $t = is_file($f) ? substr((string)file_get_contents($f), -14000) : 'No log file yet.';
    $t = preg_replace('/(EAA[A-Za-z0-9]{20,}|password[^\n]{0,40}|APP_KEY[^\n]*)/i', '[hidden]', $t);
    return response($t, 200, ['Content-Type'=>'text/plain; charset=utf-8']);
  }
}
