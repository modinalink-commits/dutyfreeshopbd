<?php
use Illuminate\Database\Migrations\Migration;

/** Owner 27 Nov 2026: the AI working-hours window is removed; the AI answers all day. Re-tick "Use working hours" on the AI settings page to restore it. */
return new class extends Migration {
  public function up(): void {
    try {
      $c = \App\Support\AiSchedule::get();
      \App\Support\Sms::setSecret('ai_schedule', json_encode(['on'=>false, 'w'=>$c['w'] ?? [['00:00', '10:00'], ['21:00', '24:00']]]));
    } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
