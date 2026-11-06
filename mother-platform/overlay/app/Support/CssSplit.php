<?php
namespace App\Support;
/**
 * Moves the big inline <style> blocks of a heavy admin page into ONE cached stylesheet (/admin/a/{hash}.css) so the browser downloads it once and keeps it.
 * Small blocks (< 400 bytes), blocks with attributes (media=…) and anything unusual stay inline. Any problem returns the original HTML.
 */
class CssSplit {
  public static function dir(): string { return storage_path('app/css-cache'); }
  public static function run(string $html, int $minPage = 150000): string {
    if (strlen($html) < $minPage) return $html;
    try {
      $parts = []; $n = 0;
      $out = preg_replace_callback('~<style>(.*?)</style>~s', function ($m) use (&$parts, &$n) {
        if (strlen($m[1]) < 400 || stripos($m[1], '</') !== false) return $m[0];
        $parts[] = $m[1]; return $n++ === 0 ? '<!--df-css-->' : '';
      }, $html);
      if ($out === null || $n < 1) return $html;
      $css = implode("\n", $parts); $h = substr(md5($css), 0, 16);
      $d = self::dir(); if (!is_dir($d)) @mkdir($d, 0775, true);
      $f = $d . '/' . $h . '.css';
      if (!is_file($f)) { if (@file_put_contents($f, $css, LOCK_EX) === false) return $html; self::prune($d); }
      return str_replace('<!--df-css-->', '<link rel="stylesheet" href="/admin/a/' . $h . '.css">', $out);
    } catch (\Throwable $e) { return $html; }
  }
  private static function prune(string $d): void { $fs = glob($d . '/*.css') ?: []; if (count($fs) <= 12) return; usort($fs, fn($a, $b) => filemtime($a) <=> filemtime($b)); foreach (array_slice($fs, 0, count($fs) - 12) as $x) @unlink($x); }
  public static function serve(string $h) {
    if (!preg_match('/^[a-f0-9]{16}$/', $h)) abort(404);
    $f = self::dir() . '/' . $h . '.css'; if (!is_file($f)) abort(404);
    return response(file_get_contents($f), 200, ['Content-Type' => 'text/css; charset=utf-8', 'Cache-Control' => 'private, max-age=31536000, immutable']);
  }
}
