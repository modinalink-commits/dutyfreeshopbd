<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::create('integration_secrets', function (Blueprint $t) { $t->id(); $t->string('key')->unique(); $t->text('value')->nullable(); $t->timestamps(); });
    Schema::table('sms_logs', function (Blueprint $t) { $t->timestamp('sent_at')->nullable(); });
  }
  public function down(): void {}
};
