<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('orders', 'email')) Schema::table('orders', function (Blueprint $t) { $t->string('email', 160)->nullable(); });
    // move emails that older orders stored inside the note into the dedicated field, and clean the note
    DB::statement("UPDATE orders SET email = substring(note from 'Email:\\s*([^\\s|]+)') WHERE email IS NULL AND note ~ 'Email:'");
    DB::statement("UPDATE orders SET note = NULLIF(btrim(regexp_replace(note, '\\s*\\|?\\s*Email:\\s*[^\\s|]+', '', 'g'), ' |'), '') WHERE note ~ 'Email:'");
    DB::statement("UPDATE customers c SET email = o.email FROM (SELECT DISTINCT ON (phone) phone, email FROM orders WHERE email IS NOT NULL ORDER BY phone, id DESC) o WHERE o.phone = c.phone AND (c.email IS NULL OR c.email = '')");
  }
  public function down(): void {}
};
