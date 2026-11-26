<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marine Supply: files sent with a requisition (Excel, Word, PDF, photos) are kept with the order. One nullable column on marine_documents. */
return new class extends Migration {
  public function up(): void { if (Schema::hasTable('marine_documents') && !Schema::hasColumn('marine_documents', 'order_id')) Schema::table('marine_documents', function (Blueprint $t) { $t->unsignedBigInteger('order_id')->nullable()->index(); }); }
  public function down(): void { if (Schema::hasTable('marine_documents') && Schema::hasColumn('marine_documents', 'order_id')) Schema::table('marine_documents', function (Blueprint $t) { $t->dropColumn('order_id'); }); }
};
