<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void {
    $have = DB::table('employees')->pluck('name')->map(fn($n) => mb_strtolower(trim($n)))->all();
    foreach (['Niham', 'Sakib', 'Islam', 'Mehedi', 'Mamun'] as $n) {
      if (in_array(mb_strtolower($n), $have, true)) continue;
      $u = DB::table('users')->whereRaw('lower(name) like ?', [mb_strtolower($n) . '%'])->first();
      DB::table('employees')->insert(['name' => $n, 'department' => 'Staff', 'phone' => $u->phone ?? null, 'salary' => 0, 'joined_at' => now()->toDateString(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
  }
  public function down(): void {}
};
