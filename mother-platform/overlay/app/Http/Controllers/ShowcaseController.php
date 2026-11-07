<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Admin control for the homepage 6-product animated showcase strip. */
class ShowcaseController extends Controller
{
  public function index() {
    $rows = DB::table('showcase_slots')->orderBy('position')->orderBy('id')->get();
    $prods = [];
    foreach ($rows as $r) if ($r->product_id) $prods[$r->product_id] = DB::table('products')->where('id', $r->product_id)->first(['id', 'name', 'published']);
    return view('showcase_admin', ['rows' => $rows, 'prods' => $prods]);
  }
  public function search(Request $r) {
    $q = trim((string)$r->query('q')); if (mb_strlen($q) < 2) return response()->json([]);
    $p = DB::table('products')->where('published', true)->where('name', 'ilike', '%' . str_replace(['%', '_'], '', $q) . '%')->orderBy('name')->limit(12)->get(['id', 'name']);
    return response()->json($p);
  }
  public function save(Request $r) {
    $r->validate(['slots' => 'required|array|max:6', 'slots.*.image_file' => 'nullable|file|mimes:webp,png,jpg,jpeg|max:3072']);
    foreach ((array)$r->input('slots') as $id => $d) {
      $row = DB::table('showcase_slots')->where('id', (int)$id)->first(); if (!$row) continue;
      $up = ['title' => mb_substr(trim((string)($d['title'] ?? '')), 0, 120) ?: null, 'link' => mb_substr(trim((string)($d['link'] ?? '')), 0, 255) ?: null,
        'theme' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($d['theme'] ?? '')) ? $d['theme'] : $row->theme, 'mode' => (($d['mode'] ?? '') === 'poster') ? 'poster' : 'cutout', 'active' => !empty($d['active']), 'position' => max(1, min(6, (int)($d['position'] ?? $row->position))), 'updated_at' => now()];
      $pid = (int)($d['product_id'] ?? 0); $up['product_id'] = $pid > 0 && DB::table('products')->where('id', $pid)->exists() ? $pid : null;
      $f = $r->file("slots.$id.image_file");
      if ($f && $f->isValid()) { $ext = strtolower($f->getClientOriginalExtension() ?: 'webp'); $name = 'slot' . (int)$id . '-' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext; $f->move(public_path('showcase'), $name); $up['image'] = '/showcase/' . $name; }
      DB::table('showcase_slots')->where('id', $row->id)->update($up);
    }
    foreach (['store.home10', 'store.home5', 'store.home6'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
    return redirect('/admin/showcase')->with('ok', 'Showcase saved. The homepage shows the change now.');
  }
}
