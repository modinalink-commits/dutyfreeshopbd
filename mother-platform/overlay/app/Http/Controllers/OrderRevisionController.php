<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as MP;
use App\Models\Mother;
use App\Support\Delivery;
use App\Support\OrderDiscount;
use App\Support\Procure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Order Edit / Revision: an existing order is never overwritten silently. Every change is a revision (who, when, why, before, after, totals). */
class OrderRevisionController extends Controller {
  const EDITABLE = ['New', 'Pre Order', 'Assign', 'Pending', 'AI Order', 'Approved', 'Scheduled', 'WFP', 'Packaging', 'Cancel'];
  const CONFIRMED = ['Approved', 'Scheduled', 'WFP', 'Packaging'];
  const OPEN = ['New', 'Pre Order', 'Assign', 'Pending', 'AI Order'];
  const REASONS = ['Customer requested product addition', 'Customer requested product removal', 'Quantity changed', 'Product replaced', 'Price correction', 'Delivery charge correction', 'Discount correction', 'Customer confirmation update', 'Other'];

  private function perms(): array {
    $u = Auth::user(); $c = fn($m, $n) => MP::can($u, $m, $n);
    return ['edit' => $c('orders', 'edit'), 'price' => $c('orders', 'all'), 'discount' => $c('marketing', 'edit'), 'delivery' => $c('couriers', 'edit'), 'confirmed' => $c('orders', 'all'), 'settings' => $c('orders', 'all')];
  }
  /** null = editable, otherwise the sentence to show. */
  private function blocked($o, array $P): ?string {
    if (in_array($o->status, ['Shipment', 'Partial Delivered', 'Delivered', 'Late Delivery', 'Return Pending', 'Returned'])) return 'This order has already been shipped. Use Return / Replacement workflow instead.';
    if (!in_array($o->status, self::EDITABLE)) return 'An order in "'.$o->status.'" cannot be edited.';
    if (!$P['edit']) return 'Your role does not allow editing orders.';
    if (in_array($o->status, self::CONFIRMED) && !$P['confirmed']) return 'This order is already confirmed. Editing it needs the "Edit Confirmed Order" permission.';
    return null;
  }
  private function items($id) {
    return DB::table('order_items as i')->leftJoin('products as p', 'p.id', '=', 'i.product_id')->where('i.order_id', $id)->orderBy('i.id')
      ->select('i.*', 'p.sku as sku', 'p.stock as stock', DB::raw('coalesce(p.image,(select pp.image from products pp where pp.name = i.name limit 1)) as image'))->get();
  }
  public function form(Request $r, $id) {
    $o = DB::table('orders')->find($id); abort_unless($o, 404); $P = $this->perms();
    $rows = $this->items($id)->map(fn($i) => ['item_id' => $i->id, 'product_id' => $i->product_id, 'variant_id' => null, 'name' => $i->name, 'sku' => $i->sku, 'qty' => (int)$i->qty, 'price' => (float)$i->price, 'base' => (float)$i->price, 'image' => $i->image, 'stock' => $i->stock, 'orig_name' => $i->name, 'orig_qty' => (int)$i->qty, 'orig_price' => (float)$i->price])->values();
    $manualShip = ($o->delivery_manual_adj ?? null) !== null || stripos((string)($o->delivery_zone ?? ''), 'express') !== false;
    // Never add or change a discount silently: only an existing automatic discount is recalculated by default; otherwise the original discount stays until staff chooses otherwise.
    $suggestDisc = ((float)($o->discount ?? 0) > 0 && (string)($o->discount_by ?? '') === 'Automatic' && (string)($o->discount_by ?? '') !== 'Manual' && stripos((string)($o->discount_source ?? ''), 'referral') === false && stripos((string)($o->discount_type ?? ''), 'referral') === false) ? 'auto' : 'keep';
    return view('order_edit', ['o'=>$o, 'rows'=>$rows, 'P'=>$P, 'block'=>$this->blocked($o, $P), 'reasons'=>self::REASONS, 'smsOn'=>(DB::table('settings')->where('key', 'order_update_sms')->value('value') ?? '0') === '1',
      'suggestDisc'=>$suggestDisc, 'suggestShip'=>$manualShip ? 'keep' : 'auto', 'call'=>(int)$r->query('call', 0), 'courier'=>!empty($o->consignment_id)]);
  }
  /** Products for "Add / Replace": by name, SKU, barcode or product code. */
  public function products(Request $r) {
    $q = trim((string)$r->query('q', '')); if (mb_strlen($q) < 1) return response()->json([]);
    $like = '%'.str_replace(['%', '_'], '', $q).'%';
    $ps = DB::table('products')->where(fn($w) => $w->where('name', 'ilike', $like)->orWhere('sku', 'ilike', $like)->orWhere('barcode', 'ilike', $like)->orWhere('ext_id', 'ilike', $like))->orderByRaw('published desc')->limit(10)->get(['id', 'name', 'sku', 'barcode', 'price', 'stock', 'image']);
    $vs = $ps->count() ? DB::table('product_variants')->whereIn('product_id', $ps->pluck('id'))->get()->groupBy('product_id') : collect();
    return response()->json($ps->map(fn($p) => ['id'=>$p->id, 'name'=>$p->name, 'sku'=>$p->sku, 'barcode'=>$p->barcode, 'price'=>(float)$p->price, 'stock'=>(int)$p->stock, 'image'=>$p->image,
      'variants'=>($vs[$p->id] ?? collect())->map(function ($v) use ($p) { $o = is_string($v->options) ? json_decode($v->options, true) : (array)$v->options; $lab = $o ? implode(' / ', array_map(fn($k, $x) => "$k: $x", array_keys($o), $o)) : ($v->sku ?: 'Variant'); return ['id'=>$v->id, 'label'=>$lab, 'price'=>(float)($v->price ?? $p->price), 'stock'=>(int)$v->stock, 'sku'=>$v->sku]; })->values()->all()])->values());
  }
  private function norm($raw): array {
    $out = [];
    foreach ((array)$raw as $x) {
      if (!is_array($x)) continue;
      $out[] = ['item_id'=>!empty($x['item_id']) ? (int)$x['item_id'] : null, 'product_id'=>!empty($x['product_id']) ? (int)$x['product_id'] : null, 'variant_id'=>!empty($x['variant_id']) ? (int)$x['variant_id'] : null,
        'name'=>mb_substr(trim((string)($x['name'] ?? '')), 0, 250), 'qty'=>(int)($x['qty'] ?? 0), 'price'=>round((float)($x['price'] ?? 0), 2), 'price_reason'=>mb_substr(trim((string)($x['price_reason'] ?? '')), 0, 200)];
    }
    return $out;
  }
  /** The single calculation used by the live quote, the save and the later "apply pending revision". */
  private function calc($o, array $in, array $opt, array $P): array {
    $old = DB::table('order_items')->where('order_id', $o->id)->orderBy('id')->get()->keyBy('id'); $rows = []; $seen = []; $errs = []; $warn = [];
    foreach ($in as $x) {
      if ($x['qty'] < 1) { $errs[] = '"'.$x['name'].'": quantity must be at least 1 (use Remove to take it out).'; continue; }
      if ($x['name'] === '') { $errs[] = 'A product has no name.'; continue; }
      if ($x['price'] < 0) { $errs[] = '"'.$x['name'].'": invalid price.'; continue; }
      $oi = ($x['item_id'] && isset($old[$x['item_id']])) ? $old[$x['item_id']] : null; if ($oi) $seen[$oi->id] = 1;
      $pid = $x['product_id']; $cur = null;
      if ($pid) { $p = DB::table('products')->where('id', $pid)->first(['price', 'stock', 'name']); $cur = $p ? (float)$p->price : null; $stock = $p ? (int)$p->stock : null;
        if ($x['variant_id']) { $v = DB::table('product_variants')->where('id', $x['variant_id'])->where('product_id', $pid)->first(); if ($v) { if ($v->price !== null) $cur = (float)$v->price; $stock = (int)$v->stock; } }
        if (isset($stock) && $x['qty'] > $stock) $warn[] = '"'.$x['name'].'": '.$x['qty'].' ordered but only '.$stock.' in stock.'; }
      $same = $oi && (int)$oi->product_id === (int)$pid && $oi->name === $x['name'];
      if ($oi && $same) { $base = (float)$oi->price; $act = ($x['qty'] != $oi->qty && abs($x['price'] - $oi->price) > 0.009) ? 'updated' : ($x['qty'] != $oi->qty ? 'qty' : (abs($x['price'] - $oi->price) > 0.009 ? 'price' : 'kept')); }
      elseif ($oi) { $base = $cur ?? $x['price']; $act = 'replaced'; }
      else { $base = $cur ?? $x['price']; $act = 'added'; }
      $priceChanged = abs($x['price'] - $base) > 0.009;
      if ($priceChanged) {
        if (!$P['price']) $errs[] = '"'.$x['name'].'": you do not have permission to change the price.';
        elseif ($x['price_reason'] === '') $errs[] = '"'.$x['name'].'": give a reason for the price change.';
      }
      $rows[] = ['item_id'=>$oi->id ?? null, 'product_id'=>$pid, 'variant_id'=>$x['variant_id'], 'name'=>$x['name'], 'action'=>$act, 'old_name'=>$oi->name ?? null, 'old_qty'=>$oi ? (int)$oi->qty : null, 'new_qty'=>$x['qty'], 'old_price'=>$oi ? (float)$oi->price : null, 'new_price'=>$x['price'],
        'base'=>$base, 'price_changed'=>$priceChanged, 'price_reason'=>$x['price_reason'], 'old_total'=>$oi ? round($oi->qty * $oi->price, 2) : null, 'new_total'=>round($x['qty'] * $x['price'], 2)];
    }
    foreach ($old as $oi) if (!isset($seen[$oi->id])) $rows[] = ['item_id'=>$oi->id, 'product_id'=>$oi->product_id, 'variant_id'=>null, 'name'=>$oi->name, 'action'=>'removed', 'old_name'=>$oi->name, 'old_qty'=>(int)$oi->qty, 'new_qty'=>0, 'old_price'=>(float)$oi->price, 'new_price'=>(float)$oi->price, 'base'=>(float)$oi->price, 'price_changed'=>false, 'price_reason'=>'', 'old_total'=>round($oi->qty * $oi->price, 2), 'new_total'=>0];
    $live = array_values(array_filter($rows, fn($r) => $r['action'] !== 'removed')); if (!$live) $errs[] = 'An order needs at least one product. Cancel the order instead of removing everything.';
    $sub = round(array_sum(array_column($live, 'new_total')), 2); $qtyTot = array_sum(array_column($live, 'new_qty')); $oldSub = round((float)$old->sum(fn($i) => $i->qty * $i->price), 2);
    // ---- discount
    $dmode = in_array($opt['discount_mode'] ?? 'auto', ['auto', 'keep', 'manual']) ? $opt['discount_mode'] : 'auto'; $oldDisc = round((float)($o->discount ?? 0), 2);
    $auto = OrderDiscount::compute($o->phone, $sub, $o->coupon, ['qty'=>$qtyTot, 'exclude_order'=>$o->id, 'items'=>array_map(fn($x) => ['product_id'=>$x['product_id'], 'price'=>$x['new_price'], 'qty'=>$x['new_qty']], $live)]);
    $kept = array_values(array_filter($auto['lines'], fn($l) => $l['applied'])); $autoDisc = min($sub, round(array_sum(array_column($kept, 'amount')), 2)); $dmeta = null; $dnote = null;
    if ($dmode === 'auto') { $disc = $autoDisc; $dmeta = OrderDiscount::meta($kept, $auto['profile'], 'Automatic', $disc); if ($disc <= 0) $dmeta['discount_by'] = null; $dnote = $kept ? 'Recalculated: '.implode(' + ', array_column($kept, 'label')) : 'Recalculated: no discount applies'; }
    elseif ($dmode === 'keep') { $disc = min($sub, $oldDisc); $dnote = 'Original discount kept (৳'.number_format($disc).')'; }
    else {
      if (!$P['discount']) { $errs[] = 'You do not have permission to change the discount manually.'; $disc = min($sub, $oldDisc); }
      else { $disc = max(0, min($sub, round((float)($opt['discount_manual'] ?? 0), 2))); $dnote = 'Manual discount ৳'.number_format($disc).' by '.Auth::user()->name; $dmeta = ['discount_type'=>'Manual Discount', 'discount_rule'=>'Admin override (order revision)', 'discount_pct'=>null, 'discount_source'=>'Manual by '.Auth::user()->name, 'discount_by'=>'Manual', 'customer_category'=>$auto['profile']['category'] ?? null, 'membership_status'=>null];
        if (OrderDiscount::hasLines()) $dmeta['discount_lines'] = OrderDiscount::linesJson([OrderDiscount::manualLine($disc, Auth::user()->name, trim((string)($opt['discount_reason'] ?? '')) ?: 'Order revision override')]); }
    }
    if ($auto['coupon_error'] && $o->coupon && $dmode === 'auto') $warn[] = 'Coupon '.$o->coupon.': '.$auto['coupon_error'].' (no longer applied).';
    // ---- delivery
    $pids = array_values(array_filter(array_column($live, 'product_id')));
    $dv = Delivery::compute((float)$sub, 0, (string)($o->address ?? ''), (string)($o->district ?? ''), $pids); $smode = in_array($opt['ship_mode'] ?? 'auto', ['auto', 'keep', 'override']) ? $opt['ship_mode'] : 'auto'; $oldShip = round((float)$o->shipping, 2); $dcols = null; $snote = null;
    if ($smode === 'keep') { $ship = $oldShip; $snote = 'Previous delivery charge kept'; }
    elseif ($smode === 'override') {
      if (!$P['delivery']) { $errs[] = 'You do not have permission to override the delivery charge.'; $ship = $oldShip; }
      else { $ship = max(0, round((float)($opt['ship_override'] ?? 0), 2)); if (trim((string)($opt['ship_reason'] ?? '')) === '') $errs[] = 'Give a reason for the delivery charge override.'; $snote = 'Manual override ৳'.number_format($ship).' (system ৳'.number_format($dv['charge']).'): '.mb_substr(trim((string)($opt['ship_reason'] ?? '')), 0, 150);
        $dcols = ['delivery_zone'=>mb_substr((string)$dv['zone'], 0, 160), 'delivery_original'=>$dv['original'], 'delivery_free_discount'=>$dv['free_discount'], 'delivery_manual_adj'=>round($ship - $dv['charge'], 2), 'delivery_manual_by'=>Auth::user()->name, 'delivery_note'=>$dv['reason'] ? mb_substr($dv['reason'], 0, 200) : null]; }
    } else { $ship = round((float)$dv['charge'], 2); $snote = 'Recalculated'.($dv['zone'] ? ' ('.$dv['zone'].')' : ''); $dcols = ['delivery_zone'=>mb_substr((string)$dv['zone'], 0, 160), 'delivery_original'=>$dv['original'], 'delivery_free_discount'=>$dv['free_discount'], 'delivery_manual_adj'=>null, 'delivery_manual_by'=>null, 'delivery_note'=>$dv['reason'] ? mb_substr($dv['reason'], 0, 200) : null]; }
    $total = round($sub - $disc + $ship, 2); $oldTotal = round((float)$o->total, 2);
    return ['voice_lines'=>($dmode === 'auto' ? array_values(array_filter($kept, fn($l) => ($l['key'] ?? '') === 'voice')) : []), 'rows'=>$rows, 'errors'=>$errs, 'warnings'=>$warn, 'old_sub'=>$oldSub, 'sub'=>$sub, 'old_disc'=>$oldDisc, 'disc'=>$disc, 'auto_disc'=>$autoDisc, 'disc_lines'=>array_map(fn($l) => ['label'=>$l['label'], 'amount'=>$l['amount'], 'applied'=>$l['applied']], $auto['lines']), 'disc_meta'=>$dmeta, 'disc_note'=>$dnote, 'dmode'=>$dmode,
      'old_ship'=>$oldShip, 'ship'=>$ship, 'auto_ship'=>round((float)$dv['charge'], 2), 'ship_note'=>$snote, 'smode'=>$smode, 'dcols'=>$dcols, 'old_total'=>$oldTotal, 'total'=>$total, 'diff'=>round($total - $oldTotal, 2), 'qty'=>$qtyTot];
  }
  public function quote(Request $r, $id) {
    $o = DB::table('orders')->find($id); abort_unless($o, 404); $P = $this->perms();
    $c = $this->calc($o, $this->norm($r->input('items')), $r->all(), $P); unset($c['disc_meta'], $c['dcols']);
    return response()->json($c);
  }
  private function summary(array $c): string {
    $p = [];
    foreach ($c['rows'] as $x) {
      if ($x['action'] === 'added') $p[] = 'Added: '.$x['name'].' × '.$x['new_qty'];
      elseif ($x['action'] === 'removed') $p[] = 'Removed: '.$x['name'];
      elseif ($x['action'] === 'replaced') $p[] = 'Replaced '.$x['old_name'].' → '.$x['name'];
      elseif ($x['action'] === 'qty' || $x['action'] === 'updated') $p[] = $x['name'].': Qty '.$x['old_qty'].' → '.$x['new_qty'];
      if (in_array($x['action'], ['price', 'updated'])) $p[] = $x['name'].': price ৳'.number_format($x['old_price']).' → ৳'.number_format($x['new_price']);
    }
    if (abs($c['disc'] - $c['old_disc']) > 0.009) $p[] = 'Discount ৳'.number_format($c['old_disc']).' → ৳'.number_format($c['disc']);
    if (abs($c['ship'] - $c['old_ship']) > 0.009) $p[] = 'Delivery ৳'.number_format($c['old_ship']).' → ৳'.number_format($c['ship']);
    return implode('; ', $p);
  }
  private function typeOf(array $c): string {
    $t = [];
    foreach ($c['rows'] as $x) { if ($x['action'] === 'replaced') $t['replace'] = 1; elseif (in_array($x['action'], ['added', 'removed'])) $t['items'] = 1; elseif (in_array($x['action'], ['qty', 'updated'])) $t['quantity'] = 1; if (in_array($x['action'], ['price', 'updated'])) $t['price'] = 1; }
    if (abs($c['disc'] - $c['old_disc']) > 0.009) $t['discount'] = 1; if (abs($c['ship'] - $c['old_ship']) > 0.009) $t['delivery'] = 1;
    return count($t) > 1 ? 'mixed' : (array_key_first($t) ?? 'none');
  }
  public function save(Request $r, $id) {
    $d = $r->validate(['mode'=>'required|in:save,confirm,pending', 'reason'=>'required|in:'.implode(',', self::REASONS), 'note'=>'nullable|string|max:1000', 'items'=>'required|array|min:1', 'call_log_id'=>'nullable|integer'], ['reason.required'=>'Select the reason for this order change.']);
    $o = DB::table('orders')->find($id); abort_unless($o, 404); $P = $this->perms();
    if ($m = $this->blocked($o, $P)) return back()->withInput()->withErrors(['rev'=>$m]);
    if ($d['reason'] === 'Other' && trim((string)($d['note'] ?? '')) === '') return back()->withInput()->withErrors(['rev'=>'"Other" needs a note.']);
    $conf = $r->boolean('customer_confirmed');
    if ($d['mode'] !== 'pending' && !$conf) return back()->withInput()->withErrors(['rev'=>'Tick "Customer confirmed this order change" — or use "Save as Pending Revision".']);
    $c = $this->calc($o, $this->norm($r->input('items')), $r->all(), $P);
    if ($c['errors']) return back()->withInput()->withErrors(['rev'=>implode(' ', $c['errors'])]);
    if ($this->typeOf($c) === 'none' && abs($c['diff']) < 0.01) return back()->withInput()->withErrors(['rev'=>'Nothing was changed.']);
    $call = !empty($d['call_log_id']) && DB::table('call_logs')->where('id', $d['call_log_id'])->exists() ? (int)$d['call_log_id'] : null;
    if ($d['mode'] === 'pending') {
      $rid = DB::table('order_revisions')->insertGetId($this->revRow($o, $c, $d, false, $call) + ['status'=>'pending', 'payload'=>json_encode(['items'=>$this->norm($r->input('items')), 'opt'=>$r->only(['discount_mode', 'discount_manual', 'ship_mode', 'ship_override', 'ship_reason']), 'reason'=>$d['reason'], 'note'=>$d['note'] ?? null], JSON_UNESCAPED_UNICODE), 'created_at'=>now(), 'updated_at'=>now()]);
      $this->itemRows($rid, $c); Procure::audit('order.revision.pending', $o->invoice, ['total'=>$c['old_total']], ['total'=>$c['total'], 'revision'=>$rid]);
      return redirect('/admin/orders/'.$id.'#revisions')->with('rev_ok', 'Saved as pending revision — the order itself is unchanged until you apply it.');
    }
    $send = $r->boolean('send_sms');
    $this->commit($o, $c, $d, $call, null, $d['mode'] === 'confirm', $send);
    return redirect('/admin/orders/'.$id.'#revisions')->with('rev_ok', 'Order updated. New total ৳'.number_format($c['total']).' (difference '.($c['diff'] >= 0 ? '+' : '−').'৳'.number_format(abs($c['diff'])).').');
  }
  private function revRow($o, array $c, array $d, bool $conf, ?int $call): array {
    return ['order_id'=>$o->id, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name, 'ip'=>request()->ip(), 'revision_type'=>$this->typeOf($c), 'old_subtotal'=>$c['old_sub'], 'new_subtotal'=>$c['sub'], 'old_discount'=>$c['old_disc'], 'new_discount'=>$c['disc'], 'old_delivery_charge'=>$c['old_ship'], 'new_delivery_charge'=>$c['ship'],
      'old_grand_total'=>$c['old_total'], 'new_grand_total'=>$c['total'], 'amount_difference'=>$c['diff'], 'reason'=>$d['reason'], 'note'=>$d['note'] ?? null, 'customer_confirmed'=>$conf, 'call_log_id'=>$call, 'courier_warning'=>!empty($o->consignment_id),
      'discount_note'=>$c['disc_note'], 'delivery_note'=>$c['ship_note']];
  }
  private function itemRows(int $rid, array $c): void {
    foreach ($c['rows'] as $x) { if ($x['action'] === 'kept') continue;
      DB::table('order_revision_items')->insert(['order_revision_id'=>$rid, 'order_item_id'=>$x['item_id'], 'product_id'=>$x['product_id'], 'name'=>mb_substr($x['name'], 0, 250), 'action'=>$x['action'], 'old_quantity'=>$x['old_qty'], 'new_quantity'=>$x['new_qty'], 'old_price'=>$x['old_price'], 'new_price'=>$x['new_price'], 'old_total'=>$x['old_total'], 'new_total'=>$x['new_total'],
        'metadata'=>json_encode(['old_name'=>$x['old_name'], 'price_reason'=>$x['price_reason'] ?: null, 'base_price'=>$x['base']], JSON_UNESCAPED_UNICODE)]); }
  }
  /** Applies a calculated revision to the order inside one transaction. $existing = id of a pending revision being applied. */
  private function commit($o, array $c, array $d, ?int $call, ?int $existing, bool $confirmOrder, bool $sendSms): void {
    DB::transaction(function () use ($o, $c, $d, $call, $existing, $confirmOrder, $sendSms) {
      $fresh = DB::table('orders')->where('id', $o->id)->lockForUpdate()->first(); abort_unless($fresh && $fresh->status === $o->status, 409, 'The order changed while you were editing. Reload and try again.');
      $row = $this->revRow($fresh, $c, $d, true, $call) + ['status'=>'applied', 'applied_at'=>now(), 'updated_at'=>now()];
      if ($existing) { DB::table('order_revisions')->where('id', $existing)->update($row); DB::table('order_revision_items')->where('order_revision_id', $existing)->delete(); $rid = $existing; }
      else $rid = DB::table('order_revisions')->insertGetId($row + ['created_at'=>now()]);
      $this->itemRows($rid, $c);
      foreach ($c['rows'] as $x) {
        if ($x['action'] === 'removed') DB::table('order_items')->where('id', $x['item_id'])->delete();
        elseif ($x['action'] === 'added') DB::table('order_items')->insert(['order_id'=>$o->id, 'product_id'=>$x['product_id'], 'name'=>$x['name'], 'qty'=>$x['new_qty'], 'price'=>$x['new_price'], 'created_at'=>now(), 'updated_at'=>now()]);
        elseif ($x['action'] !== 'kept') DB::table('order_items')->where('id', $x['item_id'])->update(['product_id'=>$x['product_id'], 'name'=>$x['name'], 'qty'=>$x['new_qty'], 'price'=>$x['new_price'], 'updated_at'=>now()]);
      }
      if (!empty($c['voice_lines'])) \App\Support\Voice::onOrder((string)$o->phone, (int)$o->id, $c['voice_lines']);
      $upd = ['total'=>$c['total'], 'discount'=>$c['disc'], 'shipping'=>$c['ship'], 'updated_at'=>now()];
      if ($c['disc_meta']) $upd += $c['disc_meta']; if (OrderDiscount::hasLines() && $c['disc'] <= 0 && $c['dmode'] !== 'keep') $upd += ['discount_lines'=>null]; if ($c['disc'] <= 0 && $c['dmode'] !== 'keep') $upd += ['discount_by'=>null];
      if ($c['dcols']) $upd += $c['dcols'];
      DB::table('orders')->where('id', $o->id)->update($upd);
      if ($c['dmode'] !== 'keep' && OrderDiscount::hasLines()) { try { OrderDiscount::logChange((int)$o->id, OrderDiscount::lines($o), isset($upd['discount_lines']) && $upd['discount_lines'] ? json_decode($upd['discount_lines'], true) : []); } catch (\Throwable $e) {} }
      if (abs($c['disc'] - $c['old_disc']) > 0.009) DB::table('order_discount_logs')->insert(['order_id'=>$o->id, 'kind'=>$c['dmode'] === 'manual' ? 'override' : 'applied', 'label'=>mb_substr('Order revision: '.($c['disc_note'] ?? ''), 0, 200), 'prev_amount'=>$c['old_disc'], 'new_amount'=>$c['disc'], 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name, 'created_at'=>now(), 'updated_at'=>now()]);
      $sum = $this->summary($c);
      DB::table('order_staff_comments')->insert(['order_id'=>$o->id, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name, 'category'=>'Order revision', 'body'=>mb_substr('Order changed ('.$d['reason'].'): '.$sum.' — total ৳'.number_format($c['old_total']).' → ৳'.number_format($c['total']).(!empty($d['note']) ? '. '.$d['note'] : '').(!empty($o->consignment_id) ? ' ⚠ Courier parcel already created — update it.' : ''), 0, 1000), 'created_at'=>now(), 'updated_at'=>now()]);
      Procure::audit('order.revise', $o->invoice, ['total'=>$c['old_total'], 'discount'=>$c['old_disc'], 'shipping'=>$c['old_ship'], 'subtotal'=>$c['old_sub']], ['total'=>$c['total'], 'discount'=>$c['disc'], 'shipping'=>$c['ship'], 'subtotal'=>$c['sub'], 'revision'=>$rid, 'reason'=>$d['reason'], 'note'=>$d['note'] ?? null, 'changes'=>$sum]);
      if ($confirmOrder && in_array($o->status, self::OPEN)) {
        DB::table('orders')->where('id', $o->id)->update(['status'=>'Approved', 'updated_at'=>now()]);
        DB::table('order_status_logs')->insert(['order_id'=>$o->id, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name, 'from_status'=>$o->status, 'to_status'=>'Approved', 'note'=>'Confirmed with order revision #'.$rid, 'created_at'=>now(), 'updated_at'=>now()]);
      }
      if ($call) DB::table('call_logs')->where('id', $call)->update(['order_id'=>$o->id, 'updated_at'=>now()]);
      if ($sendSms && (DB::table('settings')->where('key', 'order_update_sms')->value('value') ?? '0') === '1' && $fresh->phone) {
        try { \App\Support\Sms::queue(\App\Support\Chat::phone($fresh->phone) ?: $fresh->phone, 'DutyFreeBD: Your order '.$fresh->invoice.' has been updated. Updated payable amount: Tk '.number_format($c['total']).'. Our representative has confirmed the changes with you.', 'order_updated', ['order_id'=>$o->id]); DB::table('order_revisions')->where('id', $rid)->update(['sms_sent'=>true]); } catch (\Throwable $e) {}
      }
    });
  }
  /** Apply a pending revision after the customer has confirmed. Recalculated against the order as it is now. */
  public function apply(Request $r, $id, $rid) {
    $rev = DB::table('order_revisions')->where('id', $rid)->where('order_id', $id)->where('status', 'pending')->first(); abort_unless($rev, 404);
    $o = DB::table('orders')->find($id); $P = $this->perms();
    if ($m = $this->blocked($o, $P)) return back()->withErrors(['rev'=>$m]);
    if (!$r->boolean('customer_confirmed')) return back()->withErrors(['rev'=>'Tick "Customer confirmed this order change" to apply the pending revision.']);
    $pl = json_decode($rev->payload, true) ?: []; $c = $this->calc($o, $pl['items'] ?? [], $pl['opt'] ?? [], $P);
    if ($c['errors']) return back()->withErrors(['rev'=>implode(' ', $c['errors'])]);
    $this->commit($o, $c, ['reason'=>$pl['reason'] ?? $rev->reason, 'note'=>$pl['note'] ?? $rev->note], $rev->call_log_id, (int)$rid, false, $r->boolean('send_sms'));
    return redirect('/admin/orders/'.$id.'#revisions')->with('rev_ok', 'Pending revision applied. New total ৳'.number_format($c['total']).'.');
  }
  public function discard(Request $r, $id, $rid) {
    $n = DB::table('order_revisions')->where('id', $rid)->where('order_id', $id)->where('status', 'pending')->update(['status'=>'discarded', 'updated_at'=>now()]); abort_unless($n, 404);
    Procure::audit('order.revision.discard', 'order#'.$id, null, ['revision'=>$rid]); return redirect('/admin/orders/'.$id.'#revisions')->with('rev_ok', 'Pending revision discarded.');
  }
  /** Quick Edit (small popups on the order page). Same calculation, permissions, revision history and audit as the full editor. JSON in / JSON out. */
  public function quick(Request $r, $id) {
    $err = fn(string $m) => response()->json(['ok'=>false, 'error'=>$m], 422);
    $o = DB::table('orders')->find($id); abort_unless($o, 404); $P = $this->perms();
    if ($m = $this->blocked($o, $P)) return $err($m);
    $kind = (string)$r->input('kind'); if (!in_array($kind, ['qty', 'products', 'discount', 'delivery', 'amount'], true)) return $err('Unknown quick edit.');
    if (!$r->boolean('customer_confirmed')) return $err('Tick "Customer confirmed this change" first.');
    $note = mb_substr(trim((string)$r->input('note', '')), 0, 500);
    if (in_array($kind, ['discount', 'delivery', 'amount'], true) && $note === '') return $err('Please write the reason.');
    $cur = $this->items($id)->map(fn($i) => ['item_id'=>$i->id, 'product_id'=>$i->product_id, 'variant_id'=>null, 'name'=>$i->name, 'qty'=>(int)$i->qty, 'price'=>(float)$i->price, 'price_reason'=>''])->all();
    $manualShip = ($o->delivery_manual_adj ?? null) !== null || stripos((string)($o->delivery_zone ?? ''), 'express') !== false;
    $keepDisc = !((float)($o->discount ?? 0) > 0 && (string)($o->discount_by ?? '') === 'Automatic' && stripos((string)($o->discount_source ?? ''), 'referral') === false && stripos((string)($o->discount_type ?? ''), 'referral') === false);
    $opt = ['discount_mode'=>$keepDisc ? 'keep' : 'auto', 'ship_mode'=>$manualShip ? 'keep' : 'auto'];
    $reason = 'Other';
    if ($kind === 'qty') {
      $q = (array)$r->input('qty', []); foreach ($cur as &$x) if (isset($q[$x['item_id']])) $x['qty'] = max(0, (int)$q[$x['item_id']]); unset($x); $reason = 'Quantity changed';
    } elseif ($kind === 'products') {
      $cur = $this->norm($r->input('items')); foreach ($cur as &$x) $x['price_reason'] = $note !== '' ? $note : 'Quick edit'; unset($x);
      $reason = in_array((string)$r->input('reason'), self::REASONS, true) ? (string)$r->input('reason') : 'Other';
    }
    if (in_array($kind, ['discount', 'amount'], true)) {
      $sub = round(array_sum(array_map(fn($x) => $x['qty'] * $x['price'], $cur)), 2);
      $val = round((float)$r->input('discount_value', 0), 2); $amt = $r->input('discount_type') === 'pct' ? round($sub * min(100, max(0, $val)) / 100, 2) : $val;
      if ($r->filled('discount_value') || $kind === 'discount') { $opt['discount_mode'] = 'manual'; $opt['discount_manual'] = $amt; } else $opt['discount_mode'] = 'keep';
      $reason = 'Discount correction';
    }
    if (in_array($kind, ['delivery', 'amount'], true)) {
      if ($r->filled('delivery_charge') || $kind === 'delivery') { $opt['ship_mode'] = 'override'; $opt['ship_override'] = (float)$r->input('delivery_charge', 0); $opt['ship_reason'] = $note; } else $opt['ship_mode'] = 'keep';
      $reason = $kind === 'delivery' ? 'Delivery charge correction' : $reason;
    }
    if ($kind === 'delivery') $opt['discount_mode'] = 'keep';
    $c = $this->calc($o, $this->norm($cur), $opt, $P);
    if ($c['errors']) return $err(implode(' ', $c['errors']));
    if ($this->typeOf($c) === 'none' && abs($c['diff']) < 0.01) return $err('Nothing was changed.');
    try { $this->commit($o, $c, ['reason'=>$reason, 'note'=>'Quick Edit'.($note !== '' ? ': '.$note : '')], null, null, false, false); }
    catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { return $err($e->getMessage() ?: 'The order changed while you were editing. Reload and try again.'); }
    $A = '\App\Support\OrderActivity';
    if (abs($c['disc'] - $c['old_disc']) > 0.009) $A::log((int)$o->id, 'discount_changed', '৳'.number_format($c['old_disc'], 2), '৳'.number_format($c['disc'], 2), $note);
    if (abs($c['ship'] - $c['old_ship']) > 0.009) $A::log((int)$o->id, 'delivery_charge_changed', '৳'.number_format($c['old_ship'], 2), '৳'.number_format($c['ship'], 2), $note);
    $sum = $this->summary($c); if (in_array($kind, ['qty', 'products'], true) && $sum !== '') $A::log((int)$o->id, $kind === 'qty' ? 'quantity_changed' : 'products_changed', '৳'.number_format($c['old_total'], 2), '৳'.number_format($c['total'], 2), mb_substr($sum, 0, 250));
    return response()->json(['ok'=>true, 'msg'=>'Saved. New total ৳'.number_format($c['total']).' (difference '.($c['diff'] >= 0 ? '+' : '−').'৳'.number_format(abs($c['diff'])).').']);
  }
  public function smsSetting(Request $r) {
    abort_unless($this->perms()['settings'], 403);
    DB::table('settings')->updateOrInsert(['key'=>'order_update_sms'], ['value'=>$r->boolean('on') ? '1' : '0', 'updated_at'=>now(), 'created_at'=>now()]);
    return back()->with('rev_ok', 'Order update SMS is now '.($r->boolean('on') ? 'ON' : 'OFF').'.');
  }
}
