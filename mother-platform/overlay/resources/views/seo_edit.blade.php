@extends('layout')@section('title',$pg->id ? 'Edit page' : 'New page')
@section('body')
@php $dh=fn($x)=>$x ? \Carbon\Carbon::parse($x,'UTC')->timezone('Asia/Dhaka')->format('Y-m-d\TH:i') : ''; @endphp
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">{{ $pg->id ? 'Edit page' : 'New page' }}</h1><div style="font-size:12px"><a href="/admin/seo">← Pages &amp; SEO</a></div></div>
@if($pg->id)<span style="font-size:12px;font-weight:700">SEO score: {{ $sc[0] }} · {{ $sc[1] }}</span>
<a class="g" style="text-decoration:none" target="_blank" href="/{{ $pg->slug }}/?preview={{ $prev }}">Preview</a>
<form method="post" action="/admin/seo/page/{{ $pg->id }}/duplicate">@csrf<button class="g">Duplicate</button></form>
<form method="post" action="/admin/seo/page/{{ $pg->id }}/delete" onsubmit="return confirm('Delete this page? The old URL will redirect to the home page.')">@csrf<button class="g">Delete</button></form>@endif</div>
@if(session('ok'))<div class="card" style="color:#166534">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="card" style="color:#B91C1C">{{ $errors->first() }}</div>@endif
@if($pg->id && $sc[2])<div class="card" style="font-size:12.5px"><b>Suggestions:</b> {{ implode(' · ', $sc[2]) }}</div>@endif
<form method="post" action="/admin/seo/page{{ $pg->id ? '/'.$pg->id : '' }}">@csrf
<div class="card"><h2>Content</h2>
<label>Title</label><input name="title" id="t" value="{{ old('title',$pg->title) }}" required>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px"><div><label>URL slug</label><input name="slug" id="sl" value="{{ old('slug',$pg->slug) }}" placeholder="auto from title"></div>
<div><label>Page type</label><select name="page_type">@foreach(\App\Support\SeoMgr::TYPES as $k=>$l)<option value="{{ $k }}" @selected(old('page_type',$pg->page_type)===$k)>{{ $l }}</option>@endforeach</select></div>
<div><label>Status</label><select name="status">@foreach(\App\Support\SeoMgr::STATUSES as $k=>$l)<option value="{{ $k }}" @selected(old('status',$pg->status)===$k)>{{ $l }}</option>@endforeach</select></div>
<div><label>Publish at (Dhaka time, for Scheduled)</label><input type="datetime-local" name="publish_at" value="{{ $dh($pg->publish_at ?? null) }}"></div>
<div><label>Unpublish at</label><input type="datetime-local" name="unpublish_at" value="{{ $dh($pg->unpublish_at ?? null) }}"></div></div>
<label>Short description</label><textarea name="short_desc" rows="2">{{ old('short_desc',$pg->short_desc ?? '') }}</textarea>
<label>Featured image URL</label><input name="featured_image" value="{{ old('featured_image',$pg->featured_image ?? '') }}">
<label>Content (HTML)</label>
<div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:4px">@foreach([['H2','<h2>','</h2>'],['H3','<h3>','</h3>'],['B','<b>','</b>'],['I','<i>','</i>'],['List','<ul><li>','</li></ul>'],['Link','<a href=&quot;&quot;>','</a>']] as [$l,$a,$b])<button type="button" class="g" onclick="wrap('{{ $a }}','{{ $b }}')">{{ $l }}</button>@endforeach</div>
<textarea name="body" id="bd" rows="16" style="font-family:monospace">{{ old('body',$pg->body ?? '') }}</textarea></div>
<div class="card"><h2>Search appearance</h2>
<div id="gp" style="border:1px solid #E5E7EB;border-radius:8px;padding:10px 12px;margin-bottom:10px;background:#fff;max-width:640px"><div id="gu" style="font-size:12px;color:#188038"></div><div id="gt" style="font-size:18px;color:#1a0dab;line-height:1.25"></div><div id="gd" style="font-size:13px;color:#4d5156"></div></div>
<label>SEO title <small id="ct" style="color:#6B7280"></small></label><input name="meta_title" id="mt" value="{{ old('meta_title',$pg->meta_title ?? '') }}">
<label>Meta description <small id="cd" style="color:#6B7280"></small></label><textarea name="meta_description" id="md" rows="2">{{ old('meta_description',$pg->meta_description ?? '') }}</textarea>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px"><div><label>Focus keyword</label><input name="focus_keyword" value="{{ old('focus_keyword',$pg->focus_keyword ?? '') }}"></div><div><label>Keywords (comma separated)</label><input name="keywords" value="{{ old('keywords',$pg->keywords ?? '') }}"></div><div><label>Canonical URL (optional)</label><input name="canonical" value="{{ old('canonical',$pg->canonical ?? '') }}" placeholder="https://…"></div></div>
<div style="display:flex;gap:18px;margin-top:8px;font-size:13px"><label><input type="checkbox" name="robots_index" value="1" @checked(old('robots_index',$pg->robots_index ?? 1))> Allow search engines to index</label><label><input type="checkbox" name="robots_follow" value="1" @checked(old('robots_follow',$pg->robots_follow ?? 1))> Follow links</label></div>
<p style="font-size:12px;color:#6B7280;margin:6px 0 0">Empty SEO title / description are generated automatically on save.</p></div>
<div class="card"><h2>Social sharing (Open Graph / Twitter)</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px"><div><label>OG title</label><input name="og_title" value="{{ old('og_title',$pg->og_title ?? '') }}"></div><div><label>OG image URL</label><input name="og_image" value="{{ old('og_image',$pg->og_image ?? '') }}"></div><div><label>Twitter image URL</label><input name="twitter_image" value="{{ old('twitter_image',$pg->twitter_image ?? '') }}"></div></div>
<label>OG description</label><textarea name="og_description" rows="2">{{ old('og_description',$pg->og_description ?? '') }}</textarea></div>
<button>Save page</button></form>
@if($rev->count())<div class="card" style="margin-top:12px"><h2>Revisions</h2><table><tbody>@foreach($rev as $v)<tr><td>{{ \Carbon\Carbon::parse($v->created_at,'UTC')->timezone('Asia/Dhaka')->format('d M Y h:i A') }}</td><td>{{ $v->title }}</td><td>{{ $v->edited_by }}</td><td><form method="post" action="/admin/seo/page/{{ $pg->id }}/restore/{{ $v->id }}" onsubmit="return confirm('Restore this revision?')">@csrf<button class="g">Restore</button></form></td></tr>@endforeach</tbody></table></div>@endif
<script>
function wrap(a,b){var t=document.getElementById('bd'),s=t.selectionStart,e=t.selectionEnd;a=a.replace(/&quot;/g,'"');t.value=t.value.slice(0,s)+a+t.value.slice(s,e)+b+t.value.slice(e);t.focus();pv()}
var site=@json($site);
function pv(){var t=document.getElementById('t').value,mt=document.getElementById('mt').value||(t+' | '+site),md=document.getElementById('md').value||document.getElementById('bd').value.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim().slice(0,155),sl=document.getElementById('sl').value||t.toLowerCase().replace(/[^a-z0-9]+/g,'-');
document.getElementById('gu').textContent=location.host+' › '+sl;document.getElementById('gt').textContent=mt.slice(0,62);document.getElementById('gd').textContent=md.slice(0,160);
document.getElementById('ct').textContent='('+mt.length+'/60)';document.getElementById('cd').textContent='('+document.getElementById('md').value.length+'/160)'}
['t','sl','mt','md','bd'].forEach(function(i){document.getElementById(i).addEventListener('input',pv)});pv();
</script>
@endsection
