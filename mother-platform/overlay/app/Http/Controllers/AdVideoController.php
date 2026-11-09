<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Ad Video Maker: builds Facebook-size animated videos (square, portrait, story/reel, landscape) from the products of a category page plus a voice/music track.
 *  The video is rendered in the admin's own browser (canvas + MediaRecorder), so nothing heavy runs on the server. The server only keeps the audio library. */
class AdVideoController extends Controller {
  private function dirs(): array { return ['/ad-audio' => public_path('ad-audio'), '/uploads/ad-audio' => public_path('uploads/ad-audio')]; }
  public function index() { return view('ad_video', ['audios' => $this->audios()]); }
  private function audios(): array {
    $o = [];
    foreach ($this->dirs() as $url => $dir) { if (!is_dir($dir)) continue; foreach (glob($dir . '/*.{mp3,m4a,aac,wav,ogg}', GLOB_BRACE) ?: [] as $f) $o[] = ['name' => basename($f), 'url' => $url . '/' . rawurlencode(basename($f)), 'kb' => (int)round(filesize($f) / 1024)]; }
    return $o;
  }
  public function upload(Request $r) {
    $r->validate(['audio' => 'required|file|max:20480|mimes:mp3,m4a,aac,wav,ogg,mpga']);
    $f = $r->file('audio'); $dir = public_path('uploads/ad-audio'); if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($f->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'audio';
    $f->move($dir, $base . '-' . substr(md5(uniqid('', true)), 0, 6) . '.' . strtolower($f->getClientOriginalExtension() ?: 'mp3'));
    return redirect('/admin/ad-video')->with('ok', 'Audio added to the library.');
  }
}
