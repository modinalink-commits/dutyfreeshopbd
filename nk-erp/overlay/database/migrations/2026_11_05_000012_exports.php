<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('report', 40);
            $table->string('format', 12);
            $table->json('filters')->nullable();
            $table->unsignedInteger('rows')->default(0);
            $table->string('path', 255)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->string('status', 10)->default('ready'); // ready, blocked, expired
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('downloads')->default(0);
            $table->boolean('archived')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
