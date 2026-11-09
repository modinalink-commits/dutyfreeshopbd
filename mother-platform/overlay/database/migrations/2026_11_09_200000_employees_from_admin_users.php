<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void {
    $have = DB::table('employees')->pluck('name')->map(fn($n) => mb_strtolower(trim($n)))->all();
    foreach (DB::table('users')->orderBy('id')->get() as $u) {
      $n = trim((string)$u->name);
      if ($n === '' || in_array(mb_strtolower($n), $have, true)) continue;
      DB::table('employees')->insert(['name' => $n, 'department' => ($u->role ?? '') === 'admin' ? 'Admin' : 'Staff', 'phone' => $u->phone ?? null, 'salary' => 0, 'joined_at' => substr((string)($u->created_at ?? now()), 0, 10), 'active' => (bool)($u->active ?? true), 'created_at' => now(), 'updated_at' => now()]);
      $have[] = mb_strtolower($n);
    }
  }
  public function down(): void {}
};
