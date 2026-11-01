<?php
namespace App\Http\Controllers;
use App\Support\Slugs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Category tree (Main -> Sub -> Sub Sub) and independent Brands. Products keep text sub / sub_sub; nodes add order, status, image and flags. */
class TaxonomyController extends Controller {
  private function bust(): void { foreach (['store.cats', 'store.subs', 'store.catslugs', 'store.home6', 'store.home5', 'store.home4', 'store.sitemap5'] as $k) Cache::forget($k); }
  private function err(string $m, int $c = 422) { return response()->json(['ok' => false, 'message' => $m], $c); }

  /** Create nodes for any (category, sub, sub_sub) found on products but missing in the tree. */
  private function sync(): void {
    $have = []; foreach (DB::table('category_nodes')->get() as $n) $have[$n->category_id . '|' . ($n->level == 2 ? '' : $n->parent_id) . '|' . $n->name . '|' . $n->level] = $n;
    $subs = DB::table('category_nodes')->where('level', 2)->get()->keyBy(fn($n) => $n->category_id . '|' . $n->name);
    $rows = DB::table('products')->whereNotNull('category_id')->whereNotNull('sub')->where('sub', '!=', '')->select('category_id', 'sub', 'sub_sub', DB::raw('count(*) n'))->groupBy('category_id', 'sub', 'sub_sub')->orderByDesc('n')->get();
    $pos = [];
    foreach ($rows as $r) {
      $k = $r->category_id . '|' . $r->sub;
      if (!isset($subs[$k])) {
        $pos[$r->category_id] = ($pos[$r->category_id] ?? (int)DB::table('category_nodes')->where('category_id', $r->category_id)->where('level', 2)->max('position')) + 1;
        $id = DB::table('category_nodes')->insertGetId(['category_id' => $r->category_id, 'level' => 2, 'name' => $r->sub, 'slug' => Slugs::subSlug($r->sub), 'position' => $pos[$r->category_id], 'created_at' => now(), 'updated_at' => now()]);
        $subs[$k] = (object)['id' => $id, 'category_id' => $r->category_id, 'name' => $r->sub];
      }
      if ($r->sub_sub && !DB::table('category_nodes')->where('level', 3)->where('parent_id', $subs[$k]->id)->where('name', $r->sub_sub)->exists())
        DB::table('category_nodes')->insert(['category_id' => $r->category_id, 'parent_id' => $subs[$k]->id, 'level' => 3, 'name' => $r->sub_sub, 'slug' => Slugs::slug($r->sub_sub, 60) ?: 'other', 'position' => (int)DB::table('category_nodes')->where('parent_id', $subs[$k]->id)->max('position') + 1, 'created_at' => now(), 'updated_at' => now()]);
    }
  }

  private function tree(): array {
    $this->sync();
    $cnt = []; foreach (DB::table('products')->select('category_id', 'sub', 'sub_sub', DB::raw('count(*) n'))->groupBy('category_id', 'sub', 'sub_sub')->get() as $r) { $cnt['m' . $r->category_id] = ($cnt['m' . $r->category_id] ?? 0) + $r->n; if ($r->sub !== null && $r->sub !== '') { $cnt['s' . $r->category_id . '|' . $r->sub] = ($cnt['s' . $r->category_id . '|' . $r->sub] ?? 0) + $r->n; if ($r->sub_sub) $cnt['t' . $r->category_id . '|' . $r->sub . '|' . $r->sub_sub] = ($cnt['t' . $r->category_id . '|' . $r->sub . '|' . $r->sub_sub] ?? 0) + $r->n; } }
    $nodes = DB::table('category_nodes')->orderBy('position')->orderBy('name')->get();
    $o = [];
    foreach (DB::table('categories')->whereNull('parent_id')->orderBy('position')->orderBy('name')->get() as $m) {
      $subs = [];
      foreach ($nodes->where('category_id', $m->id)->where('level', 2) as $s) {
        $kids = [];
        foreach ($nodes->where('parent_id', $s->id)->where('level', 3) as $t) $kids[] = ['id' => $t->id, 'type' => 'subsub', 'name' => $t->name, 'slug' => $t->slug, 'image' => $t->image, 'description' => $t->description, 'position' => $t->position, 'active' => (bool)$t->active, 'show_home' => (bool)$t->show_home, 'featured' => (bool)$t->featured, 'count' => $cnt['t' . $m->id . '|' . $s->name . '|' . $t->name] ?? 0, 'parent_id' => $s->id, 'main_id' => $m->id, 'url' => '/' . $m->slug . '/' . Slugs::subSlug($s->name) . '/' . $t->slug];
        $subs[] = ['id' => $s->id, 'type' => 'sub', 'name' => $s->name, 'slug' => $s->slug, 'image' => $s->image, 'description' => $s->description, 'position' => $s->position, 'active' => (bool)$s->active, 'show_home' => (bool)$s->show_home, 'featured' => (bool)$s->featured, 'count' => $cnt['s' . $m->id . '|' . $s->name] ?? 0, 'parent_id' => $m->id, 'main_id' => $m->id, 'url' => '/' . $m->slug . '/' . Slugs::subSlug($s->name), 'children' => $kids];
      }
      $o[] = ['id' => $m->id, 'type' => 'main', 'name' => $m->name, 'slug' => $m->slug, 'image' => $m->image, 'description' => $m->description, 'position' => $m->position, 'active' => (bool)$m->active, 'show_home' => (bool)($m->show_home ?? true), 'count' => $cnt['m' . $m->id] ?? 0, 'url' => '/' . $m->slug, 'children' => $subs];
    }
    return $o;
  }
  private function brands(): array {
    $bc = DB::table('products')->select('brand_id', DB::raw('count(*) c'))->groupBy('brand_id')->pluck('c', 'brand_id');
    return DB::table('brands')->orderBy('position')->orderBy('name')->get()->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'slug' => $b->slug ?: Slugs::slug($b->name), 'logo' => $b->logo ?? null, 'description' => $b->description ?? null, 'position' => $b->position, 'active' => (bool)$b->active, 'count' => $bc[$b->id] ?? 0])->all();
  }
  public function index() { return view('taxonomy', ['data' => ['tree' => $this->tree(), 'brands' => $this->brands()]]); }
  public function data() { return response()->json(['tree' => $this->tree(), 'brands' => $this->brands()])->header('Cache-Control', 'no-store'); }

  public function save(Request $r) {
    $type = $r->input('type'); abort_unless(in_array($type, ['main', 'sub', 'subsub'], true), 422);
    $id = (int)$r->input('id');
    $d = $r->validate(['name' => 'required|string|max:120', 'image' => 'nullable|string|max:500', 'description' => 'nullable|string|max:2000', 'position' => 'nullable|integer', 'active' => 'nullable', 'show_home' => 'nullable', 'featured' => 'nullable']);
    $name = trim($d['name']); $act = filter_var($r->input('active', true), FILTER_VALIDATE_BOOLEAN); $home = filter_var($r->input('show_home', false), FILTER_VALIDATE_BOOLEAN); $feat = filter_var($r->input('featured', false), FILTER_VALIDATE_BOOLEAN);
    $img = trim((string)($d['image'] ?? '')) ?: null; $desc = trim((string)($d['description'] ?? '')) ?: null; $now = now();
    if ($type === 'main') {
      $slug = Slugs::slug($r->input('slug') ?: $name); if ($slug === '') return $this->err('Slug is required (letters, numbers and dashes).');
      if (DB::table('categories')->where('slug', $slug)->when($id, fn($q) => $q->where('id', '!=', $id))->exists()) return $this->err('This slug is already used by another category.');
      if (DB::table('categories')->whereRaw('lower(name) = ?', [mb_strtolower($name)])->whereNull('parent_id')->when($id, fn($q) => $q->where('id', '!=', $id))->exists()) return $this->err('A main category with this name already exists.');
      $row = ['name' => $name, 'slug' => $slug, 'image' => $img, 'description' => $desc, 'active' => $act, 'show_home' => $home, 'updated_at' => $now];
      if ($id) { abort_unless(DB::table('categories')->where('id', $id)->exists(), 404); if (isset($d['position'])) $row['position'] = (int)$d['position']; DB::table('categories')->where('id', $id)->update($row); }
      else { $row['position'] = $d['position'] ?? ((int)DB::table('categories')->max('position') + 1); $row['created_at'] = $now; $id = DB::table('categories')->insertGetId($row); }
      $this->bust(); return response()->json(['ok' => true, 'id' => $id]);
    }
    $slug = Slugs::slug($name, 60); if ($slug === '') return $this->err('Name must contain letters or numbers.');
    $parent = (int)$r->input('parent_id'); $row = ['name' => $name, 'slug' => $type === 'sub' ? Slugs::subSlug($name) : $slug, 'image' => $img, 'description' => $desc, 'active' => $act, 'show_home' => $home, 'featured' => $feat, 'updated_at' => $now];
    if ($type === 'sub') {
      $main = DB::table('categories')->where('id', $parent)->whereNull('parent_id')->first(); if (!$main) return $this->err('Choose a parent main category.');
      if (DB::table('category_nodes')->where('level', 2)->where('category_id', $main->id)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->when($id, fn($q) => $q->where('id', '!=', $id))->exists()) return $this->err('This main category already has a sub category with that name.');
      if ($id) {
        $old = DB::table('category_nodes')->where('id', $id)->where('level', 2)->first(); abort_unless($old, 404);
        if ($old->category_id != $main->id || $old->name !== $name) {
          $q = DB::table('products')->where('category_id', $old->category_id)->where('sub', $old->name);
          $moved = $old->category_id != $main->id;
          $q->update(['sub' => $name, 'category_id' => $main->id]);
          DB::table('category_nodes')->where('parent_id', $id)->update(['category_id' => $main->id]);
          if ($moved) { DB::table('products')->where('category_id', $main->id)->where('sub', $name)->update(['path_slug' => null]); Slugs::assignAll(); }
        }
        if (isset($d['position'])) $row['position'] = (int)$d['position'];
        DB::table('category_nodes')->where('id', $id)->update($row + ['category_id' => $main->id]);
      } else { $row += ['category_id' => $main->id, 'level' => 2, 'position' => $d['position'] ?? ((int)DB::table('category_nodes')->where('category_id', $main->id)->where('level', 2)->max('position') + 1), 'created_at' => $now]; $id = DB::table('category_nodes')->insertGetId($row); }
    } else {
      $ps = DB::table('category_nodes')->where('id', $parent)->where('level', 2)->first(); if (!$ps) return $this->err('Choose a parent sub category.');
      if (DB::table('category_nodes')->where('level', 3)->where('parent_id', $ps->id)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->when($id, fn($q) => $q->where('id', '!=', $id))->exists()) return $this->err('This sub category already has a sub sub category with that name.');
      if ($id) {
        $old = DB::table('category_nodes')->where('id', $id)->where('level', 3)->first(); abort_unless($old, 404);
        $oldPs = DB::table('category_nodes')->find($old->parent_id);
        if ($old->parent_id != $ps->id || $old->name !== $name) {
          DB::table('products')->where('category_id', $oldPs->category_id)->where('sub', $oldPs->name)->where('sub_sub', $old->name)->update(['sub_sub' => $name, 'sub' => $ps->name, 'category_id' => $ps->category_id]);
          if ($old->parent_id != $ps->id) { DB::table('products')->where('category_id', $ps->category_id)->where('sub', $ps->name)->where('sub_sub', $name)->update(['path_slug' => null]); Slugs::assignAll(); }
        }
        if (isset($d['position'])) $row['position'] = (int)$d['position'];
        DB::table('category_nodes')->where('id', $id)->update($row + ['parent_id' => $ps->id, 'category_id' => $ps->category_id]);
      } else { $row += ['category_id' => $ps->category_id, 'parent_id' => $ps->id, 'level' => 3, 'position' => $d['position'] ?? ((int)DB::table('category_nodes')->where('parent_id', $ps->id)->max('position') + 1), 'created_at' => $now]; $id = DB::table('category_nodes')->insertGetId($row); }
    }
    $this->bust(); return response()->json(['ok' => true, 'id' => $id]);
  }

  public function delete(Request $r) {
    $type = $r->input('type'); $id = (int)$r->input('id');
    if ($type === 'main') {
      $c = DB::table('categories')->where('id', $id)->first(); if (!$c) return $this->err('Not found.', 404);
      $p = DB::table('products')->where('category_id', $id)->count(); $k = DB::table('category_nodes')->where('category_id', $id)->count();
      if ($p || $k) return $this->err("This category contains $p products and $k subcategories. Please move or remove them before deleting.");
      DB::table('categories')->where('id', $id)->delete();
    } else {
      $n = DB::table('category_nodes')->where('id', $id)->first(); if (!$n) return $this->err('Not found.', 404);
      if ($n->level == 2) { $p = DB::table('products')->where('category_id', $n->category_id)->where('sub', $n->name)->count(); $k = DB::table('category_nodes')->where('parent_id', $n->id)->count(); }
      else { $ps = DB::table('category_nodes')->find($n->parent_id); $p = DB::table('products')->where('category_id', $n->category_id)->where('sub', $ps->name ?? '')->where('sub_sub', $n->name)->count(); $k = 0; }
      if ($p || $k) return $this->err("This category contains $p products and $k subcategories. Please move or remove them before deleting.");
      DB::table('category_nodes')->where('id', $id)->delete();
    }
    $this->bust(); return response()->json(['ok' => true]);
  }
  public function toggle(Request $r) {
    $id = (int)$r->input('id');
    if ($r->input('type') === 'main') DB::table('categories')->where('id', $id)->update(['active' => DB::raw('not active'), 'updated_at' => now()]);
    else DB::table('category_nodes')->where('id', $id)->update(['active' => DB::raw('not active'), 'updated_at' => now()]);
    $this->bust(); return response()->json(['ok' => true]);
  }
  /** Drag and drop: new order of siblings. kind=main (categories) or node (category_nodes). */
  public function reorder(Request $r) {
    $ids = array_values(array_filter(array_map('intval', (array)$r->input('ids')))); $tbl = $r->input('kind') === 'main' ? 'categories' : 'category_nodes';
    foreach ($ids as $i => $id) DB::table($tbl)->where('id', $id)->update(['position' => $i + 1, 'updated_at' => now()]);
    $this->bust(); return response()->json(['ok' => true]);
  }

  public function brandSave(Request $r) {
    $id = (int)$r->input('id');
    $d = $r->validate(['name' => 'required|string|max:120', 'slug' => 'nullable|string|max:120', 'logo' => 'nullable|string|max:500', 'description' => 'nullable|string|max:2000', 'position' => 'nullable|integer']);
    $slug = Slugs::slug($d['slug'] ?? '' ?: $d['name']); if ($slug === '') return $this->err('Slug is required.');
    if (DB::table('brands')->where('slug', $slug)->when($id, fn($q) => $q->where('id', '!=', $id))->exists()) return $this->err('This brand slug is already used.');
    if (DB::table('brands')->whereRaw('lower(name) = ?', [mb_strtolower(trim($d['name']))])->when($id, fn($q) => $q->where('id', '!=', $id))->exists()) return $this->err('A brand with this name already exists.');
    $row = ['name' => trim($d['name']), 'slug' => $slug, 'logo' => trim((string)($d['logo'] ?? '')) ?: null, 'description' => trim((string)($d['description'] ?? '')) ?: null, 'active' => filter_var($r->input('active', true), FILTER_VALIDATE_BOOLEAN), 'updated_at' => now()];
    if ($id) { if (isset($d['position'])) $row['position'] = (int)$d['position']; DB::table('brands')->where('id', $id)->update($row); }
    else { $row['position'] = $d['position'] ?? ((int)DB::table('brands')->max('position') + 1); $row['created_at'] = now(); $id = DB::table('brands')->insertGetId($row); }
    return response()->json(['ok' => true, 'id' => $id]);
  }
  public function brandDelete(Request $r) {
    $id = (int)$r->input('id'); $p = DB::table('products')->where('brand_id', $id)->count();
    if ($p) return $this->err("This brand is linked to $p products. Unlink or move them before deleting (or set the brand to Inactive).");
    DB::table('brands')->where('id', $id)->delete(); return response()->json(['ok' => true]);
  }
}
