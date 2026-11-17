<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * The session lives in the cookie (SESSION_DRIVER=cookie). When a form fails validation Laravel flashes every posted field into the
 * session, the Set-Cookie header becomes too large and the nginx proxy answers 502 instead of showing the error. The browser is then
 * left on the POST address, and a reload of that address is a 404 ("Not Found"). Found 17 Nov 2026 on the product form
 * (sale price above regular price), but it is the same for every large form.
 * After the controller has answered, the flashed input and error text are cut to a size that always fits: short fields stay
 * (so the typed price is still there), long ones (description, variants, SEO text) fall back to the saved values.
 */
class FlashDiet {
  const INPUT = 700; const FIELD = 80; const MSG = 400; const MSGS = 3;
  public function handle(Request $r, Closure $next) {
    $res = $next($r);
    try {
      if (config('session.driver') !== 'cookie' || !$r->hasSession()) return $res;
      $s = $r->session();
      $old = $s->get('_old_input');
      if (is_array($old) && $old) {
        unset($old['_token']);
        if (strlen((string)json_encode($old)) > self::INPUT) {
          $keep = []; $used = 0;
          foreach ($old as $k => $v) {
            if (!is_scalar($v) || strlen((string)$v) > self::FIELD) continue;
            $n = strlen((string)$k) + strlen((string)$v) + 6;
            if ($used + $n > self::INPUT) continue;
            $keep[$k] = $v; $used += $n;
          }
          $old = $keep;
        }
        $s->put('_old_input', $old);
      }
      $eb = $s->get('errors');
      if ($eb instanceof ViewErrorBag) {
        $nb = new ViewErrorBag; $left = self::MSGS;
        foreach ($eb->getBags() as $name => $bag) {
          $mb = new MessageBag;
          foreach ($bag->getMessages() as $f => $list) foreach ($list as $msg) {
            if ($left-- <= 0) break 2;
            $mb->add($f, mb_strlen((string)$msg) > self::MSG ? mb_substr((string)$msg, 0, self::MSG) . '…' : (string)$msg);
          }
          $nb->put($name, $mb);
        }
        $s->put('errors', $nb);
      }
    } catch (\Throwable $e) { report($e); }
    return $res;
  }
}
