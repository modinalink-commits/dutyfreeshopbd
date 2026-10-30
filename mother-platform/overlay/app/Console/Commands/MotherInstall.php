<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class MotherInstall extends Command {
  protected $signature = 'mother:install';
  protected $description = 'Create first super admin and first store';
  public function handle() {
    if (!DB::table('users')->where('role','super')->exists()) {
      DB::table('users')->insert(['name'=>'Mohammad Faruk','email'=>env('ADMIN_EMAIL','admin@example.com'),'password'=>Hash::make(env('ADMIN_PASSWORD')),'role'=>'super','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
      $this->info('admin created');
    }
    if (!DB::table('discount_rules')->exists()) {
      $rules = [
      ['membership','Membership discount','Silver, Gold, Platinum and Diamond get a % on every order.',['Silver %'=>2,'Gold %'=>4,'Platinum %'=>6,'Diamond %'=>8]],
      ['payment','Payment method discount','Pay online, save more.',['Card %'=>3,'bKash / Nagad %'=>2,'ShurjoPay / Amarpay %'=>2,'Cash on delivery %'=>0]],
      ['amount','Order amount discount','Spend more, save more.',['Over 5000 TK %'=>2,'Over 10000 TK %'=>3,'Over 20000 TK %'=>5,'Max discount per order TK'=>2000]],
      ['bundle','Bundle discount','Buy products together and get a price off the set.',['Bundle %'=>10,'Minimum products'=>2]],
      ['bogo','Buy X get Y','Buy 2 get 1 free.',['Buy quantity'=>2,'Get quantity free'=>1]],
      ['bulk','Quantity (bulk) discount','Price drops as quantity rises.',['2 cartons %'=>1,'5 cartons %'=>3,'10 cartons %'=>5]],
      ['first','First order discount','Welcome offer for new customers.',['Discount %'=>5,'Max TK'=>300]],
      ['freedelivery','Free delivery rule','Free delivery over an amount.',['Free over TK'=>3000]],
      ['catalog','Category / brand / product discount','Percent or fixed off selected items.',['Default %'=>0]],
      ['flash','Flash sale and Plus sale','Timed offers with countdown.',['Stock limit per product'=>50,'Per customer limit'=>2]],
      ['loyalty','Loyalty points redeem','Customers pay part of the bill with points.',['1 point value TK'=>1,'Max redeem % of order'=>20,'Points per 100 TK'=>1]],
      ['referral','Referral / affiliate','Friend gets a discount, referrer earns a reward.',['Friend discount TK'=>100,'Referrer reward TK'=>100]],
      ['birthday','Birthday / festival discount','Auto discount on birthday or festival.',['Birthday %'=>5,'Valid days'=>3]],
      ['abandoned','Abandoned cart offer','Auto offer if a cart is left for some hours.',['After hours'=>6,'Discount %'=>3]],
      ['source','Order source discount','Special price by source: Landing page, Facebook, TikTok, YouTube.',['Discount %'=>0]]
      ];
      foreach ($rules as $i => $r) DB::table('discount_rules')->insert(['key'=>$r[0],'title'=>$r[1],'help'=>$r[2],'enabled'=>false,'config'=>json_encode($r[3]),'position'=>$i,'created_at'=>now(),'updated_at'=>now()]);
    }
    if (!DB::table('stores')->exists()) DB::table('stores')->insert(['name'=>'DutyFreeBD','domain'=>'dutyfreebd.com','created_at'=>now(),'updated_at'=>now()]);
  }
}
