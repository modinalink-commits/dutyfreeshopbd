<?php
namespace App\Http\Controllers;

use App\Support\LiveBoard;

/** Live Dashboard: a read-only wall screen of everything running in the background. Settings-level permission (ModulePermission). */
class LiveBoardController extends Controller
{
  public function index() { return view('live_board'); }
  public function status() {
    try { return response()->json(LiveBoard::data())->header('Cache-Control', 'no-store'); }
    catch (\Throwable $e) { report($e); return response()->json(['error' => 'Live data could not be read'], 500); }
  }
}
