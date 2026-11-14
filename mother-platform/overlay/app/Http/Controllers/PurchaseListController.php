<?php
namespace App\Http\Controllers;
use App\Support\Procure;
use App\Support\ProfitFloor;
use App\Support\PurchaseList as L;
use App\Support\WhatsApp;
use Illuminate\Http\Request;

/** Daily purchase list: preview, settings, WhatsApp template and test send (admin), plus the private PDF link the WhatsApp button opens. */
class PurchaseListController extends Controller {
  public function index() {
    $d = L::data();
    return view('purchase_list', ['d' => $d, 's' => fn($k, $df = '') => L::set($k, $df), 'tplStatus' => L::set('tpl_status', 'NOT SUBMITTED'), 'body' => L::BODY, 'rcp' => L::recipients(), 'admin' => ProfitFloor::admin()]);
  }
  /** Today's PDF for the signed-in staff member. */
  public function download() {
    return response(L::pdf(L::data()), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="purchase-list-' . now('Asia/Dhaka')->format('Y-m-d') . '.pdf"', 'Cache-Control' => 'no-store']);
  }
  public function save(Request $r) {
    abort_unless(ProfitFloor::admin(), 403);
    $d = $r->validate(['send_at' => ['required', 'regex:/^\d{1,2}:\d{2}$/'], 'cover_days' => 'required|integer|min:3|max:30', 'to' => 'required|string|max:600']);
    Procure::put('purchase_list_send_at', $d['send_at']); Procure::put('purchase_list_cover_days', (string)$d['cover_days']); Procure::put('purchase_list_to', trim($d['to']));
    Procure::put('purchase_list_enabled', $r->boolean('enabled') ? '1' : '0');
    return back()->with('ok', 'সেভ হয়েছে।');
  }
  public function submit() { abort_unless(ProfitFloor::admin(), 403); [$ok, $m] = L::submitTemplate(); return back()->with($ok ? 'ok' : 'err', 'Template: ' . $m); }
  public function sync() { abort_unless(ProfitFloor::admin(), 403); [$ok, $m] = L::syncTemplate(); return back()->with($ok ? 'ok' : 'err', 'Template status: ' . $m); }
  /** Send today's list now: to one test number, or to everyone on the list. */
  public function send(Request $r) {
    abort_unless(ProfitFloor::admin(), 403);
    $only = null;
    if ($r->filled('phone')) { $wa = WhatsApp::norm((string)$r->input('phone')); if (!preg_match('/^8801[3-9]\d{8}$/', $wa)) return back()->with('err', 'সঠিক মোবাইল নম্বর দিন।'); $only = [['Test', $wa]]; }
    return back()->with('ok', 'Purchase list: ' . implode(' | ', L::send($only)));
  }
  /** Public, but only reachable with the 40-character token from the WhatsApp message; stops working after four days. */
  public function open(string $token) {
    $f = L::path($token); abort_unless($f, 404);
    return response()->file($f, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="DutyFreeBD-purchase-list.pdf"', 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex']);
  }
}
