<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class SmsController extends Controller
{
    public function index(Request $request)
    {
        Sms::daily();
        $start = now()->timezone('Asia/Dhaka')->startOfDay()->utc();
        $sales = Sale::query()->with(['customer', 'trip.vehicle'])->where('created_at', '>=', $start)->latest('id')->get();
        $sms = SmsMessage::query()->where('created_at', '>=', $start)->get();
        $f = $request->query('f');
        $last = SmsMessage::query()->whereIn('sale_id', $sales->pluck('id'))->orderBy('id')->get()->keyBy('sale_id');
        $hist = $sales->filter(fn ($s) => match ($f) { 'failed' => ($last[$s->id]->status ?? null) === 'failed', 'reprinted' => $s->pos_prints + $s->a4_prints > 1, default => true })->take(100);
        $kind = in_array($request->query('t'), array_keys(Sms::TYPES), true) ? $request->query('t') : 'new_sale';
        $sample = $sales->first() ?? Sale::query()->latest('id')->first();
        $preview = Sms::render($kind, ['invoice_no' => $sample?->invoice_no ?? 'R-0001', 'total' => number_format((float) ($sample?->total ?? 0)), 'paid' => number_format((float) ($sample?->paid ?? 0)),
            'due' => number_format($sample?->due() ?? 0), 'paid_amount' => number_format((float) ($sample?->paid ?? 0)), 'empties' => 2, 'message' => 'Notice text']);
        return view('sms', ['sales' => $sales, 'sms' => $sms, 'hist' => $hist, 'last' => $last, 'f' => $f, 'kind' => $kind, 'preview' => $preview,
            'failed' => SmsMessage::query()->where('status', 'failed')->count(), 'configured' => Sms::configured(), 'recent' => SmsMessage::query()->latest('id')->limit(20)->get()]);
    }

    public function gateway(Request $request)
    {
        $data = $request->validate(['sms_provider' => ['nullable', 'string', 'max:60'], 'sms_sender' => ['nullable', 'string', 'max:30'], 'sms_endpoint' => ['nullable', 'url', 'max:500'],
            'sms_key' => ['nullable', 'string', 'max:300'], 'sms_lang' => ['required', Rule::in(['bn', 'en'])], 'sms_rate' => ['nullable', 'numeric', 'min:0'],
            'auto' => ['array'], 'tpl' => ['array'], 'tpl.*' => ['nullable', 'string', 'max:600']]);
        foreach (['sms_provider', 'sms_sender', 'sms_endpoint', 'sms_lang', 'sms_rate'] as $k) Setting::put($k, $data[$k] ?? null);
        if (! empty($data['sms_key'])) Setting::put('sms_key', Crypt::encryptString($data['sms_key']));
        foreach (array_keys(Sms::TYPES) as $k) Setting::put('sms_auto_'.$k, ! empty($data['auto'][$k]) ? '1' : '0');
        foreach ($data['tpl'] ?? [] as $k => $text) {
            [$kind, $lang] = array_pad(explode('|', $k), 2, null);
            if (isset(Sms::TYPES[$kind]) && in_array($lang, ['bn', 'en'], true)) Setting::put("sms_tpl_{$kind}_{$lang}", $text ?: null);
        }
        AuditLog::record('sms_settings', 'Gateway and templates saved'.(! empty($data['sms_key']) ? ' (new key)' : ''));
        return back()->with('ok', 'SMS settings saved.');
    }

    public function retry()
    {
        $ids = SmsMessage::query()->whereIn('status', ['failed', 'off'])->whereNotNull('to')->where('attempts', '<', 5)->pluck('id');
        foreach ($ids as $id) Sms::send($id);
        AuditLog::record('sms_retry', $ids->count().' messages');
        return back()->with('ok', $ids->count().' SMS retried.');
    }

    public function notice(Request $request)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:300'], 'to' => ['required', Rule::in(['due', 'all'])]]);
        $q = Customer::query()->where('is_active', true)->whereNotNull('phone')->get();
        if ($data['to'] === 'due') $q = $q->filter(fn ($c) => $c->due() > 0);
        foreach ($q as $c) Sms::queue('notice', $c->phone, Sms::render('notice', ['message' => $data['message']]), null, $c->id);
        AuditLog::record('sms_notice', $q->count().' customers');
        return back()->with('ok', 'Notice queued for '.$q->count().' customers.');
    }
}
