<?php
namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Marine Supply Access Management (owner brief 26 Nov 2026).
 * Three profile types, one per backend subtab: owner = Shipping Owners (request kind "company"), agency = Local Agencies ("agent"), vessel = Vessels ("crew").
 * Activation: staff approve -> email with the username and a single-use Set Password link (72 hours) -> the person presses "Send code" on that page ->
 * a 6-digit code goes to the same email (10 minutes, 5 tries) -> code + own password -> account Active. Opening the link changes nothing (mail scanners open links).
 * Resend Invite replaces the link and voids any code. Only hashes of links and codes are stored. Every email is recorded in marine_invites.
 * The portal lives on the store's own address (/marine-portal), like the supplier portal, so its session cookie is first-party.
 */
class MarineAccess
{
  const TYPES = ['owner' => 'Shipping Owners', 'agency' => 'Local Agencies', 'vessel' => 'Vessels'];
  const KIND = ['owner' => 'company', 'agency' => 'agent', 'vessel' => 'crew'];
  const PREFIX = ['owner' => 'OWN', 'agency' => 'AGT', 'vessel' => 'VSL'];
  const VROLES = ['captain' => 'Captain / Authorized Officer', 'crew' => 'Crew Member'];
  const PORTAL = 'https://dutyfreebd.com/marine-portal';
  const SITE = 'https://marinedutyfreebd.com';
  const INVITE_HOURS = 72; const OTP_MINUTES = 10; const OTP_TRIES = 5; const OTP_GAP = 60; const OTP_PER_LINK = 6;
  const DOC_EXT = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']];
  const DOC_MAX = 5242880; const DOC_COUNT = 4; const DOC_PER_REQUEST = 20;

  // ---------- pure helpers (covered by the test script) ----------
  public static function typeOf(?string $kind): string { return $kind === 'agent' ? 'agency' : ($kind === 'crew' ? 'vessel' : 'owner'); }
  public static function username(string $type, int $id): string { return (self::PREFIX[$type] ?? 'ACC') . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT); }
  public static function token(): string { return bin2hex(random_bytes(20)); }
  public static function tokenOk(?string $t): bool { return is_string($t) && preg_match('/^[a-f0-9]{40}$/', $t) === 1; }
  public static function th(string $token): string { return hash('sha256', $token); }
  public static function code(): string { return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT); }
  public static function codeOk(?string $c): bool { return is_string($c) && preg_match('/^\d{6}$/', $c) === 1; }
  /** Why a password is not accepted, or null when it is fine. Length over composition rules (OWASP): 10 to 128 characters, not the username or the email. */
  public static function passwordProblem(string $pw, string $pw2, string $username, string $email): ?string {
    if ($pw !== $pw2) return 'The two passwords do not match.';
    if (mb_strlen($pw) < 10) return 'Use at least 10 characters.';
    if (strlen($pw) > 128) return 'Use at most 128 characters.';
    $l = mb_strtolower($pw); if ($l === mb_strtolower($username) || $l === mb_strtolower($email) || $l === mb_strtolower(explode('@', $email)[0])) return 'Do not use your username or email as the password.';
    if (preg_match('/^(.)\1+$/u', $pw) || in_array($l, ['1234567890', 'password123', 'qwertyuiop', '0123456789', 'passwordpassword'], true)) return 'That password is too easy to guess.';
    return null;
  }
  public static function mask(string $email): string { [$a, $b] = array_pad(explode('@', $email, 2), 2, ''); return mb_substr($a, 0, 1) . str_repeat('*', max(2, min(6, mb_strlen($a) - 1))) . '@' . $b; }
  /** An uploaded file is kept only when both its extension and its real content type are on the list and it is within the size limit. */
  public static function docProblem(string $ext, ?string $mime, int $size): ?string {
    $ext = strtolower($ext); if (!isset(self::DOC_EXT[$ext])) return 'Only PDF, JPG, PNG or WEBP files are accepted.';
    if (!in_array((string)$mime, self::DOC_EXT[$ext], true)) return 'The file content does not match its type.';
    if ($size <= 0 || $size > self::DOC_MAX) return 'Each file must be 5 MB or smaller.';
    return null;
  }

  // ---------- state ----------
  public static function ready(): bool { try { return Schema::hasTable('marine_accounts') && Schema::hasTable('marine_invites') && Schema::hasColumn('marine_access_requests', 'doc_hash'); } catch (\Throwable $e) { return false; } }
  public static function staff(): string { return mb_substr((string)(optional(auth()->user())->name ?? 'staff'), 0, 120); }

  /** The signed-in portal account, or null. Suspended accounts and sessions older than a forced logout are refused on every request. */
  public static function user(): ?object {
    static $u = false; if ($u !== false) return $u;
    $id = (int)session('ma_uid'); if (!$id) return $u = null;
    $x = DB::table('marine_accounts')->where('id', $id)->first();
    if (!$x || $x->status !== 'active' || ($x->force_logout_at && strtotime((string)$x->force_logout_at) > (int)session('ma_iat'))) { session()->forget(['ma_uid', 'ma_iat']); return $u = null; }
    return $u = $x;
  }
  public static function find(string $ident): ?object { $i = mb_strtolower(trim($ident)); if ($i === '' || mb_strlen($i) > 160) return null; return DB::table('marine_accounts')->where(fn($w) => $w->whereRaw('lower(username) = ?', [$i])->orWhereRaw('lower(email) = ?', [$i]))->orderByDesc('id')->first(); }
  public static function byToken(?string $token): ?object { if (!self::tokenOk($token)) return null; return DB::table('marine_accounts')->where('invite_hash', self::th($token))->where('invite_expires_at', '>', now())->whereIn('status', ['invited', 'active'])->first(); }

  // ---------- email ----------
  private static function wrap(string $title, string $body): string {
    return '<!doctype html><html><body style="margin:0;background:#F4F6F9;font-family:Arial,Helvetica,sans-serif;color:#0C161F"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px"><table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#FFFFFF;border:1px solid #D9E0E8;border-radius:10px"><tr><td style="padding:20px 28px;border-bottom:3px solid #F95505"><img src="' . self::SITE . '/logo-header.png" alt="DutyFree BD Marine Supply" width="163" height="44" style="display:block;border:0;font-size:18px;font-weight:700;color:#0B2545"></td></tr><tr><td style="padding:24px 28px;font-size:15px;line-height:1.6"><h1 style="margin:0 0 12px;font-size:19px;color:#0B2545">' . e($title) . '</h1>' . $body . '</td></tr><tr><td style="padding:16px 28px;border-top:1px solid #E5EAF0;font-size:12px;line-height:1.5;color:#64748B">DutyFree BD Marine Supply · ship supply at Chattogram, Mongla and Payra · ' . self::SITE . '<br>If you did not ask for this, you can ignore this email. Nobody from our desk will ever ask for your password or code.</td></tr></table></td></tr></table></body></html>';
  }
  private static function btn(string $url, string $text): string { return '<p style="margin:18px 0"><a href="' . e($url) . '" style="display:inline-block;background:#F95505;color:#FFFFFF;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:8px">' . e($text) . '</a></p><p style="font-size:12px;color:#64748B;word-break:break-all">If the button does not open, copy this address into your browser:<br>' . e($url) . '</p>'; }
  /** Sends one email now and records it. Returns [ok, error]. */
  public static function mail(string $kind, string $to, string $subject, string $title, string $body, ?int $requestId, ?int $accountId, string $by): array {
    try { [$ok, $err] = Mailer::deliver($to, $subject, self::wrap($title, $body)); } catch (\Throwable $e) { $ok = false; $err = $e->getMessage(); }
    try { DB::table('marine_invites')->insert(['request_id' => $requestId, 'account_id' => $accountId, 'kind' => $kind, 'to' => mb_substr($to, 0, 160), 'ok' => (bool)$ok, 'error' => $ok ? null : mb_substr((string)$err, 0, 400), 'sent_by' => mb_substr($by, 0, 120), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return [(bool)$ok, $ok ? null : (string)$err];
  }

  // ---------- staff actions ----------
  /** Creates the account for an approved application (once) and sends the invite. Returns [ok, message]. */
  public static function approveAndInvite(object $q, string $by): array {
    if (!filter_var((string)$q->email, FILTER_VALIDATE_EMAIL)) return [false, 'This application has no valid email address.'];
    $type = self::typeOf($q->kind);
    $acc = DB::transaction(function () use ($q, $by, $type) {
      $a = DB::table('marine_accounts')->where('request_id', $q->id)->lockForUpdate()->first(); if ($a) return $a;
      $id = DB::table('marine_accounts')->insertGetId(['type' => $type, 'request_id' => $q->id, 'username' => 'tmp-' . bin2hex(random_bytes(8)), 'email' => mb_strtolower(trim((string)$q->email)), 'name' => $q->name, 'company' => $q->company, 'role' => $q->role,
        'vessel_role' => $type === 'vessel' ? ($q->vessel_role ?: 'crew') : null, 'crew_rank' => $q->crew_rank, 'phone' => $q->phone, 'port' => $q->port, 'imo' => $q->imo, 'vessel' => $q->vessel, 'status' => 'invited', 'approved_by' => $by, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
      DB::table('marine_accounts')->where('id', $id)->update(['username' => self::username($type, $id)]);
      if ($q->imo && $type !== 'vessel') DB::table('marine_account_vessels')->insertOrIgnore(['account_id' => $id, 'imo' => $q->imo, 'name' => $q->vessel, 'note' => 'From the application', 'added_by' => $by, 'created_at' => now(), 'updated_at' => now()]);
      DB::table('marine_documents')->where('request_id', $q->id)->whereNull('account_id')->update(['account_id' => $id]);
      return DB::table('marine_accounts')->where('id', $id)->first();
    });
    return self::invite($acc, $by, 'invite');
  }
  /** New single-use link; the previous link and any code stop working at once. $purpose: invite | resend | reset. */
  public static function invite(object $acc, string $by, string $purpose): array {
    if ($acc->status === 'suspended') return [false, 'This account is suspended.'];
    $token = self::token(); $reset = $purpose === 'reset';
    DB::table('marine_accounts')->where('id', $acc->id)->update(['invite_hash' => self::th($token), 'invite_expires_at' => now()->addHours($reset ? 2 : self::INVITE_HOURS), 'invite_purpose' => $reset ? 'reset' : 'invite', 'otp_hash' => null, 'otp_expires_at' => null, 'otp_tries' => 0, 'otp_sent_at' => null, 'otp_sent_count' => 0, 'updated_at' => now()]);
    $url = self::PORTAL . '/activate/' . $token; $hi = '<p>Dear ' . e($acc->name ?: 'Sir or Madam') . ',</p>';
    if ($reset) return self::mail('reset', $acc->email, 'Reset your DutyFree BD Marine Supply password', 'Reset your password', $hi . '<p>We received a request to reset the password of your account <b>' . e($acc->username) . '</b>. The link below works once and for 2 hours.</p>' . self::btn($url, 'Set a new password') . '<p>On that page press <b>Send code</b>; we then email you a 6-digit code to confirm it is you.</p>', $acc->request_id, $acc->id, $by);
    return self::mail($purpose === 'resend' ? 'resend' : 'invite', $acc->email, 'Your DutyFree BD Marine Supply account is approved: set your password', 'Your account is approved',
      $hi . '<p>Your ' . e(rtrim(self::TYPES[$acc->type] ?? 'account', 's')) . ' account with DutyFree BD Marine Supply has been verified.</p><p style="background:#F4F6F9;border:1px solid #D9E0E8;border-radius:8px;padding:12px 14px">Username: <b style="font-size:17px;letter-spacing:.03em">' . e($acc->username) . '</b></p><p>Set your own password with the link below. It works once and for ' . self::INVITE_HOURS . ' hours.</p>' . self::btn($url, 'Set password') . '<p>On that page press <b>Send code</b>; we then email you a 6-digit code to confirm this address. Your account becomes active when the code and your password are accepted.</p>', $acc->request_id, $acc->id, $by);
  }
  /** Asks the applicant for missing documents: email with what is missing and a link to a page where files can be added (14 days). */
  public static function requestDocuments(object $q, string $note, string $by): array {
    $token = self::token();
    DB::table('marine_access_requests')->where('id', $q->id)->update(['status' => 'docs', 'docs_note' => mb_substr($note, 0, 600), 'docs_requested_at' => now(), 'doc_hash' => self::th($token), 'doc_expires_at' => now()->addDays(14), 'updated_at' => now()]);
    return self::mail('documents', (string)$q->email, 'Documents needed for your DutyFree BD Marine Supply account', 'We need a few more documents',
      '<p>Dear ' . e($q->name ?: 'Sir or Madam') . ',</p><p>Thank you for your account request. Before we can verify it, our marine desk needs the following:</p><p style="background:#FFF7ED;border:1px solid #FDBA74;border-radius:8px;padding:12px 14px;white-space:pre-line">' . e($note) . '</p>' . self::btn(self::PORTAL . '/documents/' . $token, 'Add documents') . '<p>PDF, JPG, PNG or WEBP, up to 5 MB each. The link stays open for 14 days.</p>', $q->id, null, $by);
  }

  // ---------- codes ----------
  /** Emails a fresh 6-digit code for the open link. Refused when one was sent less than a minute ago or too many were sent for this link. */
  public static function sendCode(object $acc): array {
    if ($acc->otp_sent_at && time() - strtotime((string)$acc->otp_sent_at) < self::OTP_GAP) return [false, 'A code was just sent. Please wait a minute before asking for another.'];
    if ((int)$acc->otp_sent_count >= self::OTP_PER_LINK) return [false, 'Too many codes were requested for this link. Ask our marine desk to resend your invitation.'];
    $code = self::code();
    DB::table('marine_accounts')->where('id', $acc->id)->update(['otp_hash' => Hash::make($code), 'otp_expires_at' => now()->addMinutes(self::OTP_MINUTES), 'otp_tries' => 0, 'otp_sent_at' => now(), 'otp_sent_count' => (int)$acc->otp_sent_count + 1, 'updated_at' => now()]);
    [$ok, $err] = self::mail('otp', $acc->email, 'Your DutyFree BD Marine Supply verification code', 'Your verification code', '<p>Use this code to confirm your email address and set your password:</p><p style="font-size:30px;font-weight:700;letter-spacing:.3em;color:#0B2545;margin:14px 0">' . e($code) . '</p><p>The code works for ' . self::OTP_MINUTES . ' minutes. Do not share it with anyone.</p>', $acc->request_id, $acc->id, 'system');
    return $ok ? [true, 'We sent a 6-digit code to ' . self::mask($acc->email) . '.'] : [false, 'The code could not be sent just now. Please try again in a few minutes.'];
  }
  /** True only for the right, unexpired code within the allowed tries. A wrong code counts; the fifth wrong one voids the code. */
  public static function checkCode(object $acc, ?string $code): bool {
    $a = DB::table('marine_accounts')->where('id', $acc->id)->first(); if (!$a || !$a->otp_hash || !$a->otp_expires_at || strtotime((string)$a->otp_expires_at) < time()) return false;
    if ((int)$a->otp_tries >= self::OTP_TRIES) { DB::table('marine_accounts')->where('id', $a->id)->update(['otp_hash' => null, 'otp_expires_at' => null]); return false; }
    if (!self::codeOk($code) || !Hash::check((string)$code, $a->otp_hash)) { DB::table('marine_accounts')->where('id', $a->id)->update(['otp_tries' => (int)$a->otp_tries + 1]); return false; }
    return true;
  }

  // ---------- documents ----------
  /** Keeps one uploaded file on the private disk under a random name. Returns the reason it was refused, or null when stored. */
  public static function storeDoc(?UploadedFile $f, int $requestId, ?int $accountId, string $side = 'applicant'): ?string {
    if (!$f || !$f->isValid()) return 'The file did not arrive completely.';
    $ext = strtolower((string)$f->getClientOriginalExtension()); $bad = self::docProblem($ext, $f->getMimeType(), (int)$f->getSize()); if ($bad) return $bad;
    if (DB::table('marine_documents')->where('request_id', $requestId)->count() >= self::DOC_PER_REQUEST) return 'This application already has the maximum number of files.';
    $path = $f->storeAs('marine-docs/' . $requestId, bin2hex(random_bytes(12)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext), 'local'); if (!$path) return 'The file could not be saved.';
    $name = mb_substr(preg_replace('/[^\w .()\-]+/u', '_', (string)$f->getClientOriginalName()), 0, 190) ?: 'document.' . $ext;
    DB::table('marine_documents')->insert(['request_id' => $requestId, 'account_id' => $accountId, 'name' => $name, 'path' => $path, 'mime' => $f->getMimeType(), 'size' => (int)$f->getSize(), 'by_side' => $side, 'created_at' => now(), 'updated_at' => now()]);
    return null;
  }
}
