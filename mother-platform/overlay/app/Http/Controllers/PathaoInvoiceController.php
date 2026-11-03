<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as P;
use App\Support\PathaoInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
/** Pathao settlement invoices: import the invoice CSV, see what matched, what Pathao collected and what it paid out. */
class PathaoInvoiceController extends Controller {
  public function index(Request $r) {
    $inv = DB::table('pathao_invoices')->orderByDesc('invoice_date')->orderByDesc('id')->paginate(60);
    $sum = DB::table('pathao_invoices')->selectRaw('coalesce(sum(collected),0) c, coalesce(sum(fee),0) f, coalesce(sum(payout),0) p, coalesce(sum(lines),0) n, coalesce(sum(unmatched),0) u, coalesce(sum(flagged),0) fl')->first();
    $flag = DB::table('pathao_invoice_lines as l')->leftJoin('orders as o', 'o.id', '=', 'l.order_id')->whereIn('l.match_status', ['unmatched', 'mismatch', 'overpaid', 'error'])->orderByDesc('l.id')->limit(150)->get(['l.*', 'o.invoice as inv', 'o.customer_name as cname']);
    return view('pathao_invoices', ['inv' => $inv, 'sum' => $sum, 'flag' => $flag, 'can' => P::can(Auth::user(), 'couriers', 'edit')]);
  }
  public function show(Request $r, $no) {
    $i = DB::table('pathao_invoices')->where('invoice_no', $no)->first(); abort_unless($i, 404);
    $lines = DB::table('pathao_invoice_lines as l')->leftJoin('orders as o', 'o.id', '=', 'l.order_id')->where('l.invoice_no', $no)->orderBy('l.id')->get(['l.*', 'o.invoice as inv', 'o.status as ostatus', 'o.payment_status as pstatus']);
    return view('pathao_invoice_show', ['i' => $i, 'lines' => $lines]);
  }
  public function import(Request $r) {
    abort_unless(P::can(Auth::user(), 'couriers', 'edit'), 403);
    $d = $r->validate(['file' => 'nullable|file|max:10240', 'text' => 'nullable|string|max:3000000', 'invoice_no' => 'nullable|string|max:40']);
    $txt = $r->hasFile('file') ? (string)file_get_contents($r->file('file')->getRealPath()) : trim((string)($d['text'] ?? ''));
    if ($txt === '') return back()->with('bad', 'Choose the Pathao invoice CSV file (or paste its rows).');
    $p = PathaoInvoice::parse($txt); if ($p['error']) return back()->with('bad', $p['error']);
    $no = strtoupper(trim((string)($d['invoice_no'] ?? ''))) ?: ($p['invoice'] ?: '');
    if ($no === '' && $r->hasFile('file')) $no = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', pathinfo($r->file('file')->getClientOriginalName(), PATHINFO_FILENAME)));
    if (!preg_match('/^[A-Z0-9]{6,30}$/', $no)) return back()->with('bad', 'Type the Pathao invoice number (for example 021126DQZGCPT). It was not in the file.');
    $res = PathaoInvoice::import($no, $p['lines'], Auth::user()->name ?? 'Staff');
    $i = DB::table('pathao_invoices')->where('invoice_no', $no)->first();
    return redirect('/admin/courier/pathao-invoices/'.$no)->with('ok', 'Invoice '.$no.': '.$res['new'].' new line(s)'.($res['dup'] ? ', '.$res['dup'].' already imported' : '').'. Matched '.$i->matched.' of '.$i->lines.($i->unmatched ? ', '.$i->unmatched.' not matched' : '').($i->flagged ? ', '.$i->flagged.' to check' : '').'.');
  }
  /** Can our existing Pathao login read the merchant invoices API? Shows only status codes and field names, never tokens. */
  public function probe(Request $r) {
    abort_unless(P::can(Auth::user(), 'couriers', 'edit'), 403);
    $t = \App\Support\Pathao::token(); if (!$t) return back()->with('bad', 'Probe: could not get a Pathao token from the saved login.');
    $out = [];
    $paths = ['/api/v1/monetary/invoices/receivable', '/api/v1/monetary/invoices/receivable?page=1&limit=5', '/api/v1/monetary/invoices/021126DQZGCPT', '/api/v1/monetary/invoices/021126DQZGCPT/orders', '/api/v1/monetary/invoices/021126DQZGCPT/items', '/api/v1/monetary/invoice/021126DQZGCPT'];
    if ($r->filled('i') && isset($paths[(int)$r->input('i')])) $paths = [$paths[(int)$r->input('i')]];   // one endpoint per request keeps it under the gateway timeout
    if ($r->filled('p') && preg_match('#^/api/v1/monetary/invoices?/[A-Za-z0-9/_\-.?&=]{1,120}$#', (string)$r->input('p'))) $paths = [(string)$r->input('p')];   // custom read-only GET probe, monetary invoice paths only
    foreach ($paths as $p) {
      $o = ['http'=>['method'=>'GET', 'timeout'=>12, 'ignore_errors'=>true, 'header'=>"Accept: application/json\r\nAuthorization: Bearer $t\r\nUser-Agent: MotherCommerce/1.0\r\n"], 'ssl'=>['verify_peer'=>true]];
      $b = @file_get_contents('https://merchant.pathao.com'.$p, false, stream_context_create($o)); $code = 0; foreach ($http_response_header ?? [] as $l) if (preg_match('#^HTTP/\S+\s+(\d+)#', $l, $m)) $code = (int)$m[1];
      $j = json_decode((string)$b, true); $src = is_array($j) ? ($j['error']['description'] ?? $j['message'] ?? '') : 'not json';
      if (!is_array($j) && $code === 200 && $b !== false && $b !== '') $src = 'not json, '.strlen((string)$b).' bytes, header: '.mb_substr(strtok((string)$b, "\n") ?: '', 0, 220);
      $dd = is_array($j) ? ($j['data'] ?? $j) : []; $first = is_array($dd) && isset($dd[0]) && is_array($dd[0]) ? $dd[0] : (is_array($dd) && isset($dd['data'][0]) && is_array($dd['data'][0]) ? $dd['data'][0] : null);
      $out[] = $p.' → HTTP '.$code.($code === 200 && is_array($j) ? ' (fields: '.implode(', ', array_slice(array_keys(is_array($dd) ? $dd : []), 0, 12)).($first ? '; item: '.implode(', ', array_slice(array_keys($first), 0, 25)) : '').')' : ' ('.mb_substr((string)$src, 0, 300).')');
    }
    return back()->with('ok', 'Probe result: '.implode(' | ', $out));
  }
  public function rematch(Request $r) {
    abort_unless(P::can(Auth::user(), 'couriers', 'edit'), 403);
    $n = PathaoInvoice::settle(null, Auth::user()->name ?? 'Staff');
    foreach (DB::table('pathao_invoices')->pluck('invoice_no') as $no) PathaoInvoice::totals($no);
    return back()->with('ok', 'Re-checked unmatched lines: '.$n.' parcel(s) matched now.');
  }
}
