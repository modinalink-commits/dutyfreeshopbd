<?php
namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Scheduled orders: a date/time is mandatory, and the server (background worker) moves them Scheduled -> Approved when it arrives. */
class OrderSchedule
{
    const TZ = 'Asia/Dhaka';

    /** local Dhaka date + time -> UTC Carbon (stored in UTC like every other timestamp). */
    public static function parse(?string $date, ?string $time): ?Carbon
    {
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
        $time = ($time && preg_match('/^\d{1,2}:\d{2}/', $time)) ? substr($time, 0, 5) : '00:00';
        try { return Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time, self::TZ)->utc(); } catch (\Throwable $e) { return null; }
    }

    public static function label($utc): string
    {
        return $utc ? Carbon::parse($utc, 'UTC')->timezone(self::TZ)->format('d M Y · h:i A') : '';
    }
    public static function short($utc): string
    {
        return $utc ? Carbon::parse($utc, 'UTC')->timezone(self::TZ)->format('d M · h:i A') : '';
    }
    public static function remaining($utc): string
    {
        if (!$utc) return '';
        $t = Carbon::parse($utc, 'UTC'); if ($t->lte(now())) return 'due now';
        $m = (int)now()->diffInMinutes($t); $d = intdiv($m, 1440); $h = intdiv($m % 1440, 60);
        if ($d >= 1) return $d . ' day' . ($d > 1 ? 's' : '') . ' remaining';
        return $h >= 1 ? $h . 'h ' . ($m % 60) . 'm remaining' : ($m % 60) . ' min remaining';
    }

    /** Put an order in Scheduled with a date/time, or change the date/time of one already scheduled. */
    public static function set(int $id, ?string $date, ?string $time, ?string $note, ?int $uid, string $uname): array
    {
        $o = DB::table('orders')->find($id); if (!$o) return [false, 'Order not found'];
        $at = self::parse($date, $time);
        if (!$at) return [false, 'Scheduled date is required.'];
        if ($at->lte(now())) return [false, 'Scheduled date/time must be in the future (Asia/Dhaka time).'];
        $changed = $o->status === 'Scheduled';
        DB::transaction(function () use ($o, $id, $at, $note, $uid, $uname, $changed) {
            DB::table('orders')->where('id', $id)->update(['status' => 'Scheduled', 'scheduled_at' => $at, 'scheduled_by' => $uid, 'scheduled_note' => $note ? mb_substr($note, 0, 500) : null, 'auto_approved' => false, 'updated_at' => now()]);
            DB::table('order_status_logs')->insert(['order_id' => $id, 'user_id' => $uid, 'user_name' => $uname, 'from_status' => $o->status, 'to_status' => 'Scheduled',
                'note' => ($changed ? 'Schedule changed to ' : 'Scheduled for ') . self::label($at) . ($note ? ' — ' . $note : ''), 'created_at' => now(), 'updated_at' => now()]);
        });
        return [true, 'Scheduled for ' . self::label($at)];
    }

    /** Cancel the schedule: scheduled_at is cleared so the worker can never approve it; the order goes back to its earlier stage. */
    public static function cancel(int $id, ?int $uid, string $uname): array
    {
        $o = DB::table('orders')->find($id); if (!$o || $o->status !== 'Scheduled') return [false, 'This order is not scheduled.'];
        $prev = DB::table('order_status_logs')->where('order_id', $id)->where('to_status', 'Scheduled')->whereNotNull('from_status')->where('from_status', '<>', 'Scheduled')->orderByDesc('id')->value('from_status');
        $to = in_array($prev, ['New', 'Pre Order', 'Assign', 'Pending', 'AI Order'], true) ? $prev : 'Pending';
        DB::transaction(function () use ($o, $id, $uid, $uname, $to) {
            DB::table('orders')->where('id', $id)->update(['status' => $to, 'scheduled_at' => null, 'scheduled_note' => null, 'updated_at' => now()]);
            DB::table('order_status_logs')->insert(['order_id' => $id, 'user_id' => $uid, 'user_name' => $uname, 'from_status' => 'Scheduled', 'to_status' => $to, 'note' => 'Schedule cancelled (was ' . self::label($o->scheduled_at) . ')', 'created_at' => now(), 'updated_at' => now()]);
        });
        return [true, 'Schedule cancelled — order is now ' . $to];
    }

    /** Called every ~30 s by the background worker and by `php artisan orders:process-scheduled`. Only status=Scheduled AND scheduled_at<=now is touched. */
    public static function tick(): int
    {
        $guard = (string)DB::table('settings')->where('key', 'sched_pay_guard')->value('value') !== '0';
        $due = DB::table('orders')->where('status', 'Scheduled')->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->limit(200)->get();
        $n = 0;
        foreach ($due as $o) {
            if ($guard && $o->pay_status === 'Verification Pending') continue; // stays Scheduled until the payment is verified
            $ok = DB::transaction(function () use ($o) {
                $c = DB::table('orders')->where('id', $o->id)->where('status', 'Scheduled')->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now())
                    ->update(['status' => 'Approved', 'approved_at' => now(), 'auto_approved' => true, 'updated_at' => now()]);
                if (!$c) return false; // changed by someone meanwhile — never override
                DB::table('order_status_logs')->insert(['order_id' => $o->id, 'user_id' => null, 'user_name' => 'System', 'from_status' => 'Scheduled', 'to_status' => 'Approved',
                    'note' => 'Order automatically changed from Scheduled to Approved (scheduled for ' . self::label($o->scheduled_at) . ')', 'created_at' => now(), 'updated_at' => now()]);
                return true;
            });
            if ($ok) $n++;
        }
        if ($n) {
            Cache::put('sched.notice', ['n' => $n, 'at' => now()->toDateTimeString()], 86400);
            try { DB::table('settings')->updateOrInsert(['key' => 'sched_last_auto'], ['value' => $n . ' Scheduled orders moved to Approved · ' . now(self::TZ)->format('d M h:i A'), 'updated_at' => now(), 'created_at' => now()]); } catch (\Throwable $e) {}
        }
        return $n;
    }
}
