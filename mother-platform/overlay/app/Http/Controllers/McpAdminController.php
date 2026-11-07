<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as P;
use App\Support\Mcp;
use App\Support\Procure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Admin-only: MCP on/off kill switch, token create/revoke, call log. */
class McpAdminController extends Controller {
  private function chk() { abort_unless(P::can(Auth::user(), 'users', 'view'), 403); abort_unless(Mcp::ready(), 503, 'MCP tables are not ready yet'); }
  public function index() {
    $this->chk();
    return view('mcp_admin', ['on' => Mcp::enabled(), 'tokens' => DB::table('mcp_tokens')->orderByDesc('id')->get(), 'log' => DB::table('mcp_audit')->orderByDesc('id')->limit(60)->get(), 'tools' => array_keys(Mcp::tools()), 'url' => url('/api/mcp')]);
  }
  public function toggle(Request $r) { $this->chk(); Procure::put('mcp_enabled', $r->boolean('on') ? '1' : '0'); $this->log('mcp.toggle', $r->boolean('on') ? 'on' : 'off', $r); return back()->with('ok', 'MCP is now '.($r->boolean('on') ? 'ENABLED' : 'DISABLED').'.'); }
  public function create(Request $r) {
    $this->chk(); $d = $r->validate(['name' => 'required|string|max:120', 'days' => 'nullable|integer|min:1|max:365']);
    $raw = Mcp::newToken($d['name'], !empty($d['days']) ? now()->addDays((int)$d['days'])->toDateTimeString() : null, Auth::id()); $this->log('mcp.token.create', $d['name'], $r);
    return back()->with('ok', 'Token created. Copy it now — it is shown only once.')->with('newtoken', $raw);
  }
  public function revoke(Request $r, $id) { $this->chk(); DB::table('mcp_tokens')->where('id', $id)->update(['revoked_at' => now(), 'updated_at' => now()]); $this->log('mcp.token.revoke', '#'.$id, $r); return back()->with('ok', 'Token revoked.'); }
  private function log(string $a, string $s, Request $r): void { try { DB::table('audit_logs')->insert(['user_id' => Auth::id(), 'user_name' => Auth::user()->name, 'action' => $a, 'subject' => $s, 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {} }
}
