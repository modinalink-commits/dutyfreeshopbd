<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Pathao settlement invoices: parse the invoice CSV (or a table copied from the panel), match every line to our parcel, store the real
 *  courier fee, and record what Pathao actually collected as a payment. Nothing is guessed: a line that cannot be matched stays "unmatched"
 *  and never touches an order. Re-importing the same invoice is safe (each line is stored once). */
class PathaoInvoice {
  const HEAD = ['date'=>['date'], 'consignment'=>['consignmentid','consignment','cn'], 'orderref'=>['orderid','merchantorderid','orderreference','order'], 'recipient'=>['recipient','recipientname','customer'], 'phone'=>['phone','recipientphone','mobile'],
    'type'=>['invoicetype','type'], 'collectable'=>['collectable','amounttocollect'], 'collected'=>['collected','collectedamount'], 'cod'=>['cod','codfee'], 'charge'=>['deliverycharge','deliveryfee','charge'], 'discount'=>['discount'], 'total'=>['total','totalfee','fee'], 'paidout'=>['paidout','payout','receivable'], 'invoice'=>['invoiceid','invoiceno','invoice']];
  public static function money($v): float {
    $s = trim((string)$v); if ($s === '') return 0.0; $neg = str_contains($s, '-') || (str_starts_with($s, '(') && str_ends_with($s, ')'));
    $s = preg_replace('/[^0-9.]/', '', str_replace(',', '', $s)); if ($s === '' || $s === '.') return 0.0; $n = (float)$s; return $neg ? -$n : $n;
  }
  private static function norm(string $h): string { return preg_replace('/[^a-z0-9]/', '', strtolower(trim($h, " \t\n\r\0\x0B\xEF\xBB\xBF\""))); }
  private static function date(?string $s): ?string {
    $s = trim((string)$s); if ($s === '') return null;
    if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})/', $s, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    $t = strtotime($s); return $t ? date('Y-m-d', $t) : null;
  }
  /** @return array{lines:array,invoice:?string,error:?string} */
  public static function parse(string $text): array {
    $text = str_replace("\r", '', $text); $rows = [];
    $first = strtok($text, "\n"); $delim = substr_count((string)$first, "\t") >= 3 ? "\t" : ',';
    $fh = fopen('php://temp', 'r+'); fwrite($fh, $text); rewind($fh);
    while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) { if ($r === [null] || !array_filter($r, fn($c) => trim((string)$c) !== '')) continue; $rows[] = $r; }
    fclose($fh);
    $hi = null; $map = [];
    foreach ($rows as $i => $r) { $m = [];
      foreach ($r as $ci => $h) { $n = self::norm((string)$h); foreach (self::HEAD as $k => $names) if (!isset($m[$k]) && in_array($n, $names, true)) $m[$k] = $ci; }
      if (isset($m['consignment'])) { $hi = $i; $map = $m; break; }
      if ($i > 15) break; }
    if ($hi === null) return ['lines'=>[], 'invoice'=>null, 'error'=>'Could not find a "Consignment ID" column. Upload the CSV exported from the Pathao invoice page, or paste the table with its header row.'];
    $g = fn(array $r, string $k) => isset($map[$k]) ? trim((string)($r[$map[$k]] ?? '')) : '';
    $out = []; $inv = null;
    foreach (array_slice($rows, $hi + 1) as $r) {
      $cid = strtoupper($g($r, 'consignment')); if ($cid === '' || preg_match('/^(TOTAL|SUM)/i', $cid)) continue;
      if (!$inv && $g($r, 'invoice') !== '') $inv = strtoupper($g($r, 'invoice'));
      $rec = $g($r, 'recipient'); $ph = trim($g($r, 'phone'), " \"'");
      if ($ph === '' && preg_match('/^(.*?)\s*\n\s*"?(\+?\d[\d\s-]{8,})"?\s*$/su', $rec, $mm)) { $rec = trim($mm[1]); $ph = preg_replace('/\D/', '', $mm[2]); }
      $out[] = ['date'=>self::date($g($r, 'date')), 'consignment'=>$cid, 'orderref'=>$g($r, 'orderref'), 'recipient'=>mb_substr($rec, 0, 160), 'phone'=>$ph, 'type'=>strtolower($g($r, 'type')) ?: 'delivery',
        'collectable'=>self::money($g($r, 'collectable')), 'collected'=>self::money($g($r, 'collected')), 'cod'=>self::money($g($r, 'cod')), 'charge'=>self::money($g($r, 'charge')), 'discount'=>self::money($g($r, 'discount')), 'total'=>self::money($g($r, 'total')), 'paidout'=>self::money($g($r, 'paidout'))];
    }
    return ['lines'=>$out, 'invoice'=>$inv, 'error'=>$out ? null : 'The file has a header but no parcel rows.'];
  }
  /** Our order for a line: by consignment first, then by the order id we sent Pathao (our invoice number). */
  public static function find(array $l): ?object {
    $o = DB::table('orders')->where('consignment_id', $l['consignment'])->first(); if ($o) return $o;
    $ref = trim((string)$l['orderref']); if ($ref === '') return null;
    return DB::table('orders')->whereIn('invoice', Inv::candidates($ref))->first() ?: null;
  }
  public static function import(string $invoiceNo, array $lines, string $by, ?string $status = null): array {
    $invoiceNo = strtoupper(trim($invoiceNo)); $new = 0; $dup = 0; $applied = 0;
    DB::transaction(function () use ($invoiceNo, $lines, $by, $status, &$new, &$dup, &$applied) {
      DB::table('pathao_invoices')->insertOrIgnore(['invoice_no'=>$invoiceNo, 'imported_by'=>$by, 'status'=>$status, 'created_at'=>now(), 'updated_at'=>now()]);
      foreach ($lines as $l) {
        $key = substr(sha1($invoiceNo.'|'.$l['consignment'].'|'.$l['type'].'|'.$l['collected'].'|'.$l['total'].'|'.$l['paidout'].'|'.$l['charge']), 0, 40);
        $ins = DB::table('pathao_invoice_lines')->insertOrIgnore(['invoice_no'=>$invoiceNo, 'line_key'=>$key, 'line_date'=>$l['date'], 'consignment_id'=>$l['consignment'], 'order_ref'=>$l['orderref'] ?: null, 'recipient'=>$l['recipient'], 'phone'=>$l['phone'], 'line_type'=>$l['type'],
          'collectable'=>$l['collectable'], 'collected'=>$l['collected'], 'cod_fee'=>$l['cod'], 'delivery_charge'=>$l['charge'], 'discount'=>$l['discount'], 'fee'=>$l['total'], 'paid_out'=>$l['paidout'], 'match_status'=>'unmatched', 'created_at'=>now(), 'updated_at'=>now()]);
        if ($ins) $new++; else $dup++;
      }
      $applied = self::settle($invoiceNo, $by);
    });
    self::totals($invoiceNo);
    return ['new'=>$new, 'dup'=>$dup, 'applied'=>$applied];
  }
  /** Match and apply every not-yet-applied line of an invoice (or of all invoices when null). */
  public static function settle(?string $invoiceNo, string $by): int {
    $q = DB::table('pathao_invoice_lines')->where('applied', false); if ($invoiceNo) $q->where('invoice_no', $invoiceNo); $n = 0;
    foreach ($q->orderBy('id')->limit(5000)->get() as $row) {
      $l = ['consignment'=>$row->consignment_id, 'orderref'=>$row->order_ref]; $o = self::find($l);
      if (!$o) { DB::table('pathao_invoice_lines')->where('id', $row->id)->update(['match_status'=>'unmatched', 'order_id'=>null, 'updated_at'=>now()]); continue; }
      try { if (self::apply($row, $o, $by)) $n++; } catch (\Throwable $e) { DB::table('pathao_invoice_lines')->where('id', $row->id)->update(['match_status'=>'error', 'note'=>mb_substr($e->getMessage(), 0, 250), 'updated_at'=>now()]); }
    }
    return $n;
  }
  private static function apply(object $row, object $o, string $by): bool {
    $note = null; $status = 'matched';
    if ($row->line_type === 'return') { DB::table('pathao_invoice_lines')->where('id', $row->id)->update(['order_id'=>$o->id, 'match_status'=>'matched', 'applied'=>true, 'note'=>'Return parcel line (no money)', 'updated_at'=>now()]); return true; }
    $collected = round((float)$row->collected, 2); $recorded = 0.0;
    if ($collected > 0) {
      $txn = 'PATHAO:'.$row->invoice_no.':'.$row->consignment_id;
      if (DB::table('order_payments')->where('txn_id', $txn)->exists()) { $recorded = (float)DB::table('order_payments')->where('txn_id', $txn)->value('amount'); }
      else {
        $paid = (float)DB::table('order_payments')->where('order_id', $o->id)->where('status', 'verified')->sum('amount'); $due = max(round((float)$o->total - $paid, 2), 0.0);
        $recorded = min($collected, $due);
        if ($recorded > 0) { DB::table('order_payments')->insert(['order_id'=>$o->id, 'method'=>'Pathao COD', 'amount'=>$recorded, 'txn_id'=>$txn, 'sender'=>'Pathao', 'paid_at'=>($row->line_date ?: now()), 'note'=>'COD collected by Pathao, invoice '.$row->invoice_no, 'status'=>'verified', 'received_by'=>'Pathao', 'created_by_name'=>'System (Pathao invoice)', 'verified_by'=>'System', 'verified_at'=>now(), 'created_at'=>now(), 'updated_at'=>now()]);
          \App\Http\Controllers\OrderPaymentController::recalc((int)$o->id); }
        if ($collected - $recorded > 0.99) { $status = 'overpaid'; $note = 'Pathao collected ৳'.number_format($collected).' but only ৳'.number_format($recorded).' was due: ৳'.number_format($collected - $recorded).' extra to check'; }
      }
    }
    if ($status === 'matched' && abs($collected - (float)$row->collectable) > 0.99 && $row->line_type === 'delivery') { $status = 'mismatch'; $note = 'Collected ৳'.number_format($collected).' vs collectable ৳'.number_format((float)$row->collectable); }
    $fee = (float)DB::table('pathao_invoice_lines')->where('order_id', $o->id)->where('line_type', '<>', 'return')->where('id', '<>', $row->id)->sum('fee') + (float)$row->fee;
    $col = (float)DB::table('pathao_invoice_lines')->where('order_id', $o->id)->where('line_type', 'delivery')->where('id', '<>', $row->id)->sum('collected') + ($row->line_type === 'delivery' ? $collected : 0);
    $out = (float)DB::table('pathao_invoice_lines')->where('order_id', $o->id)->where('id', '<>', $row->id)->sum('paid_out') + (float)$row->paid_out;
    $upd = ['pathao_invoice_no'=>$row->invoice_no, 'pathao_collected'=>$col, 'pathao_paid_out'=>$out, 'pathao_settled_at'=>($row->line_date ?: now()), 'updated_at'=>now()]; if ($fee > 0) $upd['courier_fee'] = $fee;
    DB::table('orders')->where('id', $o->id)->update($upd);
    DB::table('pathao_invoice_lines')->where('id', $row->id)->update(['order_id'=>$o->id, 'match_status'=>$status, 'applied'=>true, 'note'=>$note, 'updated_at'=>now()]);
    $prev = OrderActivity::$system; OrderActivity::$system = true;
    try { OrderActivity::log((int)$o->id, 'pathao_invoice', null, $row->invoice_no, 'Pathao invoice '.$row->invoice_no.': collected ৳'.number_format($collected).', courier fee ৳'.number_format((float)$row->fee, 2).', paid out ৳'.number_format((float)$row->paid_out, 2).($recorded > 0 ? ' — COD payment ৳'.number_format($recorded).' recorded' : '').($note ? ' ('.$note.')' : ''), []); } finally { OrderActivity::$system = $prev; }
    return true;
  }
  public static function totals(string $invoiceNo): void {
    $b = DB::table('pathao_invoice_lines')->where('invoice_no', $invoiceNo);
    $s = (clone $b)->selectRaw("count(*) n, count(*) filter (where order_id is not null) m, count(*) filter (where order_id is null) u, count(*) filter (where match_status in ('mismatch','overpaid','error')) f, coalesce(sum(collected),0) c, coalesce(sum(fee),0) fee, coalesce(sum(paid_out),0) p, min(line_date) d")->first();
    DB::table('pathao_invoices')->where('invoice_no', $invoiceNo)->update(['lines'=>$s->n, 'matched'=>$s->m, 'unmatched'=>$s->u, 'flagged'=>$s->f, 'collected'=>$s->c, 'fee'=>$s->fee, 'payout'=>$s->p, 'invoice_date'=>$s->d, 'updated_at'=>now()]);
  }
}
