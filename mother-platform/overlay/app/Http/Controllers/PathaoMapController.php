<?php
namespace App\Http\Controllers;
use App\Support\CourierSync;
use App\Support\PathaoStatusMapper as M;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/** Admin editor for the Pathao status map: what each status Pathao sends means for the order. Statuses we have never seen are added automatically and wait here for a decision. */
class PathaoMapController extends Controller {
  public function index() {
    $rows = DB::table('pathao_status_map')->orderByDesc('needs_mapping')->orderByRaw('last_seen_at desc nulls last')->orderBy('raw_label')->get();
    $flagged = (int)DB::table('orders')->whereNotNull('courier_flag')->count();
    return view('pathao_map', ['rows'=>$rows, 'actions'=>M::ACTIONS, 'outcomes'=>M::OUTCOMES, 'stages'=>CourierSync::STAGES, 'flagged'=>$flagged]);
  }
  private function audit(Request $r, string $a, string $s): void { DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'action'=>$a,'subject'=>$s,'ip'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]); }
  public function save(Request $r, $id) {
    $d = $r->validate(['outcome'=>'nullable|in:'.implode(',', M::OUTCOMES), 'action'=>'required|in:'.implode(',', array_keys(M::ACTIONS)), 'stage'=>'nullable|in:'.implode(',', CourierSync::STAGES), 'note'=>'nullable|string|max:500']);
    $row = DB::table('pathao_status_map')->find((int)$id); abort_unless($row, 404);
    DB::table('pathao_status_map')->where('id', $row->id)->update(['outcome'=>$d['outcome'] ?: null, 'action'=>$d['action'], 'stage'=>$d['stage'] ?: null, 'note'=>$d['note'] ?? null, 'needs_mapping'=>false, 'confirmed'=>true, 'updated_at'=>now()]);
    M::forget(); $this->audit($r, 'courier.status-map', $row->raw_label.' → '.(M::ACTIONS[$d['action']] ?? $d['action']));
    return back()->with('ok', '"'.$row->raw_label.'" saved. Orders pick it up on their next sync.');
  }
  public function add(Request $r) {
    $d = $r->validate(['raw_label'=>'required|string|max:160']); $k = M::key($d['raw_label']); if ($k === '') return back()->with('bad', 'Enter the status exactly as Pathao shows it.');
    DB::table('pathao_status_map')->insertOrIgnore(['raw_key'=>$k, 'raw_label'=>trim($d['raw_label']), 'action'=>'none', 'needs_mapping'=>true, 'confirmed'=>true, 'seen_count'=>0, 'note'=>'Added by hand. Choose what it means, then save.', 'created_at'=>now(), 'updated_at'=>now()]);
    M::forget(); return back()->with('ok', 'Added. Choose what it means and save.');
  }
  public function destroy(Request $r, $id) {
    $row = DB::table('pathao_status_map')->find((int)$id); abort_unless($row, 404);
    DB::table('pathao_status_map')->where('id', $row->id)->delete(); M::forget(); $this->audit($r, 'courier.status-map.delete', $row->raw_label);
    return back()->with('ok', 'Removed. If Pathao sends it again it will be listed as new.');
  }
  /** After mapping: make flagged parcels re-read their Pathao status on the next sync run. */
  public function recheck(Request $r) {
    $n = DB::table('orders')->whereNotNull('courier_flag')->update(['courier_synced_at'=>null]); M::forget();
    return back()->with('ok', "$n flagged parcel(s) will be re-read from Pathao on the next sync (within about 10 minutes, or press Sync now on the Courier Dashboard).");
  }
}
