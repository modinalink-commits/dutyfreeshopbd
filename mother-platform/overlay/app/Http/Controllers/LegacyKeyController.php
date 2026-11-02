<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Shows the old-site import key to the owner only, so he can type it into the sync box himself. */
class LegacyKeyController extends Controller {
  public function show() {
    abort_unless(auth()->user() && auth()->user()->type === 'super', 403);
    $t = (string)DB::table('settings')->where('key', 'legacy_import_token')->value('value');
    if ($t === '') { $t = Str::random(40); DB::table('settings')->updateOrInsert(['key' => 'legacy_import_token'], ['value' => $t, 'updated_at' => now(), 'created_at' => now()]); }
    return view('legacy_key', ['token' => $t]);
  }
}
