<?php
namespace App\Support;

class Youtube
{
  /** 11-character video id from a watch / youtu.be / shorts / embed / live link, or a bare id. */
  public static function id(string $u): ?string {
    $u = trim($u); if ($u === '') return null;
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $u)) return $u;
    if (!preg_match('#^https?://(?:www\.|m\.|music\.)?(?:youtube\.com|youtube-nocookie\.com|youtu\.be)/#i', $u)) return null;
    if (preg_match('#youtu\.be/([A-Za-z0-9_-]{11})#i', $u, $m)) return $m[1];
    if (preg_match('#[?&]v=([A-Za-z0-9_-]{11})#', $u, $m)) return $m[1];
    if (preg_match('#/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})#i', $u, $m)) return $m[1];
    return null;
  }
}
