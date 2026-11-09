<?php
namespace App\Support;

/** Public tracking link + copy button for a booked courier parcel. */
class TrackLink {
  public static function url(object $o): ?string {
    $cid = trim((string)($o->consignment_id ?? '')); if ($cid === '') return null;
    $c = (string)($o->courier ?? '');
    return match ($c) {
      'Pathao' => 'https://merchant.pathao.com/tracking?consignment_id='.rawurlencode($cid).'&phone='.rawurlencode(substr(preg_replace('/\D+/', '', (string)($o->phone ?? '')), -11)),
      'Steadfast' => !empty($o->tracking_code) ? 'https://steadfast.com.bd/t/'.rawurlencode((string)$o->tracking_code) : \App\Support\Inv::trackUrl($o->invoice),
      'RedX' => 'https://redx.com.bd/track-global-parcel/?trackingId='.rawurlencode($cid),
      default => \App\Support\Inv::trackUrl($o->invoice),
    };
  }
  /** Consignment id, a copy button and the tracking link, as one HTML snippet. */
  public static function html(object $o): string {
    $cid = trim((string)($o->consignment_id ?? '')); if ($cid === '') return '—';
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $u = self::url($o);
    return '<span style="white-space:nowrap"><b>'.$e($cid).'</b> <a href="#" title="Copy consignment ID" aria-label="Copy consignment ID" onclick="return dfCopyNo(this,\''.$e(addslashes($cid)).'\')" style="display:inline-flex;vertical-align:middle;margin-left:4px;color:#4B5563"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg></a></span>'
      .($u ? '<br><a href="'.$e($u).'" target="_blank" rel="noopener" style="font-size:12px">Track parcel ↗</a>' : '');
  }
}
