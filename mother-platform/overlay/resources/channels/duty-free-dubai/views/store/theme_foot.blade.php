{{-- Duty Free Dubai storefront theme hook (loaded last by store.layout, only on this channel). Brand look only: contact, company, payment and policy text stay on the DutyFreeBD setup. --}}
<link rel="stylesheet" href="{{ \App\Support\ChannelScope::asset('css/theme.css') }}">
<script>(function(){try{var b=document.body;b.classList.add('dfd');var L=@json(\App\Support\ChannelScope::asset('logo/header-light.svg')),F=@json(\App\Support\ChannelScope::asset('logo/footer-light.svg'));
function sw(sel,u){document.querySelectorAll(sel).forEach(function(i){i.src=u;i.removeAttribute('srcset');i.alt='Duty Free Dubai';i.removeAttribute('width');i.removeAttribute('height')})}
sw('.hdw .lg img',L);sw('.fn .lb img,.fg .cols>div:first-child>img',F);
document.querySelectorAll('link[rel~="icon"],link[rel="apple-touch-icon"]').forEach(function(l){l.parentNode.removeChild(l)});
[['icon','image/svg+xml',@json(\App\Support\ChannelScope::asset('logo/favicon.svg'))],['icon','image/x-icon',@json(\App\Support\ChannelScope::asset('logo/favicon.ico'))],['apple-touch-icon','',@json(\App\Support\ChannelScope::asset('logo/apple-touch-icon.png'))]].forEach(function(a){var l=document.createElement('link');l.rel=a[0];if(a[1])l.type=a[1];l.href=a[2];document.head.appendChild(l)});
var m=document.querySelector('meta[name="theme-color"]');if(m)m.content='#232323'}catch(e){}})();</script>
