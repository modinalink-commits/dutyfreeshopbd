<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/**
 * Tobacco-free storefront ("clean site", owner 9 Nov 2026). Google Ads disapproves any ad whose website sells tobacco, so the SAME shop can also be opened on a clean domain
 * (setting clean_hosts = comma list, e.g. dutyfreeperfume.com.bd) where cigarettes, vape, lighters, ash trays and cigarette boxes do not exist at all:
 * no menu, no listing, no search hit, no product page (404), no sitemap entry, no deal, no banner. Admin, API and the main domain are untouched.
 * Preview on any domain: open any page with ?cleanpreview=1 (cookie), ?cleanpreview=0 to leave.
 */
class CleanSite {
  /** Postgres regex (word starts) for product / sub-category names that are tobacco or tobacco accessories. */
  const RX = '\\m(cigar|cigarette|vape|vaping|nicotin|tobacco|smoking|lighter|ash ?tray|hookah|shisha|cigarette box)|সিগারেট|ভেপ|লাইটার|তামাক|অ্যাশট্রে';
  private static ?bool $on = null;
  public static function hosts(): array {
    $v = (string)Cache::remember('clean.hosts', 60, fn() => (string)(DB::table('settings')->where('key', 'clean_hosts')->value('value') ?? ''));
    return array_values(array_filter(array_map(fn($h) => preg_replace('/^www\./', '', strtolower(trim($h))), explode(',', $v))));
  }
  public static function on(): bool {
    if (self::$on !== null) return self::$on;
    try {
      if (app()->runningInConsole()) return self::$on = false;
      $r = request();
      if ($r->is('admin', 'admin/*', 'api/*', 'webhook/*')) return self::$on = false;
      $h = preg_replace('/^www\./', '', strtolower($r->getHost()));
      $on = in_array($h, self::hosts(), true);
      if (!$on) { $q = $r->query('cleanpreview'); $on = $q !== null ? $q === '1' : $r->cookie('cleanpreview') === '1'; if ($q !== null) \Illuminate\Support\Facades\Cookie::queue('cleanpreview', $q === '1' ? '1' : '0', 5); }
      return self::$on = $on;
    } catch (\Throwable $e) { return self::$on = false; }
  }
  public static function host(): string { return strtolower((string)request()->getHost()); }
  /** ids of the categories that are tobacco (Cigarette & Vape) */
  public static function catIds(): array {
    return Cache::remember('clean.catids2', 300, fn() => DB::table('categories')->get(['id', 'slug', 'name'])->filter(fn($c) => ($c->slug ?? '') === 'cigarette-vape' || str_contains((string)($c->slug ?? ''), 'lighter') || preg_match('/cigarette|vape|lighter|সিগারেট|ভেপ|লাইটার/iu', (string)($c->name ?? '')) === 1)->pluck('id')->map(fn($v) => (int)$v)->all());
  }
  public static function bad(?string $s): bool { return $s !== null && $s !== '' && preg_match('/\b(cigar|cigarette|vape|vaping|nicotin\w*|tobacco|smoking|lighter|ash ?tray|hookah|shisha)|সিগারেট|ভেপ|লাইটার|তামাক|অ্যাশট্রে/iu', $s) === 1; }
  /** true when a product row object may be shown on the clean site */
  public static function keep($p): bool {
    if (!self::on()) return true;
    if (in_array((int)($p->category_id ?? 0), self::catIds(), true) || ($p->group_key ?? '') === 'smoke') return false;
    return !(self::bad($p->name ?? '') || self::bad($p->sub ?? '') || self::bad($p->sub_sub ?? ''));
  }
  /** Add the tobacco exclusion to a products query ($a = alias prefix, 'products.' or 'p.'). No-op outside the clean site. */
  public static function apply($qb, string $a = 'products.') {
    if (!self::on()) return $qb;
    $ids = self::catIds();
    if ($ids) $qb->where(fn($w) => $w->whereNull($a.'category_id')->orWhereNotIn($a.'category_id', $ids));
    return $qb->where(fn($w) => $w->whereNull($a.'group_key')->orWhere($a.'group_key', '!=', 'smoke'))
      ->whereRaw("coalesce({$a}name,'') !~* ? and coalesce({$a}sub,'') !~* ? and coalesce({$a}sub_sub,'') !~* ?", [self::RX, self::RX, self::RX]);
  }
}
