<?php
namespace App\Support;

/** DutyFreeBD print identity. Every PDF / print page carries the stacked logo as a centred, faded watermark on each page,
 *  and every export page (PDF, Excel, Word, print) carries the horizontal logo at the top.
 *  Files live in public/brand/. */
class PrintBrand {
  const LOGO = '/brand/export-logo.png';        // horizontal logo (export page header)
  const WM   = '/brand/watermark-faded.png';    // stacked logo, already faded (watermark)
  const WMV  = '3';                              // cache-buster for the logo files

  static function url(string $p, bool $abs = false): string { return ($abs ? 'https://' . Domain::HOST : '') . $p . '?v=' . self::WMV; }

  /** Watermark + header rules. $always = the header is also shown on screen (stand-alone export pages); otherwise print only (admin pages). */
  static function css(bool $always = true): string {
    return '<style id="bx-css">.bx-wm{display:none}.bx-hd{display:' . ($always ? 'flex' : 'none') . ';align-items:center;justify-content:space-between;gap:12px;padding:0 0 8px;margin:0 0 12px;border-bottom:2px solid #FF5A00;font:11px Inter,Bornomala,sans-serif;color:#4B5563}'
      . '.bx-hd img{height:46px;width:auto;display:block}.bx-hd span{text-align:right;line-height:1.5}'
      . '@media print{.bx-hd{display:flex!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}'
      . '.bx-wm{display:flex!important;position:fixed;left:0;top:0;width:100%;height:100%;align-items:center;justify-content:center;pointer-events:none;z-index:2147483000;-webkit-print-color-adjust:exact;print-color-adjust:exact}'
      . '.bx-wm img{width:62%;max-width:560px;height:auto;display:block}}</style>';
  }

  static function header(bool $abs = false): string {
    return '<div class="bx-hd" id="bx-hd"><img src="' . self::url(self::LOGO, $abs) . '" alt="DutyFreeBD"><span>www.dutyfreebd.com<br>' . e(now('Asia/Dhaka')->format('d M Y, h:i A')) . '</span></div>';
  }

  static function watermark(): string { return '<div class="bx-wm" id="bx-wm"><img src="' . self::url(self::WM) . '" alt=""></div>'; }

  /** For admin pages (layout): print-only header + watermark. */
  static function layoutPrint(): string { return self::css(false) . self::watermark() . self::header(); }

  /** For HTML we build for PDF/print: css + watermark + header. For Word/Excel: only the logo header with inline styles (absolute URL), because those programs ignore fixed positioning. */
  static function top(string $fmt): string {
    if ($fmt === 'pdf' || $fmt === 'print' || $fmt === 'html') return self::css(true) . self::watermark() . self::header();
    return self::officeHeader();
  }

  static function officeHeader(): string {
    return '<table width="100%" cellspacing="0" cellpadding="0" style="border-bottom:2px solid #FF5A00;margin-bottom:10px"><tr><td align="left"><img src="' . self::url(self::LOGO, true) . '" height="46" alt="DutyFreeBD"></td>'
      . '<td align="right" style="font:11px Inter,Bornomala,sans-serif;color:#4B5563">www.dutyfreebd.com<br>' . e(now('Asia/Dhaka')->format('d M Y, h:i A')) . '</td></tr></table>';
  }

  /** Put the identity into a finished HTML string (used by the middleware). */
  static function inject(string $html, string $kind): string {
    if (str_contains($html, 'id="bx-wm"') || str_contains($html, 'id="bx-office"')) return $html;
    $hasLogo = str_contains($html, 'brand/export-logo');
    if ($kind === 'office') { $top = '<div id="bx-office">' . ($hasLogo ? '' : self::officeHeader()) . '</div>'; }
    else { $top = self::css(true) . self::watermark() . ($hasLogo ? '' : self::header()); }
    if (preg_match('~<body\b[^>]*>~i', $html, $m, PREG_OFFSET_CAPTURE)) { $at = $m[0][1] + strlen($m[0][0]); return substr($html, 0, $at) . $top . substr($html, $at); }
    return $top . $html;
  }
}
