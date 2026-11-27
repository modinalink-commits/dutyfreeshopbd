<?php
// Pure-function tests for command 941 (items 1-2). Run from mother-platform/overlay: php ../tests/messenger_v2/gaps_941_pure_test.php
$chat = file_get_contents('app/Support/Chat.php'); $nudge = file_get_contents('app/Support/AiNudge.php');
preg_match('/public static function asksAnythingElse\(string \$b\): bool \{.*?\n  \}\n/s', $chat, $m1);
preg_match('/public static function isRefusal\(string \$b\): bool \{.*?\n  \}\n/s', $nudge, $m2);
eval('class C{'.$m1[0].'} class N{'.$m2[0].'}');
$f = 0; $n = 0;
$chk = function($ok, $label) use (&$f, &$n) { $n++; if (!$ok) { $f++; echo "FAIL $label\n"; } };
foreach (['Na','না','লাগবে না','না, ধন্যবাদ','no thanks','dorkar nai bhai','না থাক'] as $b) $chk(N::isRefusal($b), "refusal: $b");
foreach (['না, অন্য রং আছে?','na, onno color ache?','ok','দাম কত','লাগবে না, কত দাম ছিল',''] as $b) $chk(!N::isRefusal($b), "not refusal: $b");
foreach (['স্যার, আপনার আর কোনো প্রশ্ন আছে? না থাকলে আমি এখন বিদায় নিচ্ছি।','আর কিছু জানার আছে?','Is there anything else I can help with?','আপনার কি আর কিছু জানার বা সহায়তার প্রয়োজন আছে?'] as $b) $chk(C::asksAnythingElse($b), "closing question: $b");
foreach (['স্যার, আর কিছু জানার আছে? চাইলে এখনই অর্ডার করে দিই।','Sir, anything else you would like to know? I can place the order right away.','স্যার, কী সিদ্ধান্ত নিলেন? অর্ডারটি করে দেব?','দাম ১,৭৮৩ টাকা'] as $b) $chk(!C::asksAnythingElse($b), "not closing question: $b");
echo $f ? "FAILS $f of $n\n" : "ALL $n OK\n"; exit($f ? 1 : 0);
