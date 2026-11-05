@if(isset($trips) && $trips->count() > 1)
<div class="tripbar noprint"><span class="sub" style="margin:0">Trips on {{ $trip->trip_date->format('d-m-Y') }}:</span>
  @foreach($trips as $t)
    <a class="pill" href="{{ route($routeName, ['trip' => $t->id]) }}" style="text-decoration:none;{{ $t->id === $trip->id ? 'background:#123a86;color:#fff' : '' }}">{{ $t->vehicle->code }} · {{ $t->trip_no }}</a>
  @endforeach
</div>
@endif
