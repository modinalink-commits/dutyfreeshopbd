<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void {
    try {
      if (!DB::table('chat_quick_replies')->where('title', 'সালাম ও শুরু')->exists())
        DB::table('chat_quick_replies')->insert(['title'=>'সালাম ও শুরু', 'body'=>'আসসালামু আলাইকুম 😊 আমি {agent} বলছি, DutyFreeBD থেকে। আপনি কেমন আছেন? আপনাকে কিভাবে সহযোগিতা করতে পারি?', 'active'=>true, 'created_at'=>now(), 'updated_at'=>now()]);
    } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
