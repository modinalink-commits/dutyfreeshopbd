<?php
namespace App\Http\Controllers;
use App\Support\PublishNotice as N;
use App\Support\Procure;
use App\Support\ProfitFloor;
use App\Support\SmsMarketing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Back in Store SMS: settings, a count for one product, a test to one number. Owner / Admin only (it spends SMS balance). */
class PublishNoticeController extends Controller {
  private function gate(): void { abort_unless(ProfitFloor::admin(), 403); }
  public function index(Request $r) {
    $this->gate(); $p = null; $cnt = null;
    if ($r->filled('pid')) { $p = DB::table('products')->find((int)$r->query('pid')); if ($p) $cnt = count(N::recipients((int)$p->id)); }
    $sample = $p ?: DB::table('products')->where('published', true)->orderByDesc('id')->first();
    $body = $sample ? N::body($sample) : N::text(); [$parts, $len] = SmsMarketing::parts($body);
    return view('publish_notice', ['on' => N::on(), 'text' => N::text(), 'days' => N::days(), 'minOff' => N::minOff(), 'maxAt' => N::maxAtOnce(), 'cap' => N::dailyCap(), 'minStock' => N::minStock(), 'ready' => N::ready(), 'today' => N::sentToday(),
      'p' => $p, 'cnt' => $cnt, 'body' => $body, 'parts' => $parts, 'len' => $len, 'unit' => SmsMarketing::unit(),
      'status' => DB::table('sms_logs')->where('event', N::EVENT)->where('created_at', '>=', now()->subDays(7))->selectRaw('status, count(*) as n, coalesce(sum(parts),0) as parts, coalesce(sum(cost),0) as cost, max(updated_at) as last')->groupBy('status')->orderByDesc('n')->get(),
      'log' => DB::table('audit_logs')->where('action', 'product.back_in_store_sms')->orderByDesc('id')->limit(20)->get()]);
  }
  public function save(Request $r) {
    $this->gate();
    $d = $r->validate(['act' => 'required|in:settings,test,send', 'on' => 'nullable|in:0,1', 'text' => 'nullable|string|max:400', 'days' => 'nullable|integer|min:1|max:365', 'min_off' => 'nullable|integer|min:0|max:10080', 'max_products' => 'nullable|integer|min:1|max:500', 'daily_cap' => 'nullable|integer|min:0|max:200000', 'min_stock' => 'nullable|integer|min:0|max:100000', 'phone' => 'nullable|string|max:20', 'pid' => 'nullable|integer']);
    if ($d['act'] === 'settings') {
      $was = ['on' => N::on(), 'text' => N::text(), 'days' => N::days(), 'min_off' => N::minOff(), 'max_products' => N::maxAtOnce(), 'daily_cap' => N::dailyCap()];
      $txt = trim((string)($d['text'] ?? '')); if ($txt !== '' && !str_contains($txt, '{product}')) return back()->withInput()->with('err', 'The text must contain {product}.');
      Procure::put('pubsms_on', ($d['on'] ?? '0') === '1' ? '1' : '0'); Procure::put('pubsms_text', $txt);
      foreach (['days', 'min_off', 'max_products', 'daily_cap', 'min_stock'] as $k) if (isset($d[$k])) Procure::put('pubsms_' . $k, (string)(int)$d[$k]);
      Procure::audit('Back in Store SMS setting changed', 'Back in Store SMS', $was, ['on' => N::on(), 'text' => N::text(), 'days' => N::days(), 'min_off' => N::minOff(), 'max_products' => N::maxAtOnce(), 'daily_cap' => N::dailyCap(), 'by' => auth()->user()->name ?? null]);
      return back()->with('ok', 'Saved.');
    }
    $p = DB::table('products')->find((int)($d['pid'] ?? 0)); if (!$p) return back()->with('err', 'Choose a product first (enter its ID and press Count).');
    if ($d['act'] === 'test') {
      $num = \App\Support\Sms::number((string)($d['phone'] ?? '')); if (!$num) return back()->with('err', 'Enter a valid mobile number.');
      [$ok, $m] = \App\Support\Sms::send($num, N::body($p)); return back()->with($ok ? 'ok' : 'err', 'Test to …' . substr($num, -4) . ': ' . ($ok ? 'sent' : 'failed · ' . $m));
    }
    if (!$p->published) return back()->with('err', 'This product is unpublished.');
    [$q, $held] = N::notify((int)$p->id); return back()->with('ok', $q . ' SMS queued for ' . $p->name . ($held ? ' (' . $held . ' held by the daily cap)' : '') . '.');
  }
}
