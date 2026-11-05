<?php
namespace App\Http\Controllers;

use App\Support\AiBrief;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** AI Brief (phase 1, read-only): the daily Bengali brief written by Claude from facts computed by the system. Admin / Super only. */
class AiBriefController extends Controller
{
  private function guard() { abort_unless(in_array(optional(auth()->user())->role, ['super', 'admin'], true), 403); }

  public function index(Request $r) {
    $this->guard();
    $ready = AiBrief::ready(); $rows = $ready ? DB::table('ai_briefs')->orderByDesc('id')->limit(30)->get(['id', 'kind', 'for_date', 'status', 'model', 'made_by', 'created_at', 'tokens_in', 'tokens_out', 'error']) : collect();
    $id = (int)$r->query('id'); $cur = $ready ? ($id ? DB::table('ai_briefs')->find($id) : DB::table('ai_briefs')->orderByDesc('id')->first()) : null;
    $F = $cur ? (json_decode((string)$cur->facts, true) ?: []) : [];
    return view('ai_brief', ['ready' => $ready, 'rows' => $rows, 'cur' => $cur, 'F' => $F, 'on' => AiBrief::on(), 'hour' => AiBrief::hour(), 'hasKey' => (bool)\App\Support\Sms::secret('ai_key'), 'model' => AiBrief::model(), 'target' => AiBrief::TARGET]);
  }

  /** Run now (costs one API call): at most once a minute. */
  public function run(Request $r) {
    $this->guard();
    if (!AiBrief::ready()) return back()->with('err', 'The AI Brief table is not ready yet. Please wait for the update to finish.');
    if (!Cache::add('ai_brief.manual', 1, 60)) return back()->with('err', 'A brief was just requested. Please wait a minute.');
    $row = AiBrief::generate('manual', auth()->user()->name ?? null);
    return redirect('/admin/ai-brief?id=' . ($row->id ?? 0))->with($row && $row->status === 'ok' ? 'ok' : 'err', $row && $row->status === 'ok' ? 'New AI brief is ready.' : 'The AI part did not answer (' . ($row->error ?? 'unknown') . '). Showing the system numbers only.');
  }

  public function settings(Request $r) {
    $this->guard();
    AiBrief::put('ai_brief_on', $r->boolean('on') ? '1' : '0');
    AiBrief::put('ai_brief_hour', (string)max(0, min(23, (int)$r->input('hour', 8))));
    try { DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name ?? null, 'action' => 'AI Brief settings changed', 'subject' => ($r->boolean('on') ? 'ON' : 'OFF') . ' · hour ' . (int)$r->input('hour', 8), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return back()->with('ok', 'Saved');
  }
}
