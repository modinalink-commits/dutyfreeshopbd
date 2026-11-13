<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Public comments on our Facebook / Instagram posts and what was answered (AI Comment Reply). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('chat_comments')) Schema::create('chat_comments', function (Blueprint $t) {
      $t->id(); $t->string('channel', 12)->default('facebook'); $t->string('page_ref', 40)->nullable()->index(); $t->string('post_id', 80)->nullable()->index();
      $t->string('comment_id', 80)->unique(); $t->string('parent_id', 80)->nullable(); $t->string('from_id', 40)->nullable(); $t->string('from_name', 120)->nullable();
      $t->text('message')->nullable(); $t->text('reply_text')->nullable(); $t->string('status', 12)->default('new')->index(); $t->string('note', 200)->nullable();
      $t->timestamp('replied_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('chat_comments'); }
};
