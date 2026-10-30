<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as MP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class PermissionController extends Controller {
  private function guard() { abort_unless(in_array(Auth::user()->role, ['super','admin']), 403); }
  public function show() {
    $this->guard();
    $roles = DB::table('roles')->orderBy('id')->get()->map(function ($r) { $r->p = json_decode($r->permissions ?: '{}', true) ?: []; return $r; });
    $users = DB::table('users')->leftJoin('roles','roles.id','=','users.role_id')->select('users.*','roles.name as rname')->orderBy('users.id')->get();
    return view('permissions', ['roles'=>$roles, 'users'=>$users, 'mods'=>MP::MODULES, 'levels'=>array_keys(MP::LEVELS)]);
  }
  public function save(Request $r) {
    $this->guard();
    foreach (DB::table('roles')->get() as $role) {
      $in = (array)$r->input("p.{$role->id}", []); $p = [];
      foreach (MP::MODULES as $k => $l) { $lv = $in[$k] ?? 'none'; $p[$k] = array_key_exists($lv, MP::LEVELS) ? $lv : 'none'; $p[$k.'_download'] = !empty($in[$k.'_download']); }
      DB::table('roles')->where('id',$role->id)->update(['permissions'=>json_encode($p), 'updated_at'=>now()]);
    }
    foreach ((array)$r->input('ur', []) as $uid => $rid) {
      if ((int)$uid === (int)Auth::id()) continue;
      DB::table('users')->where('id',(int)$uid)->whereNotIn('role',['super'])->update(['role_id'=>$rid ? (int)$rid : null, 'updated_at'=>now()]);
    }
    DB::table('audit_logs')->insert(['user_id'=>Auth::id(),'user_name'=>Auth::user()->name,'action'=>'permissions.update','subject'=>'roles','meta'=>json_encode([]),'ip'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]);
    return back()->with('ok','Permissions saved');
  }
}
