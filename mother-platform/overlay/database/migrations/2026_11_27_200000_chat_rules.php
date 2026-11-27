<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** 27 Nov 2026 (owner): an answered chat closes by itself after 3 hours of customer silence (ai_close_answered_hours = 3); chats with a follow-up mark stay open.
 *  The hand-off words check, the automatic "ফলোআপ দরকার" mark and the Closed - No Response rule are code (ChatRules); nothing else is changed here. */
return new class extends Migration {
  public function up(): void {
    try {
      \App\Support\Sms::setSecret('ai_close_answered_hours', '3');
      DB::table('audit_logs')->insert(['user_id'=>null, 'user_name'=>'System (owner instruction)', 'action'=>'chat.setting', 'subject'=>'Auto-close of answered chats set to 3 hours of customer silence', 'ip'=>null, 'created_at'=>now(), 'updated_at'=>now()]);
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
