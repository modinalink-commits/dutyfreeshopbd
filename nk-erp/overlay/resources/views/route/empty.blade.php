@extends('layout')
@section('title', $title)
@section('content')
<header><h1>{{ $title }}</h1><p class="sub">{{ $msg }}</p></header>
<section class="card pad" style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn pri" href="{{ route('trips.loading') }}">＋ Morning loading</a><a class="btn" href="{{ route('fleet.dash') }}">Route dashboard</a></section>
@endsection
