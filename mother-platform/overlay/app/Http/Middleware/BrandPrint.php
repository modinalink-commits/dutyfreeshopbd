<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Support\PrintBrand;

/** Adds the DutyFreeBD export logo and the print watermark to stand-alone print / PDF pages and to Word / Excel downloads that are built as ordinary (non-streamed) responses.
 *  Admin pages that use the admin layout already carry the print-only version, and the streamed exports add their own; those are skipped by the id marker. */
class BrandPrint {
  public function handle(Request $r, Closure $next) {
    $resp = $next($r);
    try {
      if (!$r->isMethod('GET') || $resp->getStatusCode() !== 200 || $r->ajax() || $resp instanceof \Symfony\Component\HttpFoundation\StreamedResponse || $resp instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) return $resp;
      $ct = strtolower((string)$resp->headers->get('Content-Type'));
      $office = str_contains($ct, 'msword') || str_contains($ct, 'ms-excel');
      if (!$office && !str_contains($ct, 'text/html')) return $resp;
      $html = $resp->getContent(); if (!is_string($html) || $html === '') return $resp;
      if (!$office && !str_contains($html, 'window.print(')) return $resp;   // only pages that are meant to be printed
      $out = PrintBrand::inject($html, $office ? 'office' : 'print');
      if ($out !== $html) { $resp->setContent($out); if ($resp->headers->has('Content-Length')) $resp->headers->set('Content-Length', (string)strlen($out)); }
    } catch (\Throwable $e) {}
    return $resp;
  }
}
