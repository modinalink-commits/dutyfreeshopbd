<?php
use Illuminate\Database\Migrations\Migration;

/** Superseded: an older placeholder table already uses the name email_logs, so the customer email outbox lives in mail_outbox (next migration). Nothing to do here. */
return new class extends Migration { public function up(): void {} public function down(): void {} };
