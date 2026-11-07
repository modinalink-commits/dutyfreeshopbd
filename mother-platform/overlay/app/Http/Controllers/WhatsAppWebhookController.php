<?php
namespace App\Http\Controllers;
use App\Support\WhatsApp as W;
use Illuminate\Http\Request;

/** Meta calls this: GET to verify the callback URL, POST for incoming messages and delivery statuses. */
class WhatsAppWebhookController extends Controller {
  public function verify(Request $r) {
    if ($r->query('hub_mode') === 'subscribe' && hash_equals(W::verifyToken(), (string)$r->query('hub_verify_token'))) return response((string)$r->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    return response('Forbidden', 403);
  }
  public function receive(Request $r) {
    $secret = W::cfg('app_secret'); $raw = $r->getContent();
    if ($secret === '' || !hash_equals('sha256=' . hash_hmac('sha256', $raw, $secret), (string)$r->header('X-Hub-Signature-256'))) return response('Invalid signature', 403);
    try { if (W::ready()) W::receive((array)json_decode($raw, true)); } catch (\Throwable $e) { report($e); }
    return response('OK', 200);
  }
}
