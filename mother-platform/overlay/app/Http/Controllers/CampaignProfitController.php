<?php
namespace App\Http\Controllers;
use App\Support\CampaignProfit;

/** Read-only: profit of every automatic marketing campaign, last 7 and 30 days. */
class CampaignProfitController extends Controller {
  public function index() {
    $rep = [];
    foreach ([7, 30] as $d) { try { $rep[$d] = CampaignProfit::report($d); } catch (\Throwable $e) { report($e); $rep[$d] = null; } }
    return view('campaign_profit', ['rep' => $rep, 'win' => CampaignProfit::WINDOW]);
  }
}
