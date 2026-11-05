<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\CustomerCylinder;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Customer SMS. The gateway key is stored encrypted on the server and never sent to a browser or the mobile app.
 * Messages are written first and sent after the response, so a slow gateway never holds up a sale.
 */
class Sms
{
    public const TYPES = [
        'new_sale' => ['New sale confirmation', 'on'], 'invoice' => ['Invoice confirmation', 'off'], 'payment' => ['Payment received', 'on'], 'partial' => ['Partial payment', 'on'],
        'due' => ['Due payment reminder', 'every 7 days'], 'empty' => ['Empty cylinder pending', 'after 3 days'], 'delivery' => ['Delivery confirmation', 'off'], 'notice' => ['Special notices', 'manual'],
    ];

    public const DEFAULTS = [
        'new_sale' => ['bn' => 'প্রিয় গ্রাহক, {company} থেকে আপনার LPG সিলিন্ডার ক্রয়ের ইনভয়েস তৈরি হয়েছে। Invoice: {invoice_no}, Total: ৳{total}, Paid: ৳{paid}, Due: ৳{due}। যোগাযোগ: {phone}।',
            'en' => 'Dear customer, your LPG invoice from {company} is ready. Invoice: {invoice_no}, Total: Tk {total}, Paid: Tk {paid}, Due: Tk {due}. Call {phone}.'],
        'invoice' => ['bn' => 'প্রিয় গ্রাহক, আপনার ইনভয়েস {invoice_no} নিশ্চিত হয়েছে। মোট ৳{total}। {company}', 'en' => 'Dear customer, invoice {invoice_no} is confirmed. Total Tk {total}. {company}'],
        'payment' => ['bn' => 'প্রিয় গ্রাহক, {company} আপনার ৳{paid_amount} পেমেন্ট গ্রহণ করেছে। Invoice: {invoice_no}, অবশিষ্ট বকেয়া: ৳{due}। ধন্যবাদ।',
            'en' => 'Dear customer, {company} received your payment of Tk {paid_amount}. Invoice: {invoice_no}, remaining due: Tk {due}. Thank you.'],
        'partial' => ['bn' => 'প্রিয় গ্রাহক, ইনভয়েস {invoice_no}-এ ৳{paid} পরিশোধ হয়েছে, বাকি ৳{due}। {company}', 'en' => 'Dear customer, Tk {paid} paid on invoice {invoice_no}; Tk {due} is still due. {company}'],
        'due' => ['bn' => 'প্রিয় গ্রাহক, {company}-এ আপনার মোট বকেয়া ৳{due}। অনুগ্রহ করে পরিশোধ করুন। যোগাযোগ: {phone}।', 'en' => 'Dear customer, your due with {company} is Tk {due}. Please pay. Call {phone}.'],
        'empty' => ['bn' => 'প্রিয় গ্রাহক, আপনার কাছে আমাদের {empties}টি খালি সিলিন্ডার আছে। অনুগ্রহ করে ফেরত দিন। {company}', 'en' => 'Dear customer, you still hold {empties} of our empty cylinders. Please return them. {company}'],
        'delivery' => ['bn' => 'প্রিয় গ্রাহক, আপনার অর্ডার {invoice_no} পৌঁছে দেওয়া হয়েছে। {company}', 'en' => 'Dear customer, your order {invoice_no} has been delivered. {company}'],
        'notice' => ['bn' => '{message} — {company}', 'en' => '{message} — {company}'],
    ];

    public static function on(string $kind): bool
    {
        return Setting::get('sms_auto_'.$kind, self::TYPES[$kind][1] === 'off' || self::TYPES[$kind][1] === 'manual' ? '0' : '1') === '1';
    }

    public static function template(string $kind, ?string $lang = null): string
    {
        $lang = $lang ?? Setting::get('sms_lang', 'bn');
        return Setting::get("sms_tpl_{$kind}_{$lang}") ?: self::DEFAULTS[$kind][$lang];
    }

    public static function render(string $kind, array $vars): string
    {
        $vars += ['company' => Setting::get('company_name', 'NK Corporation'), 'phone' => Setting::get('phone', '')];
        return strtr(self::template($kind), collect($vars)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
    }

    public static function configured(): bool
    {
        return (bool) Setting::get('sms_endpoint') && (bool) Setting::get('sms_key');
    }

    /** Write a message now, send it after the response. */
    public static function queue(string $kind, ?string $to, string $body, ?int $saleId = null, ?int $customerId = null): ?SmsMessage
    {
        $to = $to ? Customer::normalisePhone($to) : null;
        if ($kind === 'otp' && ! self::configured()) return SmsMessage::create(['kind' => $kind, 'to' => $to, 'body' => 'OTP (hidden)', 'customer_id' => $customerId, 'status' => 'off']);
        $m = SmsMessage::create(['kind' => $kind, 'to' => $to, 'body' => $body, 'sale_id' => $saleId, 'customer_id' => $customerId,
            'status' => ! $to || strlen($to) < 11 ? 'no_mobile' : (self::configured() ? 'queued' : 'off')]);
        if ($m->status === 'queued') \Illuminate\Support\defer(fn () => self::send($m->id));
        return $m;
    }

    public static function send(int $id): void
    {
        $m = SmsMessage::find($id);
        if (! $m || ! in_array($m->status, ['queued', 'failed', 'off'], true) || ! $m->to) return;
        if (! self::configured()) { $m->update(['status' => 'off']); return; }
        try {
            $key = Crypt::decryptString((string) Setting::get('sms_key'));
            $url = strtr((string) Setting::get('sms_endpoint'), ['{api_key}' => rawurlencode($key), '{sender}' => rawurlencode((string) Setting::get('sms_sender', '')),
                '{to}' => rawurlencode('88'.ltrim($m->to, '+')), '{message}' => rawurlencode($m->body)]);
            $res = Http::timeout(15)->get($url);
            $m->update(['status' => $res->successful() ? 'sent' : 'failed', 'response' => mb_substr($res->status().' '.$res->body(), 0, 250), 'attempts' => $m->attempts + 1, 'sent_at' => $res->successful() ? now() : null] + ($m->kind === 'otp' ? ['body' => 'OTP (hidden)'] : []));
        } catch (Throwable $e) {
            $m->update(['status' => 'failed', 'response' => mb_substr($e->getMessage(), 0, 250), 'attempts' => $m->attempts + 1]);
        }
    }

    public static function forSale(Sale $sale): void
    {
        if ($sale->stage === 'voided') return;
        $kind = self::on('new_sale') ? 'new_sale' : (self::on('invoice') ? 'invoice' : null);
        if (! $kind) return;
        $due = max(0, (float) $sale->total - (float) $sale->paid);
        self::queue($kind, $sale->buyerPhone(), self::render($kind, ['invoice_no' => $sale->invoice_no, 'total' => number_format((float) $sale->total), 'paid' => number_format((float) $sale->paid), 'due' => number_format($due)]),
            $sale->id, $sale->customer_id);
    }

    public static function forPayment(Customer $c, float $amount, ?Sale $sale): void
    {
        $kind = $sale && $sale->due() > 0 && self::on('partial') ? 'partial' : (self::on('payment') ? 'payment' : null);
        if (! $kind) return;
        self::queue($kind, $c->phone, self::render($kind, ['paid_amount' => number_format($amount), 'paid' => number_format($amount), 'invoice_no' => $sale?->invoice_no ?? '—', 'due' => number_format(max(0, $c->due()))]), $sale?->id, $c->id);
    }

    /** Due and empty-cylinder reminders. Runs at most once a day, from normal page loads (there is no cron). */
    public static function daily(): int
    {
        $today = now()->timezone('Asia/Dhaka')->toDateString();
        if (Setting::get('sms_daily_ran') === $today) return 0;
        Setting::put('sms_daily_ran', $today);
        $n = 0;
        if (self::on('due')) {
            foreach (Customer::query()->where('is_active', true)->whereNotNull('phone')->get() as $c) {
                $due = $c->due();
                if ($due <= 0) continue;
                $last = SmsMessage::query()->where('customer_id', $c->id)->where('kind', 'due')->latest('id')->value('created_at');
                if ($last && $last->gt(now()->subDays(7))) continue;
                self::queue('due', $c->phone, self::render('due', ['due' => number_format($due)]), null, $c->id); $n++;
            }
        }
        if (self::on('empty')) {
            $holders = CustomerCylinder::query()->where('created_at', '<=', now()->subDays(3))->selectRaw('customer_id, sum(qty_delta) q')->groupBy('customer_id')->havingRaw('sum(qty_delta) > 0')->pluck('q', 'customer_id');
            foreach ($holders as $cid => $q) {
                $c = Customer::find($cid);
                if (! $c?->phone) continue;
                $last = SmsMessage::query()->where('customer_id', $cid)->where('kind', 'empty')->latest('id')->value('created_at');
                if ($last && $last->gt(now()->subDays(3))) continue;
                self::queue('empty', $c->phone, self::render('empty', ['empties' => (int) $q]), null, $cid); $n++;
            }
        }
        return $n;
    }
}
