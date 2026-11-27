<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner 27 Nov 2026: every label on the Messages board is English. Only the title of the seeded greeting quick reply changes; the Bangla text sent to the customer stays as it is. */
return new class extends Migration {
  public function up(): void { if (Schema::hasTable('chat_quick_replies')) DB::table('chat_quick_replies')->where('title', 'সালাম ও শুরু')->update(['title'=>'Greeting (Salam)']); }
  public function down(): void { if (Schema::hasTable('chat_quick_replies')) DB::table('chat_quick_replies')->where('title', 'Greeting (Salam)')->update(['title'=>'সালাম ও শুরু']); }
};
