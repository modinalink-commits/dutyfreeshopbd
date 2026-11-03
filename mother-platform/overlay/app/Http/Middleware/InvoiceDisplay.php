<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Support\Inv;
/** Invoice numbers are shown as plain digits everywhere (pages, AJAX fragments, JSON). Links and stored values are untouched. */
class InvoiceDisplay {
  public function handle(Request $request, Closure $next) {
    $res = $next($request);
    if (!($res instanceof \Illuminate\Http\Response) && !($res instanceof \Illuminate\Http\JsonResponse)) return $res;
    $ct = strtolower((string)$res->headers->get('Content-Type', ''));
    if (!(str_contains($ct, 'text/html') || str_contains($ct, 'json'))) return $res;
    $c = $res->getContent(); if (!is_string($c) || !str_contains($c, 'INV-')) return $res;
    $res->setContent(Inv::strip($c));
    return $res;
  }
}
