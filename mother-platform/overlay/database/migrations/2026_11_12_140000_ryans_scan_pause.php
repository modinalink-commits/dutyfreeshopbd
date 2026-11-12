<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Ryans scan: a background run started before the pos fix keeps re-reading the same 48 pages. Pause it (its loop exits when status != running); the next migration resumes from the saved position with the fixed code. */
return new class extends Migration {
  public function up(): void {
    DB::table('stores')->whereRaw("lower(domain) like '%ryans.com'")->where('scan_status', 'running')->update(['scan_status' => 'paused', 'updated_at' => now()]);
  }
  public function down(): void {}
};
