<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Several Facebook Pages (and their Instagram accounts) can be connected through Facebook Login; each keeps its own encrypted token. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('chat_pages')) Schema::create('chat_pages', function (Blueprint $t) {
      $t->id(); $t->string('page_id', 40)->unique(); $t->string('name')->nullable(); $t->text('token')->nullable();
      $t->string('ig_id', 40)->nullable()->index(); $t->string('ig_username')->nullable();
      $t->boolean('subscribed')->default(false); $t->string('note', 300)->nullable(); $t->timestamps();
    });
    if (Schema::hasTable('chat_threads') && !Schema::hasColumn('chat_threads', 'page_ref')) Schema::table('chat_threads', function (Blueprint $t) { $t->string('page_ref', 40)->nullable(); });
  }
  public function down(): void {}
};
