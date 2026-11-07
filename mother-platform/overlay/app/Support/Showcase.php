<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Homepage product showcase strip: active slots with a resolved product link. Never throws (the home page must keep working). */
class Showcase {
  public static function slots(): array {
    try {
      if (!Schema::hasTable('showcase_slots')) return [];
      $rows = DB::table('showcase_slots')->where('active', true)->whereNotNull('image')->orderBy('position')->orderBy('id')->limit(6)->get();
      $out = [];
      foreach ($rows as $r) {
        $u = trim((string)$r->link); $p = null;
        if ($r->product_id) { $p = DB::table('products')->where('id', $r->product_id)->where('published', true)->first(); if (!$p && $u === '') continue; }
        if ($u === '' && $p) $u = \App\Http\Controllers\StoreController::url($p);
        if ($u === '') continue;
        $out[] = ['n' => (int)$r->position, 'pid' => (int)($r->product_id ?? 0), 'title' => (string)($p->name ?? $r->title ?? ''), 'img' => (string)$r->image, 'c' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r->theme) ? $r->theme : '#B45309', 'u' => $u, 'm' => (($r->mode ?? 'cutout') === 'poster') ? 'poster' : 'cutout', 'v' => strtotime((string)$r->updated_at) ?: 1];
      }
      return $out;
    } catch (\Throwable $e) { return []; }
  }
}
