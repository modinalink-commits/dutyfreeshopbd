<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Command Board: the owner queues commands one after another; each is marked Completed from Claude's side when the work is finished. */
class CommandBoardController extends Controller
{
  public function index(Request $r) {
    $f = (string)$r->query('s', '');
    $rows = DB::table('claude_commands')->when(in_array($f, ['pending', 'working', 'done']), fn($q) => $q->where('status', $f))
      ->orderByRaw("case status when 'working' then 0 when 'pending' then 1 else 2 end")->orderByRaw("case priority when 'high' then 0 else 1 end")->orderByDesc('id')->limit(300)->get();
    $n = DB::table('claude_commands')->selectRaw("count(*) filter (where status='pending') p, count(*) filter (where status='working') w, count(*) filter (where status='done') d")->first();
    return view('command_board', ['rows' => $rows, 'n' => $n, 'f' => $f]);
  }
  public function store(Request $r) {
    $d = $r->validate(['title' => 'required|string|max:200', 'details' => 'nullable|string|max:4000', 'priority' => 'nullable|in:normal,high']);
    DB::table('claude_commands')->insert(['title' => $d['title'], 'details' => $d['details'] ?? null, 'priority' => $d['priority'] ?? 'normal', 'status' => 'pending', 'created_by' => Auth::id(), 'created_by_name' => Auth::user()->name ?? null, 'created_at' => now(), 'updated_at' => now()]);
    return redirect('/admin/commands')->with('ok', 'Command added to the queue');
  }
  public function update(Request $r, $id) {
    $d = $r->validate(['title' => 'required|string|max:200', 'details' => 'nullable|string|max:4000', 'priority' => 'nullable|in:normal,high']);
    DB::table('claude_commands')->where('id', $id)->where('status', '!=', 'done')->update(['title' => $d['title'], 'details' => $d['details'] ?? null, 'priority' => $d['priority'] ?? 'normal', 'updated_at' => now()]);
    return back()->with('ok', 'Saved');
  }
  /** Set by Claude: working / done (with a result note) — or "pending" to reopen. */
  public function status(Request $r, $id) {
    $d = $r->validate(['status' => 'required|in:pending,working,done', 'note' => 'nullable|string|max:2000']); $c = DB::table('claude_commands')->find($id); abort_unless($c, 404);
    $u = ['status' => $d['status'], 'updated_at' => now()];
    if ($d['status'] === 'working') $u += ['started_at' => $c->started_at ?: now(), 'done_at' => null];
    elseif ($d['status'] === 'done') $u += ['done_at' => now(), 'result_note' => $d['note'] ?? $c->result_note, 'started_at' => $c->started_at ?: now()];
    else $u += ['done_at' => null, 'result_note' => $d['note'] ?? $c->result_note];
    DB::table('claude_commands')->where('id', $id)->update($u);
    return $r->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', 'Updated');
  }
  public function destroy($id) { DB::table('claude_commands')->where('id', $id)->where('status', 'pending')->delete(); return back()->with('ok', 'Removed'); }
}
