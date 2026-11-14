<?php
namespace App\Http\Controllers;
use App\Support\SinglePacket;
use Illuminate\Http\Request;

/** Admin page for the Single Packet twins: read-only preview, and the owner-approved bulk run (Admin only). */
class SinglePacketController extends Controller {
  public function page() { return view('single_packets', ['pv' => SinglePacket::preview(), 'isAdmin' => \App\Support\ProfitFloor::admin(), 'done' => session('sp_done')]); }
  public function run(Request $r) {
    abort_unless(\App\Support\ProfitFloor::admin(), 403, 'Only Admin can run this.');
    if (trim((string)$r->input('confirm')) !== 'YES') return back()->withErrors(['confirm' => 'Type YES to confirm.']);
    @set_time_limit(600);
    return redirect('/admin/single-packets')->with('sp_done', SinglePacket::run());
  }
}
