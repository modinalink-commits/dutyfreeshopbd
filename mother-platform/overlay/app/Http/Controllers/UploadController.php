<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class UploadController extends Controller {
  function image(Request $r) {
    $r->validate(['file' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:8192']);
    $f = $r->file('file');
    $dir = public_path('uploads');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = date('ymd').'-'.bin2hex(random_bytes(5)).'.'.strtolower($f->getClientOriginalExtension() ?: 'jpg');
    $f->move($dir, $name);
    return response()->json(['url' => '/uploads/'.$name]);
  }
}
