@extends('app.layout', ['tab' => 'home'])
@section('title', 'দোকানের তালিকা')
@php($U = \App\Support\Ui::class)
@section('bar')@include('app._back', ['href' => $pick === 'view' ? route('app.home') : route('app.'.$pick), 'title' => $pick === 'view' ? 'দোকানের তালিকা' : 'দোকান বাছাই'])@endsection
@section('content')
<form method="get" style="display:flex;gap:8px"><input type="hidden" name="pick" value="{{ $pick }}"><input class="txt" name="q" value="{{ $q }}" placeholder="নাম বা মোবাইল নম্বর লিখুন"><button class="chip" type="submit" style="min-height:48px">খুঁজুন</button></form>
@if(! $q && $trip?->route)<span class="lb">রুট {{ $trip->route->name }}-এর দোকান</span>@endif
@forelse($shops as $c)
  @php($due = $c->due())
  <a href="{{ $pick === 'view' ? 'tel:'.$c->phone : route('app.'.$pick, ['customer' => $c->id]) }}" class="card" style="display:flex;gap:10px;align-items:center;text-decoration:none;color:inherit">
    <div style="flex-grow:1;display:flex;flex-direction:column"><b style="font-size:var(--fs-15)">{{ $c->name }}</b><span class="n" style="font-size:var(--fs-12);color:#51607a">{{ $c->phone }}{{ $c->area ? ' · '.$c->area : '' }}</span></div>
    @if($due > 0)<span class="pill" style="background:#fff1d6;color:#8a4b00">বকেয়া {{ $U::tk($due) }}</span>@endif
  </a>
@empty
  <p style="color:#51607a;margin:0">কোনো দোকান পাওয়া যায়নি। নতুন দোকান অফিস থেকে যোগ করতে হবে।</p>
@endforelse
@endsection
