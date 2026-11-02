<b class="h" style="display:block">Contact Us</b>
<div style="font-size:13px;line-height:1.75;margin-top:6px">
<span style="display:block"><b style="font-weight:700">Hotline:</b> <a href="{{ \App\Support\Biz::telUrl(\App\Support\Biz::hotline()) }}" style="display:inline">{{ \App\Support\Biz::hotline() }}</a></span>
<span style="display:block"><b style="font-weight:700">Mobile:</b> <a href="{{ \App\Support\Biz::telUrl(\App\Support\Biz::mobile()) }}" style="display:inline">{{ \App\Support\Biz::mobile() }}</a></span>
<span style="display:block"><b style="font-weight:700">WhatsApp:</b> <a href="{{ \App\Support\Biz::waUrl() }}" rel="noopener" style="display:inline">{{ \App\Support\Biz::whatsapp() }}</a></span>
<span style="display:block"><b style="font-weight:700">Email:</b> <a href="mailto:{{ \App\Support\Biz::email() }}" style="display:inline">{{ \App\Support\Biz::email() }}</a></span>
<span style="display:block"><b style="font-weight:700">Address:</b> {{ \App\Support\Biz::address() }}</span>
<span style="display:block"><b style="font-weight:700">Business Hours:</b> {{ \App\Support\Biz::hours() }}</span>
</div>
