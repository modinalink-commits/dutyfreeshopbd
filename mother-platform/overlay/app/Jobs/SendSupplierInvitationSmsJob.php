<?php
namespace App\Jobs;

use App\Support\Procure;
use App\Support\Sms;
use App\Support\SupplierPortal as SP;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

/** Sends the single-use activation link by SMS. The token is held only in memory for this send; the log keeps a masked message. */
class SendSupplierInvitationSmsJob
{
  use Dispatchable;
  public function __construct(public int $userId, public int $logId, public string $token) {}
  public function handle(): void {
    try {
      $u = DB::table('supplier_users as u')->join('suppliers as s','s.id','=','u.supplier_id')->where('u.id', $this->userId)->first(['u.*','s.name as sname']);
      if (!$u || $u->status !== 'active') { DB::table('supplier_sms_log')->where('id', $this->logId)->update(['status'=>'failed','response'=>'Account not active','updated_at'=>now()]); return; }
      $url = rtrim(Procure::base(), '/').'/supplier-portal/activate/'.$this->token;
      $body = "{$u->sname},\nYour ".SP::brand()." account is ready.\nUsername: {$u->username}\nActivate your account: $url\nThis link is for your account only and works once.";
      [$ok, $msg] = Sms::send((string)$u->mobile, $body);
      DB::table('supplier_sms_log')->where('id', $this->logId)->update(['status'=>$ok ? 'sent' : 'failed','response'=>mb_substr(str_replace($this->token, '***', $msg), 0, 190),'sent_at'=>$ok ? now() : null,'updated_at'=>now()]);
      DB::table('supplier_users')->where('id', $u->id)->update(['invite_status'=>$ok ? 'sent' : 'failed','invite_sent_at'=>$ok ? now() : $u->invite_sent_at,'updated_at'=>now()]);
    } catch (\Throwable $e) { DB::table('supplier_sms_log')->where('id', $this->logId)->update(['status'=>'failed','response'=>'Error','updated_at'=>now()]); }
  }
}
