<?php
namespace App\Http\Controllers;

use App\Support\DeployHealth;
use Illuminate\Support\Facades\DB;

/** JSON feed for the admin bell: open alerts (red badge) and the ones already seen (still active, no badge). */
class AdminAlertController extends Controller
{
  public function feed() {
    abort_unless(in_array(optional(auth()->user())->role, ['super', 'admin'], true), 403);
    $rows = DeployHealth::active(); $open = array_values(array_filter($rows, fn($r) => $r['status'] === 'open'));
    return response()->json(['count' => count($open), 'items' => array_slice($rows, 0, 15), 'at' => time()])->header('Cache-Control', 'no-store');
  }
}
