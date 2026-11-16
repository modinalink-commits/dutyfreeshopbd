{{-- Order page: WhatsApp call recordings linked to this order. Shown only when at least one is linked. --}}
@if(\App\Support\WaRec::ready() && \Illuminate\Support\Facades\DB::table('wa_call_recordings')->where('order_id', (int)$o->id)->exists())
@include('wa_rec_ui')
<div class="card" style="margin-top:10px;padding:0"><b style="display:block;padding:10px 14px 4px;font-size:14px">WhatsApp call recordings of this order</b><div id="wrOrder"></div></div>
<script>WaRecUI.load(document.getElementById('wrOrder'),'order={{ (int)$o->id }}',{});</script>
@endif
