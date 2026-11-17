<?php
namespace App\Http\Controllers;
use App\Support\SmsInbox;
use Illuminate\Http\Request;

/** Receive address for incoming SMS: /webhooks/sms-in/{id}/{token}. One address per connection; the secret token in the address is the check that the provider is the caller. */
class SmsInboundController extends Controller {
  public function receive(Request $r, string $id, string $token) {
    $c = SmsInbox::find($id);
    if (!$c || !hash_equals((string)$c['token'], $token)) return response('Forbidden', 403);
    $in = $r->all(); if (!$in && ($j = json_decode((string)$r->getContent(), true)) && is_array($j)) $in = $j;
    try { [$res] = SmsInbox::receive($c, $in); } catch (\Throwable $e) { report($e); return response('Error', 500); }
    return response()->json(['ok' => in_array($res, ['stored', 'duplicate', 'report'], true), 'result' => $res], $res === 'invalid' ? 422 : 200);
  }
}
