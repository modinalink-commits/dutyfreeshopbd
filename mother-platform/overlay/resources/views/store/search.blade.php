@extends('store.layout')
@section('main')<h1>{{ $t ? '"'.$t.'" এর ফলাফল' : 'পণ্য খুঁজুন' }}</h1>
@if($t)<div class="g" style="margin-top:12px">@forelse($items as $q)@include('store.card')@empty<p>কোনো পণ্য পাওয়া যায়নি। অন্য নামে খুঁজুন বা WhatsApp করুন।</p>@endforelse</div>
@if($items->hasPages())<div class="pg">@if($items->previousPageUrl())<a href="{{ $items->previousPageUrl() }}">‹ আগের</a>@endif<span>{{ $items->currentPage() }} / {{ $items->lastPage() }}</span>@if($items->nextPageUrl())<a href="{{ $items->nextPageUrl() }}">পরের ›</a>@endif</div>@endif @endif
@endsection
