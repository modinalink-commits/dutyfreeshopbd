<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Call Clients CDR: store the client's caller ID (display only; digits/+ only, never used for decisions). */
return new class extends Migration {
  public function up(): void { Schema::table('voice_call_cdrs', function (Blueprint $t) { $t->string('caller', 32)->nullable()->after('dest'); }); }
  public function down(): void { Schema::table('voice_call_cdrs', function (Blueprint $t) { $t->dropColumn('caller'); }); }
};
