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
    if (!DB::table('couriers')->exists()) foreach ([['Pathao',1,1],['Steadfast',0,0],['RedX',0,0],['Sundarban Courier',0,0],['Delivery Tiger',0,0],['S A Paribahan',0,0]] as $c) DB::table('couriers')->insert(['name'=>$c[0],'active'=>(bool)$c[1],'is_default'=>(bool)$c[2],'created_at'=>now(),'updated_at'=>now()]);
    if (!DB::table('order_sources')->exists()) foreach (['messenger','youtube','google','tiktok','instagram','Landing Page','Whatsapp','FB','Web Site'] as $s) DB::table('order_sources')->insert(['name'=>$s,'created_at'=>now(),'updated_at'=>now()]);
    if (!DB::table('membership_levels')->exists()) foreach ([['Silver / Standard',0,2],['Gold / Premium',50000,4],['Platinum / VIP',150000,6],['Diamond',400000,8]] as $m) DB::table('membership_levels')->insert(['name'=>$m[0],'min_spent'=>$m[1],'discount_percent'=>$m[2],'created_at'=>now(),'updated_at'=>now()]);
    if (!DB::table('expense_purposes')->exists()) foreach ([['Internet Bill',1],['Office Food',1],['Fund Transfer',0],['Boost / Advertising',1],['Website Service Charge',1],['Investment Return',0],['Bill Statement',1],['Courier Charge',1],['Salary',1],['Supplier Bill',0],['Travel Allowance',1],['Loan Paid',0],['Investor Profit',0],['Office Stationary',0],['Others',1]] as $p) DB::table('expense_purposes')->insert(['name'=>$p[0],'is_expense'=>(bool)$p[1],'created_at'=>now(),'updated_at'=>now()]);
    if (!DB::table('attributes')->exists()) foreach (['Weight','Color','Size'] as $a) DB::table('attributes')->insert(['name'=>$a,'created_at'=>now(),'updated_at'=>now()]);
    if (!DB::table('pages')->exists()) foreach ([['about-us','About Us'],['return-policy','Return Policy'],['privacy-policy','Privacy Policy'],['delivery-policy','Delivery policy'],['blog','Blog']] as $pg) DB::table('pages')->insert(['slug'=>$pg[0],'title'=>$pg[1],'created_at'=>now(),'updated_at'=>now()]);
    if (!DB::table('roles')->exists()) foreach (['Order desk','Packing','Courier desk','Accounts','Manager'] as $r) DB::table('roles')->insert(['name'=>$r,'permissions'=>json_encode([]),'created_at'=>now(),'updated_at'=>now()]);
    if (!DB::table('districts')->exists()) foreach (["Bagerhat","Bandarban","Barguna","Barishal","Bhola","Bogura","Brahmanbaria","Chandpur","Chapai Nawabganj","Chattogram","Chuadanga","Cox's Bazar","Cumilla","Dhaka","Dinajpur","Faridpur","Feni","Gaibandha","Gazipur","Gopalganj","Habiganj","Jamalpur","Jashore","Jhalokati","Jhenaidah","Joypurhat","Khagrachhari","Khulna","Kishoreganj","Kurigram","Kushtia","Lakshmipur","Lalmonirhat","Madaripur","Magura","Manikganj","Meherpur","Moulvibazar","Munshiganj","Mymensingh","Naogaon","Narail","Narayanganj","Narsingdi","Natore","Netrokona","Nilphamari","Noakhali","Pabna","Panchagarh","Patuakhali","Pirojpur","Rajbari","Rajshahi","Rangamati","Rangpur","Satkhira","Shariatpur","Sherpur","Sirajganj","Sunamganj","Sylhet","Tangail","Thakurgaon"] as $n) DB::table('districts')->insert(['name'=>$n,'delivery_charge'=>0,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    if (!DB::table('money_accounts')->exists()) foreach ([['ShurjoPay','gateway'],['Nagad personal','mobile'],['bKash personal','mobile'],['Mutual Trust Bank','bank'],['Brac Bank','bank'],['City Bank','bank'],['Eastern Bank','bank'],['Islami Bank','bank'],['Cash','cash']] as [$n,$t]) DB::table('money_accounts')->insert(['name'=>$n,'type'=>$t,'opening'=>0,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    $defaults = ['Order desk'=>['orders'=>'edit','products'=>'view','customers'=>'view','stafflog'=>'view','reports'=>'view'], 'Packing'=>['orders'=>'view','products'=>'view','stafflog'=>'view','reports'=>'view'], 'Courier desk'=>['orders'=>'edit','products'=>'view','customers'=>'view','couriers'=>'edit','stafflog'=>'view','reports'=>'view'], 'Accounts'=>['orders'=>'view','products'=>'view','purchase'=>'view','accounts'=>'all','reports'=>'all','customers'=>'view','stafflog'=>'view','accounts_download'=>true,'reports_download'=>true], 'Manager'=>['orders'=>'all','products'=>'all','purchase'=>'all','accounts'=>'all','reports'=>'all','customers'=>'all','couriers'=>'all','marketing'=>'all','settings'=>'view','hr'=>'view','stafflog'=>'all','orders_download'=>true,'products_download'=>true,'reports_download'=>true,'customers_download'=>true]];
    foreach ($defaults as $rn => $perm) DB::table('roles')->where('name',$rn)->whereRaw("permissions::text = '[]'")->update(['permissions'=>json_encode($perm)]);
    if (!DB::table('stores')->exists()) DB::table('stores')->insert(['name'=>'DutyFreeBD','domain'=>'dutyfreebd.com','created_at'=>now(),'updated_at'=>now()]);
  }
}
