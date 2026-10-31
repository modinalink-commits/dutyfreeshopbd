<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('chat_threads')) Schema::create('chat_threads', function (Blueprint $t) {
      $t->id(); $t->string('channel', 20)->index(); $t->string('external_id', 120);
      $t->foreignId('customer_id')->nullable(); $t->string('name', 160)->nullable(); $t->string('phone', 30)->nullable()->index();
      $t->string('username', 120)->nullable(); $t->text('avatar')->nullable();
      $t->string('status', 20)->default('new')->index(); $t->foreignId('assigned_to')->nullable()->index();
      $t->unsignedInteger('unread')->default(0); $t->text('last_message')->nullable(); $t->string('last_dir', 3)->nullable(); $t->timestamp('last_at')->nullable()->index();
      $t->timestamps(); $t->unique(['channel', 'external_id']);
    });
    if (!Schema::hasTable('chat_messages')) Schema::create('chat_messages', function (Blueprint $t) {
      $t->id(); $t->foreignId('thread_id')->index(); $t->string('dir', 3); $t->string('kind', 20)->default('text'); $t->text('body')->nullable();
      $t->text('attachment_url')->nullable(); $t->string('attachment_type', 20)->nullable();
      $t->foreignId('user_id')->nullable(); $t->string('user_name', 120)->nullable();
      $t->string('ext_id', 190)->nullable()->unique(); $t->string('status', 12)->default('sent'); $t->text('error')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('chat_quick_replies')) {
      Schema::create('chat_quick_replies', function (Blueprint $t) { $t->id(); $t->string('title', 80); $t->text('body'); $t->boolean('active')->default(true); $t->timestamps(); });
      foreach ([
        ['Greeting', 'আসসালামু আলাইকুম! DutyFree Shop BD-তে স্বাগতম। কীভাবে সাহায্য করতে পারি?'],
        ['Ask phone & address', 'অর্ডার কনফার্ম করতে অনুগ্রহ করে আপনার নাম, মোবাইল নম্বর ও সম্পূর্ণ ঠিকানা জানান।'],
        ['Order processing', 'আপনার অর্ডারটি প্রসেসিং-এ আছে। কুরিয়ারে হস্তান্তর হলেই আপনাকে জানানো হবে।'],
        ['Thanks', 'ধন্যবাদ! আমাদের সাথে থাকার জন্য কৃতজ্ঞ। আর কোনো সাহায্য লাগলে জানাবেন।'],
      ] as [$a, $b]) DB::table('chat_quick_replies')->insert(['title'=>$a, 'body'=>$b, 'active'=>true, 'created_at'=>now(), 'updated_at'=>now()]);
    }
  }
  public function down(): void {}
};
