<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each invoice keeps the company details it was printed with.
        Schema::table('sales', function (Blueprint $t) {
            $t->json('company')->nullable();
            $t->unsignedSmallInteger('pos_prints')->default(0);
            $t->unsignedSmallInteger('a4_prints')->default(0);
        });
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->index();
            $table->string('to', 20)->nullable();
            $table->text('body');
            $table->foreignId('sale_id')->nullable()->index();
            $table->foreignId('customer_id')->nullable()->index();
            $table->string('status', 12)->default('queued')->index(); // queued, sent, failed, no_mobile, off
            $table->string('response', 255)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
        Schema::table('sales', fn (Blueprint $t) => $t->dropColumn(['company', 'pos_prints', 'a4_prints']));
    }
};
