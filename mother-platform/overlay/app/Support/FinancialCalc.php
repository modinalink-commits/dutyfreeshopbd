<?php
namespace App\Support;

/**
 * ONE place for the profit definitions of Mother Commerce (Report Center spec 48-49).
 * The protected profit % is NOT stored here: it is read from the central setting through ProfitFloor (Admin: Inventory Pricing), with its history,
 * so an old order is still judged by the rule that was active when it was created.
 * Money amounts are plain floats in BDT.
 */
class FinancialCalc
{
  /** Order statuses that count as a valid sale (same rule as Analytics::VALID). */
  const NOT_VALID_STATUSES = ['Cancel', 'Returned', 'Return Pending'];

  /** The central protected profit %, at a moment in time (default now). */
  public static function protectedPercent(?string $at = null): float { return ProfitFloor::percent($at); }

  /**
   * Contribution profit = Net sales - actual FIFO COGS - courier - packaging - gateway - COD handling - other variable cost - allocated ad spend.
   * @param array $p keys: net_sales, cogs, courier, packaging, gateway, cod_handling, other_variable, ad_spend (missing = 0)
   */
  public static function contribution(array $p): float
  {
    $g = fn($k) => (float)($p[$k] ?? 0);
    return round($g('net_sales') - $g('cogs') - $g('courier') - $g('packaging') - $g('gateway') - $g('cod_handling') - $g('other_variable') - $g('ad_spend'), 2);
  }

  /** Real net profit = contribution profit - fixed operating expenses. */
  public static function realNet(float $contribution, float $fixedExpenses): float { return round($contribution - $fixedExpenses, 2); }

  /** Net margin % of net sales. */
  public static function netMarginPct(float $profit, float $netSales): float { return $netSales > 0 ? round($profit / $netSales * 100, 1) : 0.0; }

  /**
   * Loss (profit < 0), Below Protected Margin (0 <= profit% < protected %), Safe (profit% >= protected %).
   * The percentage is profit on cost, the same basis as the protected-profit price floor (cost x (1 + protected %)).
   */
  public static function classify(float $profit, float $cost, ?string $at = null): string
  {
    if ($profit < 0) return 'Loss';
    if ($cost <= 0) return 'Safe';
    return ($profit / $cost * 100) + 0.0001 >= self::protectedPercent($at) ? 'Safe' : 'Below Protected Margin';
  }

  /** What the Report System Audit shows as "financial definitions in use". */
  public static function definitions(): array
  {
    return [
      ['Valid order', 'Every status except ' . implode(', ', self::NOT_VALID_STATUSES) . ' (a partial return stays valid)'],
      ['Protected profit', self::pct() . '% on cost, read from the central setting (Inventory Pricing); historical orders keep the rule of their time'],
      ['Contribution profit', 'Net sales - FIFO COGS - courier - packaging - gateway - COD handling - other variable cost - allocated ad spend'],
      ['Real net profit', 'Contribution profit - fixed operating expenses'],
      ['Margin class', 'Loss: profit below 0 · Below Protected Margin: 0 up to the protected % · Safe: at or above the protected %'],
    ];
  }

  private static function pct(): string { return ProfitFloor::pct(); }
}
