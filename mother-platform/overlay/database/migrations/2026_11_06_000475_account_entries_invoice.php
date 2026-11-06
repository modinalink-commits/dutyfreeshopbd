<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/** Income/expense rows show which invoice they come from. New column + backfill of courier rows (DEL-/ADJ-/PAY- by reference) and old-site rows (invoice number in the comment). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('account_entries', 'invoice')) Schema::table('account_entries', function (Blueprint $t) { $t->string('invoice', 40)->nullable()->index(); });
    try {
      DB::statement("update account_entries set invoice = substring(reference from 5) where invoice is null and reference like 'DEL-%'");
      DB::statement("update account_entries set invoice = substring(comment from 'order ([0-9]+)') where invoice is null and (reference like 'ADJ-%' or reference like 'PAY-%') and comment ~ 'order [0-9]+'");
      DB::statement("update account_entries set invoice = substring(comment from 'invoice no is ([0-9]+)') where invoice is null and reference like 'OLD-%' and comment ~ 'invoice no is [0-9]+'");
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
