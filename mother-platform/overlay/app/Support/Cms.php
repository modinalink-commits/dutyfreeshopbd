<?php
namespace App\Support;
class Cms {
  /** Render admin-authored HTML: swap placeholders + old mother domain, strip scripts. */
  static function render(?string $html, array $s): string {
    $html = (string)$html;
    if ($html === '') return '';
    if (!preg_match('/<[a-z][\s\S]*>/i', $html)) $html = nl2br(e($html));
    $html = preg_replace('#<script\b[\s\S]*?</script>#i', '', $html);
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html);
    $host = parse_url(url('/'), PHP_URL_HOST) ?: 'dutyfreeshopbd.com';
    $hours = $s['business_hours'] ?? '10 AM – 10 PM';
    $map = ['[[call]]'=>$s['call_number'] ?? '', '[[whatsapp]]'=>$s['whatsapp_number'] ?? '', '[[email]]'=>$s['contact_email'] ?? '', '[[mobile]]'=>($s['contact_mobile'] ?? '') ?: ($s['call_number'] ?? ''), '[[site]]'=>$host, '[[address]]'=>$s['contact_address'] ?? '', '[[hours]]'=>$hours];
    $html = strtr($html, $map);
    // old mother-site domain in internal links/text -> this site
    $html = preg_replace('#https?://(www\.)?dutyfreebd\.com#i', url('/'), $html);
    $html = preg_replace('#\b(www\.)?dutyfreebd\.com\b#i', $host, $html);
    return $html;
  }
  static function mapEmbed(?string $v): string {
    $v = trim((string)$v);
    if (preg_match('#<iframe[^>]+src=["\'](https://www\.google\.com/maps/embed[^"\']+)#i', $v, $m)) return $m[1];
    return str_starts_with($v, 'https://www.google.com/maps/embed') ? $v : '';
  }
}
