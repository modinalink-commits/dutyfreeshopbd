<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as P;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** OAuth 2.1 for the MCP connector: discovery, dynamic client registration, consent (staff login required), token (PKCE S256) and refresh. Read-only scope only. */
class McpOAuthController extends Controller {
  const SCOPES = ['mcp:read', 'offline_access'];
  const HOSTS = ['chatgpt.com', 'chat.openai.com', 'platform.openai.com', 'openai.com', 'claude.ai', 'claude.com'];
  private function iss(Request $r): string { return 'https://'.$r->getHost(); }
  private function json($d, int $code = 200) { return response()->json($d, $code)->header('Cache-Control', 'no-store'); }
  private function oerr(string $e, string $d, int $c = 400) { return $this->json(['error' => $e, 'error_description' => $d], $c); }
  public function meta(Request $r) {
    $i = $this->iss($r);
    return response()->json(['issuer' => $i, 'authorization_endpoint' => $i.'/admin/oauth/authorize', 'token_endpoint' => $i.'/api/oauth/token', 'registration_endpoint' => $i.'/api/oauth/register',
      'scopes_supported' => self::SCOPES, 'response_types_supported' => ['code'], 'grant_types_supported' => ['authorization_code', 'refresh_token'], 'code_challenge_methods_supported' => ['S256'],
      'token_endpoint_auth_methods_supported' => ['none'], 'service_documentation' => $i.'/admin/mcp'])->header('Cache-Control', 'public, max-age=300');
  }
  public function resource(Request $r) {
    $i = $this->iss($r);
    return response()->json(['resource' => $i.'/mcp', 'authorization_servers' => [$i], 'scopes_supported' => self::SCOPES, 'bearer_methods_supported' => ['header'], 'resource_name' => 'Mother Commerce MCP (read-only)'])->header('Cache-Control', 'public, max-age=300');
  }
  public function register(Request $r) {
    $b = $r->json()->all() ?: $r->all(); $uris = $b['redirect_uris'] ?? [];
    if (!is_array($uris) || !$uris || count($uris) > 10) return $this->oerr('invalid_redirect_uri', 'redirect_uris required');
    foreach ($uris as $u) { $p = parse_url((string)$u); $h = strtolower($p['host'] ?? ''); $ok = ($p['scheme'] ?? '') === 'https' && $h !== '' && collect(self::HOSTS)->contains(fn($x) => $h === $x || str_ends_with($h, '.'.$x));
      if (!$ok) return $this->oerr('invalid_redirect_uri', 'redirect host not allowed'); }
    $cid = 'mcpc_'.bin2hex(random_bytes(12)); $name = mb_substr(strip_tags((string)($b['client_name'] ?? 'MCP client')), 0, 160);
    DB::table('mcp_clients')->insert(['client_id' => $cid, 'name' => $name, 'redirect_uris' => json_encode(array_values($uris)), 'created_at' => now(), 'updated_at' => now()]);
    return $this->json(['client_id' => $cid, 'client_name' => $name, 'redirect_uris' => array_values($uris), 'grant_types' => ['authorization_code', 'refresh_token'], 'response_types' => ['code'], 'token_endpoint_auth_method' => 'none', 'scope' => implode(' ', self::SCOPES)], 201);
  }
  private function client(?string $id) { return $id ? DB::table('mcp_clients')->where('client_id', $id)->first() : null; }
  private function check(Request $r) {
    $c = $this->client((string)$r->input('client_id')); if (!$c) return [null, 'Unknown client'];
    if (!in_array((string)$r->input('redirect_uri'), json_decode($c->redirect_uris, true) ?: [], true)) return [null, 'redirect_uri is not registered'];
    if ($r->input('response_type') !== 'code') return [null, 'response_type must be code'];
    if (!$r->filled('code_challenge') || ($r->input('code_challenge_method') ?: 'S256') !== 'S256') return [null, 'PKCE S256 is required'];
    return [$c, null];
  }
  public function authorizeForm(Request $r) {
    abort_unless(P::can(Auth::user(), 'users', 'view'), 403, 'Only the admin can connect an AI client'); [$c, $e] = $this->check($r); if ($e) return response($e, 400);
    return view('mcp_consent', ['client' => $c, 'q' => $r->only('client_id', 'redirect_uri', 'response_type', 'code_challenge', 'code_challenge_method', 'state', 'scope')]);
  }
  public function authorizeDecide(Request $r) {
    abort_unless(P::can(Auth::user(), 'users', 'view'), 403); [$c, $e] = $this->check($r); if ($e) return response($e, 400);
    $u = (string)$r->input('redirect_uri'); $sep = str_contains($u, '?') ? '&' : '?'; $st = $r->filled('state') ? '&state='.rawurlencode((string)$r->input('state')) : '';
    if ($r->input('decision') !== 'allow') return redirect()->away($u.$sep.'error=access_denied'.$st);
    $code = bin2hex(random_bytes(24));
    DB::table('mcp_oauth_codes')->insert(['code_hash' => hash('sha256', $code), 'client_id' => $c->client_id, 'redirect_uri' => $u, 'code_challenge' => (string)$r->input('code_challenge'), 'scope' => 'mcp:read offline_access', 'user_id' => Auth::id(), 'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now()]);
    try { DB::table('audit_logs')->insert(['user_id' => Auth::id(), 'user_name' => Auth::user()->name, 'action' => 'mcp.oauth.allow', 'subject' => $c->name, 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return redirect()->away($u.$sep.'code='.$code.$st);
  }
  private function issue(string $cid, string $name, ?int $uid, string $scope) {
    $acc = 'mcp_'.bin2hex(random_bytes(24)); $ref = 'mcpr_'.bin2hex(random_bytes(24));
    DB::table('mcp_tokens')->insert(['name' => mb_substr('OAuth: '.$name, 0, 120), 'token_hash' => hash('sha256', $acc), 'token_hint' => substr($acc, 0, 6).'…'.substr($acc, -4), 'scope' => 'read', 'expires_at' => now()->addHour(), 'created_by' => $uid, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('mcp_refresh_tokens')->insert(['token_hash' => hash('sha256', $ref), 'client_id' => $cid, 'scope' => $scope, 'user_id' => $uid, 'expires_at' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now()]);
    return $this->json(['access_token' => $acc, 'token_type' => 'Bearer', 'expires_in' => 3600, 'refresh_token' => $ref, 'scope' => $scope]);
  }
  public function token(Request $r) {
    $g = (string)$r->input('grant_type'); $c = $this->client((string)$r->input('client_id')); if (!$c) return $this->oerr('invalid_client', 'unknown client', 401);
    if ($g === 'authorization_code') {
      $row = DB::table('mcp_oauth_codes')->where('code_hash', hash('sha256', (string)$r->input('code')))->first();
      if (!$row || $row->used_at || now()->gt($row->expires_at) || $row->client_id !== $c->client_id || $row->redirect_uri !== (string)$r->input('redirect_uri')) return $this->oerr('invalid_grant', 'code is invalid or expired');
      $v = (string)$r->input('code_verifier'); $calc = rtrim(strtr(base64_encode(hash('sha256', $v, true)), '+/', '-_'), '=');
      if ($v === '' || !hash_equals($row->code_challenge, $calc)) return $this->oerr('invalid_grant', 'PKCE verification failed');
      if (!DB::table('mcp_oauth_codes')->where('id', $row->id)->whereNull('used_at')->update(['used_at' => now()])) return $this->oerr('invalid_grant', 'code already used');
      return $this->issue($c->client_id, (string)$c->name, $row->user_id, (string)$row->scope);
    }
    if ($g === 'refresh_token') {
      $row = DB::table('mcp_refresh_tokens')->where('token_hash', hash('sha256', (string)$r->input('refresh_token')))->first();
      if (!$row || $row->revoked_at || now()->gt($row->expires_at) || $row->client_id !== $c->client_id) return $this->oerr('invalid_grant', 'refresh token is invalid or expired');
      if (!DB::table('mcp_refresh_tokens')->where('id', $row->id)->whereNull('revoked_at')->update(['revoked_at' => now()])) return $this->oerr('invalid_grant', 'refresh token already used');
      return $this->issue($c->client_id, (string)$c->name, $row->user_id, (string)$row->scope);
    }
    return $this->oerr('unsupported_grant_type', 'use authorization_code or refresh_token');
  }
}
