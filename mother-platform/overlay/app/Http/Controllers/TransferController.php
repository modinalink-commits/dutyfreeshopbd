<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as P;
use App\Support\Transfers as T;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller {
  private function chk(string $need = 'view') { abort_unless(P::can(Auth::user(), 'accounts', $need), 403); abort_unless(T::ready(), 503, 'Transfers are not set up yet'); }
  public function index(Request $r) {
    $this->chk();
    $acc = DB::table('money_accounts')->where('active', true)->orderBy('name')->get(['id', 'name']);
    $bal = []; foreach ($acc as $a) $bal[$a->id] = T::balance($a->id);
    $q = DB::table('account_transfers')->orderByDesc('transfer_date')->orderByDesc('id');
    if ($r->filled('account_id')) $q->where(fn($w) => $w->where('from_account_id', (int)$r->account_id)->orWhere('to_account_id', (int)$r->account_id));
    if ($r->filled('from')) $q->where('transfer_date', '>=', $r->from); if ($r->filled('to')) $q->where('transfer_date', '<=', $r->to);
    $rows = $q->limit(300)->get();
    $names = $acc->pluck('name', 'id'); foreach (DB::table('money_accounts')->get(['id', 'name']) as $a) $names[$a->id] = $a->name;
    $sup = DB::table('supplier_payments as p')->join('suppliers as s', 's.id', '=', 'p.supplier_id')->whereNotNull('p.money_account_id')->where('p.status', 'paid')->orderByDesc('p.pay_date')->orderByDesc('p.id')->limit(100)->get(['p.code', 'p.pay_date', 'p.amount', 'p.money_account_id', 's.name as sname', 'p.txn_id']);
    return view('transfers', ['acc' => $acc, 'bal' => $bal, 'rows' => $rows, 'names' => $names, 'sup' => $sup, 'can' => P::can(Auth::user(), 'accounts', 'edit'), 'f' => $r->only('account_id', 'from', 'to')]);
  }
  public function store(Request $r) {
    $this->chk('edit');
    $d = $r->validate(['from_account_id' => 'required|integer|different:to_account_id|exists:money_accounts,id', 'to_account_id' => 'required|integer|exists:money_accounts,id', 'amount' => 'required|numeric|min:0.01', 'transfer_date' => 'required|date', 'note' => 'nullable|string|max:300']);
    if ($d['transfer_date'] > now('Asia/Dhaka')->toDateString()) return back()->withInput()->with('bad', 'Transfer date cannot be in the future.');
    $id = T::make((int)$d['from_account_id'], (int)$d['to_account_id'], round((float)$d['amount'], 2), $d['transfer_date'], $d['note'] ?? null, Auth::user()->name);
    return back()->with('ok', 'Transfer '.DB::table('account_transfers')->where('id', $id)->value('code').' recorded.');
  }
  public function void(Request $r, $id) {
    $this->chk('edit'); $ok = T::void((int)$id, Auth::user()->name); return back()->with($ok ? 'ok' : 'bad', $ok ? 'Voided.' : 'Already voided or not found.');
  }
}
