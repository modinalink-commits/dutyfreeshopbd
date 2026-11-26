<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marine Supply: vessels seen on AIS (public broadcast data) for the free ship tracking, name search and vessel details on marinedutyfreebd.com. One row per MMSI. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('marine_vessels')) return;
    Schema::create('marine_vessels', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('mmsi')->unique(); $t->unsignedInteger('imo')->nullable()->index(); $t->string('name', 60)->nullable()->index(); $t->string('callsign', 12)->nullable();
      $t->unsignedSmallInteger('ship_type')->nullable(); $t->unsignedSmallInteger('length')->nullable(); $t->unsignedSmallInteger('beam')->nullable(); $t->decimal('draught', 4, 1)->nullable();
      $t->string('destination', 40)->nullable(); $t->string('eta', 12)->nullable();
      $t->decimal('lat', 8, 5)->nullable(); $t->decimal('lon', 8, 5)->nullable(); $t->decimal('sog', 5, 1)->nullable(); $t->decimal('cog', 5, 1)->nullable(); $t->unsignedSmallInteger('heading')->nullable(); $t->unsignedTinyInteger('nav_status')->nullable();
      $t->timestamp('pos_at')->nullable(); $t->timestamp('static_at')->nullable(); $t->timestamp('seen_at')->nullable()->index(); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('marine_vessels'); }
};
