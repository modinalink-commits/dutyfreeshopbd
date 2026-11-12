<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Owner approved (12 Nov 2026): shorten the home <title> (was 98 chars, Google truncates it). Only replaces the old long value; a title edited since is left alone. */
return new class extends Migration {
  public function up(): void {
    $old = 'DutyFree BD | ডিউটি ফ্রি দামে অরিজিনাল পারফিউম, সিগারেট ও স্কিন কেয়ার – দুবাই থেকে সারা বাংলাদেশে';
    DB::table('settings')->where('key', 'site_title')->where('value', $old)->update(['value' => 'DutyFree BD | অরিজিনাল সিগারেট, পারফিউম ও স্কিন কেয়ার']);
    \App\Http\Controllers\StoreController::bust();
  }
  public function down(): void {}
};
