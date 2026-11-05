<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Staff Activity & Order Tracking: admin-panel sign-in history (success, failed, sign-out) with IP and device. Starts empty; nothing is back-filled. */
return new class extends Migration {
  public $withinTransaction = false;
  public function up(): void {
    try {
      if (!Schema::hasTable('staff_login_logs')) Schema::create('staff_login_logs', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('user_id')->nullable()->index(); $t->string('user_name', 120)->nullable(); $t->string('email', 160)->nullable();
        $t->string('event', 12)->index(); // login | failed | logout
        $t->string('ip_address', 64)->nullable(); $t->string('user_agent', 300)->nullable(); $t->timestamp('created_at')->nullable()->index();
      });
    } catch (\Throwable $e) {
      try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Staff login log table failed', 'subject' => mb_substr($e->getMessage(), 0, 240), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e2) {}
    }
  }
  public function down(): void {}
};
