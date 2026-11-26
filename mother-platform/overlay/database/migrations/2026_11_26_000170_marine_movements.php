<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marine Supply: Port Arrivals & Departures (owner brief 26 Nov 2026).
 *  marine_port_visits = one visit of a vessel to one of our ports, entered by the Marine Desk from a confirmed source.
 *  Arrival and departure are two events of the same visit. Only Published visits are shown on the marine site.
 *  marine_vessel_photos = one approved photo per IMO with its credit. Supporting documents reuse marine_documents (new nullable visit_id).
 *  Separate from marine_port_calls, which holds calls announced by customer accounts (ETA / ETD, not confirmed events). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('marine_port_visits')) Schema::create('marine_port_visits', function (Blueprint $t) {
      $t->id(); $t->string('imo', 12)->index(); $t->string('mmsi', 12)->nullable(); $t->string('vessel', 120); $t->string('vessel_type', 60)->nullable();
      $t->string('port', 20)->index(); $t->string('visit_ref', 60)->nullable(); $t->string('berth', 120)->nullable();
      $t->timestamp('arrived_at')->nullable()->index(); $t->string('arrival_source', 20)->nullable(); $t->string('arrival_ref', 200)->nullable();
      $t->timestamp('departed_at')->nullable()->index(); $t->string('departure_source', 20)->nullable(); $t->string('departure_ref', 200)->nullable();
      $t->string('status', 12)->default('draft')->index(); $t->string('verified_by', 120)->nullable(); $t->timestamp('verified_at')->nullable(); $t->string('published_by', 120)->nullable(); $t->timestamp('published_at')->nullable();
      $t->string('internal_note', 1000)->nullable(); $t->string('feed_key', 120)->nullable()->unique(); $t->string('created_by', 120)->nullable(); $t->string('updated_by', 120)->nullable(); $t->timestamps();
      $t->index(['imo', 'port']);
    });
    if (!Schema::hasTable('marine_vessel_photos')) Schema::create('marine_vessel_photos', function (Blueprint $t) {
      $t->id(); $t->string('imo', 12)->unique(); $t->string('path', 190)->nullable(); $t->string('source_url', 400)->nullable(); $t->string('credit', 160); $t->string('approved_by', 120)->nullable(); $t->timestamp('approved_at')->nullable(); $t->timestamps();
    });
    if (Schema::hasTable('marine_documents') && !Schema::hasColumn('marine_documents', 'visit_id')) Schema::table('marine_documents', function (Blueprint $t) { $t->unsignedBigInteger('visit_id')->nullable()->index(); });
  }
  public function down(): void {
    if (Schema::hasTable('marine_documents') && Schema::hasColumn('marine_documents', 'visit_id')) Schema::table('marine_documents', function (Blueprint $t) { $t->dropColumn('visit_id'); });
    Schema::dropIfExists('marine_vessel_photos'); Schema::dropIfExists('marine_port_visits');
  }
};
