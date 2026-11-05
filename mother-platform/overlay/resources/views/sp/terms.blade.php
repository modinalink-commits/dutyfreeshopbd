@extends('sp.layout')@section('title','Portal terms')
@section('body')<h1>Supplier Portal Terms</h1><p class="sub">Version {{ \App\Support\SupplierPortal::TERMS }}</p>
<form class="card" method="post" action="/supplier-portal/terms" style="max-width:640px">@csrf
<ul style="padding-left:18px;line-height:1.7"><li>You will see only your own orders, price requests, bills, ledger and payments.</li><li>Prices you submit are confidential and are never shared with other suppliers.</li><li>Keep your password and OTP private. Actions taken from your login are recorded.</li><li>Bank detail changes are verified by DutyFreeBD before they become active.</li></ul>
<label style="display:flex;gap:8px;align-items:center;color:var(--n);font-size:13px"><input type="checkbox" name="agree" value="1" style="width:auto;min-height:0"> I Agree</label><button style="margin-top:12px">Continue</button></form>@endsection
