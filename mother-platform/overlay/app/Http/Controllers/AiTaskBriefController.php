<?php
namespace App\Http\Controllers;

use App\Support\AiTaskBrief as B;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** AI Brief Builder (page + JSON endpoints). Drafts and suggestions only: nothing here changes orders, payments, prices or sends messages. */
class AiTaskBriefController extends Controller
{
  private function who(): string { return auth()->user()->name ?? 'staff'; }
  private function audit(string $act, string $subject): void { try { DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => $this->who(), 'action' => $act, 'subject' => mb_substr($subject, 0, 240), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {} }
  private function data(Request $r): array { return B::clean($r->input('data')); }

  public function index(Request $r) {
    $ready = B::ready(); $cur = null; $versions = collect(); $list = collect(); $tpl = collect();
    if ($ready) {
      $list = DB::table('ai_task_briefs')->orderByDesc('updated_at')->limit(40)->get(['id', 'title', 'type', 'status', 'version', 'completeness', 'updated_by', 'updated_at']);
      $tpl = DB::table('ai_task_templates')->orderBy('name')->get(['id', 'name', 'type']);
      $id = (int)$r->query('id'); if ($id) { $cur = DB::table('ai_task_briefs')->find($id); if ($cur) $versions = DB::table('ai_task_brief_versions')->where('brief_id', $id)->orderByDesc('version')->get(['id', 'version', 'changes', 'by', 'created_at', 'tokens_in', 'tokens_out', DB::raw('(result is not null) as has_result')]); }
    }
    $data = $cur ? B::clean(json_decode((string)$cur->data, true)) : B::blank();
    $tplId = (int)$r->query('tpl'); if (!$cur && $tplId && $ready) { $t = DB::table('ai_task_templates')->find($tplId); if ($t) { $data = B::clean(json_decode((string)$t->data, true)); } }
    $typeRules = []; foreach (B::TYPES as $t) $typeRules[$t] = B::rulesFor($t);
    $res = $cur && $cur->result ? ['text' => $cur->result, 'status' => $cur->result_status, 'meta' => json_decode((string)$cur->result_meta, true) ?: [], 'accepted' => (bool)$cur->accepted] : null;
    return view('ai_task_brief', ['ready' => $ready, 'cur' => $cur, 'data' => $data, 'check' => B::check($data), 'versions' => $versions, 'list' => $list, 'tpl' => $tpl, 'typeRules' => $typeRules, 'res' => $res, 'hasKey' => (bool)\App\Support\Sms::secret('ai_key'), 'runsLeft' => max(0, B::DAILY_RUNS - B::runsToday((int)auth()->id()))]);
  }

  /** Save a draft; a new version is written only when something changed. */
  public function save(Request $r) {
    if (!B::ready()) return response()->json(['ok' => false, 'error' => 'Tables are not ready yet'], 503);
    $d = $this->data($r); $ck = B::check($d); $id = (int)$r->input('id'); $json = json_encode($d, JSON_UNESCAPED_UNICODE);
    if ($d['title'] === '') return response()->json(['ok' => false, 'error' => 'Brief Title is required before saving.'], 422);
    $row = $id ? DB::table('ai_task_briefs')->find($id) : null; $me = $this->who();
    if (!$row) {
      $id = DB::table('ai_task_briefs')->insertGetId(['title' => $d['title'], 'type' => $d['type'] ?: null, 'status' => 'Draft', 'version' => 1, 'data' => $json, 'completeness' => $ck['pct'], 'created_by' => $me, 'updated_by' => $me, 'created_at' => now(), 'updated_at' => now()]);
      DB::table('ai_task_brief_versions')->insert(['brief_id' => $id, 'version' => 1, 'data' => $json, 'changes' => 'Created', 'by' => $me, 'created_at' => now(), 'updated_at' => now()]);
      $this->audit('AI Brief created', $d['title']); return response()->json(['ok' => true, 'id' => $id, 'version' => 1, 'pct' => $ck['pct'], 'changed' => ['created']]);
    }
    $old = B::clean(json_decode((string)$row->data, true)); $ch = B::diff($old, $d); $ver = (int)$row->version;
    if ($ch) { $ver++; DB::table('ai_task_brief_versions')->insert(['brief_id' => $id, 'version' => $ver, 'data' => $json, 'changes' => 'Changed: ' . implode(', ', $ch), 'by' => $me, 'created_at' => now(), 'updated_at' => now()]); }
    DB::table('ai_task_briefs')->where('id', $id)->update(['title' => $d['title'], 'type' => $d['type'] ?: null, 'version' => $ver, 'data' => $json, 'completeness' => $ck['pct'], 'updated_by' => $me, 'updated_at' => now()]);
    return response()->json(['ok' => true, 'id' => $id, 'version' => $ver, 'pct' => $ck['pct'], 'changed' => $ch]);
  }

  public function preview(Request $r) {
    $d = $this->data($r); $ck = B::check($d); $snap = B::snapshot($d);
    return response()->json(['ok' => true, 'text' => B::render($d, $snap), 'check' => $ck, 'lookup_errors' => collect($snap)->pluck('facts.error')->filter()->values()]);
  }

  public function lookup(Request $r) {
    $k = (string)$r->query('kind'); abort_unless(in_array($k, B::ATTACH, true), 422);
    return response()->json(B::lookup($k, (string)$r->query('q')));
  }

  /** Suggest rewrites; nothing is applied until staff tick them. */
  public function improve(Request $r) {
    $d = $this->data($r); if ($d['objective'] === '' && $d['must_know'] === '' && $d['context'] === '') return response()->json(['ok' => false, 'error' => 'Write something in Objective, Context or Must Know first.'], 422);
    $uid = (int)auth()->id(); if (B::runsToday($uid) >= B::DAILY_RUNS) return response()->json(['ok' => false, 'error' => 'Daily AI limit reached (' . B::DAILY_RUNS . ').'], 429);
    B::countRun($uid); $x = B::improve($d); return response()->json($x, $x['ok'] ? 200 : 502);
  }

  /** Quality check, save a version, attach a fresh context snapshot, run, store. */
  public function run(Request $r) {
    if (!B::ready()) return response()->json(['ok' => false, 'error' => 'Tables are not ready yet'], 503);
    $d = $this->data($r); $ck = B::check($d);
    if ($ck['missing']) return response()->json(['ok' => false, 'error' => 'Required fields are missing: ' . implode(', ', $ck['missing']), 'check' => $ck], 422);
    $uid = (int)auth()->id(); if (B::runsToday($uid) >= B::DAILY_RUNS) return response()->json(['ok' => false, 'error' => 'Daily AI limit reached (' . B::DAILY_RUNS . ').'], 429);
    $s = $this->save($r); $sv = json_decode($s->getContent(), true); if (empty($sv['ok'])) return $s; $id = (int)$sv['id'];
    B::countRun($uid); $res = B::run($d); $me = $this->who(); $ver = (int)DB::table('ai_task_briefs')->where('id', $id)->value('version');
    DB::table('ai_task_brief_versions')->where('brief_id', $id)->where('version', $ver)->update(['result' => $res['ok'] ? $res['output'] : null, 'context' => json_encode($res['snapshot'] ?? [], JSON_UNESCAPED_UNICODE), 'model' => $res['model'] ?? null, 'tokens_in' => $res['in'] ?? 0, 'tokens_out' => $res['out'] ?? 0, 'updated_at' => now()]);
    if (!$res['ok']) { DB::table('ai_task_briefs')->where('id', $id)->update(['result_status' => 'error', 'result_meta' => json_encode(['error' => $res['error']]), 'updated_at' => now()]); $this->audit('AI Brief run failed', $d['title'] . ' · ' . $res['error']); return response()->json(['ok' => false, 'id' => $id, 'error' => 'The AI did not answer: ' . $res['error']], 502); }
    $meta = ['confidence' => $res['confidence'], 'missing' => $res['missing'], 'checks' => $res['checks'], 'model' => $res['model'], 'in' => $res['in'], 'out' => $res['out'], 'by' => $me, 'at' => now()->toDateTimeString(), 'version' => $ver];
    DB::table('ai_task_briefs')->where('id', $id)->update(['status' => 'Ran', 'result' => $res['output'], 'result_status' => $res['status'], 'result_meta' => json_encode($meta, JSON_UNESCAPED_UNICODE), 'accepted' => false, 'updated_at' => now()]);
    $this->audit('AI Brief run', $d['title'] . ' · v' . $ver . ' · ' . $res['status']);
    return response()->json(['ok' => true, 'id' => $id, 'version' => $ver, 'result' => ['text' => $res['output'], 'status' => $res['status'], 'meta' => $meta, 'accepted' => false]]);
  }

  /** Accept the result, or store the staff's edited text. */
  public function result(Request $r, int $id) {
    $row = DB::table('ai_task_briefs')->find($id); abort_unless($row, 404); $mode = (string)$r->input('mode');
    if ($mode === 'accept') { DB::table('ai_task_briefs')->where('id', $id)->update(['accepted' => true, 'status' => 'Accepted', 'updated_at' => now()]); $this->audit('AI Brief result accepted', $row->title); return response()->json(['ok' => true]); }
    if ($mode === 'edit') { $t = trim(mb_substr((string)$r->input('text'), 0, 20000)); if ($t === '') return response()->json(['ok' => false, 'error' => 'Empty text'], 422); DB::table('ai_task_briefs')->where('id', $id)->update(['result' => $t, 'accepted' => false, 'updated_at' => now()]); return response()->json(['ok' => true]); }
    abort(422);
  }

  public function version(int $id, int $v) {
    $row = DB::table('ai_task_brief_versions')->where('brief_id', $id)->where('version', $v)->first(); abort_unless($row, 404);
    return response()->json(['ok' => true, 'data' => B::clean(json_decode((string)$row->data, true)), 'result' => $row->result, 'version' => $row->version]);
  }

  public function template(Request $r) {
    if (!B::ready()) return response()->json(['ok' => false, 'error' => 'Tables are not ready yet'], 503);
    $d = $this->data($r); $name = trim(mb_substr((string)$r->input('name'), 0, 120)); if ($name === '') return response()->json(['ok' => false, 'error' => 'Template name is required'], 422);
    $id = DB::table('ai_task_templates')->insertGetId(['name' => $name, 'type' => $d['type'] ?: null, 'data' => json_encode($d, JSON_UNESCAPED_UNICODE), 'created_by' => $this->who(), 'created_at' => now(), 'updated_at' => now()]);
    $this->audit('AI Brief template saved', $name); return response()->json(['ok' => true, 'id' => $id]);
  }
}
