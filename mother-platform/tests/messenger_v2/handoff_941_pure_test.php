<?php
// Command 941 item 4: hand-off words. Run from mother-platform/overlay.
$s = file_get_contents('app/Support/ChatRules.php');
preg_match('/public static function asksForPerson.*?\n  \}\n/s', $s, $a); preg_match('/public static function detect.*?\n  \}\n/s', $s, $d);
eval('class R{'.$a[0].$d[0].'}');
$f=0;$n=0; $chk=function($ok,$l) use(&$f,&$n){$n++; if(!$ok){$f++; echo "FAIL $l\n";}};
foreach (['একজন এজেন্টের সঙ্গে কথা বলতে চাই','I want to talk to a human','agent','human please','manager er sathe kotha bolte chai','কাস্টমার কেয়ারের সাথে কথা বলব','please connect me to a real person','agent dao','আমার ডেলিভারি urgent দরকার','refund চাই','অর্ডার cancel করুন'] as $b) $chk(R::detect($b)!==null, "should hand off: $b");
foreach (['I am a manager at a hotel and I need 5 cartons for my team','my manager told me to order this perfume for the office','travel agent hisebe ami ei product ta kinbo','amar office er manager ke gift dibo, price koto?','manush onek bhalo bole ei brand ta nilam','দাম কত ভাই, urgent না, আগামী সপ্তাহে হলেও চলবে','ডেলিভারি urgent নয় ধীরে হলে সমস্যা নেই'] as $b) $chk(R::detect($b)===null, "must NOT hand off: $b");
echo $f?"FAILS $f of $n\n":"ALL $n OK\n"; exit($f?1:0);
