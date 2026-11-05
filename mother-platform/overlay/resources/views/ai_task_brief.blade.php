@extends('layout')@section('title','AI Brief')
@section('body')
<?php use App\Support\AiTaskBrief as B; $J = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT; ?>
<style>
.tb{display:flex;flex-direction:column;gap:10px;font-size:13px;color:#0F2748}
.tb *{box-sizing:border-box}
.tb .top{display:flex;flex-wrap:wrap;gap:10px;align-items:center;background:#fff;border:1px solid #CED4DA;border-radius:12px;padding:10px 14px}
.tb h1{margin:0;font-size:17px;color:#0F2748}.tb .sub{color:#6B7280;font-size:12px}
.tb .meta{margin-left:auto;display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.tb .pill{border:1px solid #CED4DA;background:#F8FAFC;border-radius:999px;padding:3px 10px;font-size:12px;font-weight:600;white-space:nowrap}
.tb .bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.tb .btn{border:1px solid #CED4DA;background:#fff;color:#0F2748;border-radius:8px;padding:7px 12px;font-weight:700;font-size:13px;cursor:pointer}
.tb .btn:hover{background:#F1F5F9}.tb .btn.go{background:#EA580C;border-color:#EA580C;color:#fff}.tb .btn.go:hover{background:#C2410C}.tb .btn:disabled{opacity:.55;cursor:wait}
.tb .sec{border:1px solid #CED4DA;border-radius:12px;background:#fff;overflow:hidden}
.tb .sh{padding:6px 12px;font-weight:800;font-size:12px;letter-spacing:.2px;border-bottom:1px solid #CED4DA;display:flex;gap:8px;align-items:center}
.tb .sb{padding:10px 12px;display:grid;gap:10px}
.tb .g2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.tb .g3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
@media(max-width:900px){.tb .g2,.tb .g3{grid-template-columns:1fr}}
.tb label{display:block;font-weight:700;font-size:12px;margin:0 0 3px}.tb label i{color:#DC2626;font-style:normal}
.tb input[type=text],.tb select,.tb textarea{width:100%;border:1px solid #CED4DA;border-radius:8px;padding:7px 9px;font:inherit;background:#fff;color:#0F2748}
.tb textarea{min-height:70px;resize:vertical}.tb input:focus,.tb select:focus,.tb textarea:focus{outline:2px solid #BFDBFE;border-color:#2563EB}
.tb .hint{color:#6B7280;font-size:11.5px;margin-top:2px}
.tb .chips{display:flex;flex-wrap:wrap;gap:6px}
.tb .chip{border:1px solid #CED4DA;border-radius:999px;padding:3px 10px;font-size:12px;background:#fff;cursor:pointer;user-select:none}
.tb .chip.on{background:#0F2748;border-color:#0F2748;color:#fff}
.tb .tag{display:inline-flex;gap:4px;align-items:center;border:1px solid #CED4DA;border-radius:999px;padding:2px 4px 2px 10px;font-size:12px;background:#fff}.tb .tag b{cursor:pointer;color:#6B7280;padding:0 5px}
.tb .tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.tb .ar{display:grid;grid-template-columns:150px minmax(0,1fr) auto;gap:6px;align-items:center;margin-bottom:6px}
@media(max-width:600px){.tb .ar{grid-template-columns:1fr}}
.tb .fcts{font-size:12px;color:#334155;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:6px 9px;margin:0 0 6px;white-space:pre-wrap;word-break:break-word}
.tb .fcts.er{background:#FEF2F2;border-color:#FECACA;color:#991B1B}
.tb .res{white-space:pre-wrap;word-break:break-word;line-height:1.55;border:1px solid #E2E8F0;border-radius:8px;padding:10px 12px;background:#FFFEFB;max-height:520px;overflow:auto}
.tb .ok{color:#166534}.tb .no{color:#B91C1C}.tb .wn{color:#B45309}
.tb .tbl{width:100%;border-collapse:collapse;font-size:12px}.tb .tbl td,.tb .tbl th{border:1px solid #E2E8F0;padding:5px 8px;text-align:left;vertical-align:top}.tb .tbl th{background:#F1F5F9}
.tb .msg{border-radius:10px;padding:8px 12px;font-size:13px}.tb .msg.er{background:#FEF2F2;border:1px solid #FECACA;color:#991B1B}.tb .msg.ok{background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46}
.tb .modal{position:fixed;inset:0;background:rgba(15,39,72,.45);display:none;align-items:center;justify-content:center;z-index:9999;padding:14px}.tb .modal.on{display:flex}
.tb .mb{background:#fff;border-radius:14px;max-width:900px;width:100%;max-height:88vh;overflow:auto;padding:14px 16px;border:1px solid #CED4DA}
</style>
@if(!$ready)<div class="tb"><div class="msg er">The AI Brief tables are not ready yet. Please wait for the update to finish, then reload.</div></div>@else
<div class="tb" id="tb">
 <div class="top">
  <div><h1>AI Brief</h1><div class="sub">Create a clear instruction for AI before running the task.</div></div>
  <div class="meta">
   <span class="pill" id="cmpl">Brief Completeness: 0%</span>
   <span class="pill" id="stt">Status: Draft</span><span class="pill">Priority: <select id="f_priority" style="width:auto;border:0;padding:0;font-weight:700;background:transparent">@foreach(B::PRIORITY as $x)<option>{{ $x }}</option>@endforeach</select></span>
   <span class="pill">AI Mode: <select id="f_ai_mode" style="width:auto;border:0;padding:0;font-weight:700;background:transparent">@foreach(B::MODES as $x)<option>{{ $x }}</option>@endforeach</select></span>
  </div>
 </div>
 <div class="bar">
  <button class="btn" id="bSave">Save Draft</button><button class="btn" id="bImp">✨ Improve Brief</button><button class="btn" id="bPrev">Preview</button><button class="btn go" id="bRun">▶ Run AI</button>
  <span class="sub">Runs left today: <b id="runsLeft">{{ $runsLeft }}</b></span>
  <span style="margin-left:auto" class="bar">
   <select id="pickBrief" style="width:220px"><option value="">Open a saved brief…</option>@foreach($list as $x)<option value="{{ $x->id }}" {{ $cur && $cur->id == $x->id ? 'selected' : '' }}>#{{ $x->id }} · {{ \Illuminate\Support\Str::limit($x->title, 38) }} ({{ $x->completeness }}%)</option>@endforeach</select>
   <select id="pickTpl" style="width:200px"><option value="">Start from a template…</option>@foreach($tpl as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select>
   <a class="btn" href="/admin/ai-brief-builder" style="text-decoration:none">New</a>
  </span>
 </div>
 @unless($hasKey)<div class="msg er">The Claude API key is not set (Site settings &gt; AI Chat). You can write and save briefs, but Run AI and Improve Brief need the key.</div>@endunless
 <div id="note"></div>

 <div class="sec"><div class="sh" style="background:#EFF6FF">① Title · Type · Objective</div><div class="sb">
  <div class="g2"><div><label>Brief Title <i>*</i></label><input type="text" id="f_title" maxlength="200" placeholder="Create Win-Back SMS Campaign for Inactive Customers"></div>
   <div><label>Brief Type <i>*</i></label><select id="f_type"><option value="">Select…</option>@foreach(B::TYPES as $x)<option>{{ $x }}</option>@endforeach</select></div></div>
  <div><label>What do you want AI to achieve? <i>*</i></label><textarea id="f_objective" maxlength="4000" placeholder="Create a short Bangla win-back SMS for customers who have not ordered for 15 days."></textarea></div>
 </div></div>

 <div class="sec"><div class="sh" style="background:#F3F4F6">② Business context · audience · data</div><div class="sb">
  <div><label>Business / Module Context <i>*</i></label><input type="text" id="f_context" maxlength="2000" placeholder="DutyFreeBD · Customer Reactivation · Win-Back Offers"></div>
  <div class="g2"><div><label>Target Audience (multiple)</label><div class="chips" data-multi="audience">@foreach(B::AUDIENCE as $x)<span class="chip" data-v="{{ $x }}">{{ $x }}</span>@endforeach</div></div>
   <div><label>Use Data From (multiple) <i>*</i></label><div class="chips" data-multi="sources">@foreach(B::SOURCES as $x)<span class="chip" data-v="{{ $x }}">{{ $x }}</span>@endforeach</div>
   <div class="hint">Customer, Order and Product facts are loaded live from the system when you attach them below. Other sources are instructions to the AI about which data is authoritative.</div></div></div>
  <div><label>Customer / Order / Product context (loaded live, never includes cost, profit or fraud data)</label><div id="attach"></div><button class="btn" type="button" id="addAtt">+ Attach</button></div>
  <div><label>Source Priority</label><textarea id="f_source_priority" maxlength="1000" style="min-height:52px" placeholder="1. Current Order Data&#10;2. Customer Profile&#10;3. Courier Live Status&#10;4. Message History"></textarea></div>
 </div></div>

 <div class="sec"><div class="sh" style="background:#FFFBEB">③ Must Know</div><div class="sb"><textarea id="f_must_know" maxlength="4000" style="min-height:92px" placeholder="Facts AI must not miss, one per line:&#10;Customer has placed 11 orders&#10;Current order is in Shipment&#10;Payment is COD&#10;Do not promise exact delivery time"></textarea></div></div>

 <div class="g2">
  <div class="sec"><div class="sh" style="background:#F0FDF4">④ Must Include</div><div class="sb"><input type="text" id="in_include" list="dl_inc" placeholder="Type and press Enter"><div class="tags" id="tg_include"></div>
   <datalist id="dl_inc"><option>Customer Name</option><option>Invoice Number</option><option>Current Status</option><option>Offer Amount</option><option>Tracking Link</option><option>Company Name</option><option>CTA</option></datalist></div></div>
  <div class="sec"><div class="sh" style="background:#FEF2F2">⑤ Must NOT Include</div><div class="sb"><input type="text" id="in_not" list="dl_not" placeholder="Type and press Enter"><div class="tags" id="tg_not"></div>
   <datalist id="dl_not"><option>Internal FIFO Cost</option><option>Fraud Score</option><option>Staff Notes</option><option>Internal Profit</option><option>API Information</option><option>Private Customer Data</option><option>Unverified Claims</option></datalist></div></div>
 </div>

 <div class="sec"><div class="sh" style="background:#F3F4F6">⑥ Business Rules <span class="sub" style="font-weight:500">(loaded from the Brief Type; you may edit)</span></div><div class="sb"><textarea id="f_rules" maxlength="4000" style="min-height:96px"></textarea></div></div>

 <div class="sec"><div class="sh" style="background:#FFF7ED">⑦ Output</div><div class="sb">
  <div class="g3"><div><label>Output Type <i>*</i></label><select id="f_output"><option value="">Select…</option>@foreach(B::OUTPUTS as $x)<option>{{ $x }}</option>@endforeach</select></div>
   <div><label>Output Length</label><select id="f_length">@foreach(B::LENGTHS as $x)<option>{{ $x }}</option>@endforeach</select></div>
   <div><label>Language <i>*</i></label><select id="f_language">@foreach(B::LANGS as $x)<option>{{ $x }}</option>@endforeach</select></div></div>
  <div class="g2"><div><label>Tone (one primary)</label><select id="f_tone">@foreach(B::TONES as $x)<option>{{ $x }}</option>@endforeach</select></div><div></div></div>
 </div></div>

 <div class="sec"><div class="sh" style="background:#F3F4F6">⑧ Example / Reference · Attachments</div><div class="sb">
  <div><label>Example or Reference (reference only; AI will not copy it blindly)</label><textarea id="f_reference" maxlength="6000" style="min-height:70px" placeholder="Paste an example message or template text"></textarea></div>
  <div><label>Attach text files (TXT, CSV, JSON, MD · up to 4 · 12,000 characters each)</label><input type="file" id="fileIn" accept=".txt,.csv,.json,.md,text/plain,text/csv,application/json" multiple><div class="tags" id="tg_files"></div>
   <div class="hint">Images, PDF, DOCX and XLSX attachments are not supported yet in this version; paste the important text or export the sheet as CSV.</div></div>
 </div></div>

 <div class="sec"><div class="sh" style="background:#F5F3FF">⑨ Permissions · confirmation · confidence</div><div class="sb">
  <div class="g3"><div><label>Permission Level <i>*</i></label><select id="f_permission">@foreach(B::PERMS as $x)<option>{{ $x }}</option>@endforeach</select></div>
   <div><label>Minimum Confidence</label><select id="f_confidence">@foreach(B::CONF as $x)<option>{{ $x }}</option>@endforeach</select></div>
   <div><label>If AI cannot confidently complete this task</label><select id="f_escalation">@foreach(B::ESC as $x)<option>{{ $x }}</option>@endforeach</select></div></div>
  <div><label>Action Permissions</label><div class="chips" data-multi="actions">@foreach(B::ACTIONS as $x)<span class="chip" data-v="{{ $x }}">{{ $x }}</span>@endforeach</div></div>
  <div><label>Require Human Confirmation for</label><div class="chips" data-multi="confirm">@foreach(B::CONFIRM as $x)<span class="chip" data-v="{{ $x }}">{{ $x }}</span>@endforeach</div></div>
  <div class="hint">This version of the tool only drafts and suggests. It never sends a message, changes an order, a payment or a price; for action levels the AI writes the proposed steps for a human to carry out.</div>
 </div></div>

 <div class="sec"><div class="sh" style="background:#EFF6FF">⑩ Expected result / Acceptance criteria</div><div class="sb"><textarea id="f_accept" maxlength="3000" style="min-height:80px" placeholder="How will we know the result is correct? One per line:&#10;Message under 160 characters&#10;Mentions the ৳100 offer&#10;Natural Bangla"></textarea></div></div>

 <div class="sec" id="resSec" style="display:none"><div class="sh" style="background:#FFF7ED">AI Result <span id="resBadge" class="pill"></span></div><div class="sb">
  <div id="resMiss"></div><div class="res" id="resTxt"></div><textarea id="resEdit" style="display:none;min-height:160px"></textarea><div id="resChk"></div>
  <div class="bar"><button class="btn" id="rAcc">Accept</button><button class="btn" id="rEdit">Edit</button><button class="btn" id="rRegen">Regenerate</button><button class="btn" id="rCopy">Copy</button><button class="btn" id="rTpl">Save Template</button><span class="sub" id="resMeta"></span></div>
  <div class="hint" id="resHint"></div>
 </div></div>

 <div class="sec"><div class="sh" style="background:#F3F4F6">Version history</div><div class="sb">
  @if($versions->count())<table class="tbl"><tr><th>Version</th><th>By</th><th>When</th><th>Changes</th><th>AI result</th><th></th></tr>
   @foreach($versions as $v)<tr><td>v{{ $v->version }}</td><td>{{ $v->by }}</td><td>{{ \Carbon\Carbon::parse($v->created_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') }}</td><td>{{ \Illuminate\Support\Str::limit($v->changes, 120) }}</td><td>{{ $v->has_result ? 'yes' : '—' }}</td><td><a href="#" class="vload" data-v="{{ $v->version }}">Load</a></td></tr>@endforeach</table>
  @else<div class="sub">Save the brief to start its history (Brief v1, v2, v3 …).</div>@endif
 </div></div>

 <div class="modal" id="modal"><div class="mb"><div id="mbody"></div><div class="bar" style="margin-top:10px" id="mfoot"></div></div></div>
</div>
<script>
(function(){
var CUR={!! json_encode($cur ? (int)$cur->id : 0, $J) !!}, D={!! json_encode($data, $J) !!}, RULES={!! json_encode($typeRules, $J) !!}, RES={!! json_encode($res, $J) !!}, TOKEN=(document.querySelector('meta[name=csrf-token]')||{}).content||'';
var $=function(i){return document.getElementById(i)}, MULTI=['audience','sources','actions','confirm'], S={files:[],include:[],not:[],attach:[]}, lastRules='';
function el(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e}
function note(t,k){var n=$('note');n.innerHTML='';if(!t)return;var d=el('div','msg '+(k||'ok'),t);n.appendChild(d);if(k!=='er')setTimeout(function(){if(n.firstChild===d)n.innerHTML=''},5000)}
function post(u,b){return fetch(u,{method:'POST',credentials:'include',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':TOKEN,'Accept':'application/json'},body:JSON.stringify(b||{})}).then(function(r){return r.json().catch(function(){return {ok:false,error:'Unexpected reply ('+r.status+')'}})})}
function renderMulti(){document.querySelectorAll('[data-multi]').forEach(function(w){var k=w.dataset.multi,sel=D[k]||[];w.querySelectorAll('.chip').forEach(function(c){c.classList.toggle('on',sel.indexOf(c.dataset.v)>-1)})})}
document.querySelectorAll('[data-multi]').forEach(function(w){w.addEventListener('click',function(e){var c=e.target.closest('.chip');if(!c)return;var k=w.dataset.multi,a=D[k]||[],i=a.indexOf(c.dataset.v);if(i>-1)a.splice(i,1);else a.push(c.dataset.v);D[k]=a;renderMulti();upd()})});
function tags(id,key){var w=$(id);w.innerHTML='';(D[key]||[]).forEach(function(t,i){var s=el('span','tag',t),b=el('b','','×');b.onclick=function(){D[key].splice(i,1);tags(id,key);upd()};s.appendChild(b);w.appendChild(s)})}
function bindTag(inp,id,key){$(inp).addEventListener('keydown',function(e){if(e.key!=='Enter')return;e.preventDefault();var v=this.value.trim();if(!v)return;D[key]=D[key]||[];if(D[key].indexOf(v)<0)D[key].push(v);this.value='';tags(id,key);upd()})}
bindTag('in_include','tg_include','must_include');bindTag('in_not','tg_not','must_not');
function files(){var w=$('tg_files');w.innerHTML='';(D.files||[]).forEach(function(f,i){var s=el('span','tag',f.name+' ('+(f.text||'').length+' chars)'),b=el('b','','×');b.onclick=function(){D.files.splice(i,1);files();upd()};s.appendChild(b);w.appendChild(s)})}
$('fileIn').addEventListener('change',function(){var list=[].slice.call(this.files||[]);D.files=D.files||[];list.forEach(function(f){if(D.files.length>=4)return;if(f.size>600000){note(f.name+' is too large (max 600 KB).','er');return}var r=new FileReader();r.onload=function(){D.files.push({name:f.name,text:String(r.result||'').slice(0,12000)});files();upd()};r.readAsText(f)});this.value=''});
function attach(){var w=$('attach');w.innerHTML='';(D.attach||[]).forEach(function(a,i){var row=el('div','ar'),k=el('select'),q=el('input'),b=el('button','btn','Check'),f=el('div','fcts');f.style.display='none';f.style.gridColumn='1/-1';
 [['customer','Customer (phone)'],['order','Order / Invoice'],['product','Product (name / SKU / ID)']].forEach(function(o){var op=el('option','',o[1]);op.value=o[0];if(a.kind===o[0])op.selected=true;k.appendChild(op)});
 q.type='text';q.value=a.q||'';q.placeholder='e.g. 102399';b.type='button';
 k.onchange=function(){a.kind=k.value;upd()};q.oninput=function(){a.q=q.value;upd()};
 b.onclick=function(){f.style.display='block';f.className='fcts';f.textContent='Checking…';fetch('/admin/ai-brief-builder/lookup?kind='+encodeURIComponent(a.kind)+'&q='+encodeURIComponent(a.q||''),{credentials:'include',headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(j){if(j.error){f.className='fcts er';f.textContent=j.error}else{f.textContent=Object.keys(j).map(function(x){var v=j[x];return x+': '+(typeof v==='object'?JSON.stringify(v):v)}).join('\n')}}).catch(function(){f.className='fcts er';f.textContent='Lookup failed'})};
 var rm=el('button','btn','×');rm.type='button';rm.onclick=function(){D.attach.splice(i,1);attach();upd()};
 row.appendChild(k);row.appendChild(q);var bw=el('span');bw.appendChild(b);bw.appendChild(document.createTextNode(' '));bw.appendChild(rm);row.appendChild(bw);w.appendChild(row);w.appendChild(f)})}
$('addAtt').onclick=function(){D.attach=D.attach||[];if(D.attach.length>=6)return;D.attach.push({kind:'order',q:''});attach()};
var SIMPLE=['title','type','priority','ai_mode','objective','context','source_priority','must_know','rules','output','length','language','tone','reference','permission','accept','confidence','escalation'];
function fill(){SIMPLE.forEach(function(k){var e=$('f_'+k);if(e)e.value=D[k]==null?'':D[k]});renderMulti();tags('tg_include','must_include');tags('tg_not','must_not');files();attach();upd()}
function collect(){SIMPLE.forEach(function(k){var e=$('f_'+k);if(e)D[k]=e.value});return D}
function calc(){var d=collect(),req=[d.title,d.type,d.objective,d.context,((d.sources||[]).length||(d.attach||[]).length||(d.files||[]).length||d.reference)?1:'',d.output,d.language,d.permission],rec=[d.must_know,(d.must_include||[]).length,(d.must_not||[]).length,d.accept],p=0,miss=0;req.forEach(function(x){if(x)p+=10;else miss++});rec.forEach(function(x){if(x)p+=5});return {p:p,miss:miss}}
function upd(){var c=calc(),s=c.miss?'🔴 Missing Required Fields':(c.p>=85?'🟢 Ready':'🟡 Needs Information');$('cmpl').textContent='Brief Completeness: '+c.p+'%';$('stt').textContent=(RES&&RES.status==='ok'?'Status: Ran · ':'Status: ')+s}
document.querySelectorAll('#tb input[id^=f_],#tb select[id^=f_],#tb textarea[id^=f_]').forEach(function(e){e.addEventListener('input',upd);e.addEventListener('change',upd)});
$('f_type').addEventListener('change',function(){var cur=$('f_rules').value.trim(),old=Object.keys(RULES).map(function(k){return RULES[k].trim()});if(!cur||old.indexOf(cur)>-1)$('f_rules').value=RULES[this.value]||'';upd()});
function busy(b,on,t){b.disabled=on;if(t)b.dataset.t=b.dataset.t||b.textContent;b.textContent=on?(t||'Working…'):(b.dataset.t||b.textContent)}
function save(quiet){return post('/admin/ai-brief-builder/save',{id:CUR,data:collect()}).then(function(j){if(j.ok){var first=!CUR;CUR=j.id;if(first)history.replaceState(null,'','/admin/ai-brief-builder?id='+CUR);if(!quiet)note(j.changed&&j.changed.length?'Saved as v'+j.version+'.':'Saved (no change since v'+j.version+').')}else note(j.error||'Could not save','er');return j})}
$('bSave').onclick=function(){var b=this;busy(b,true,'Saving…');save(false).then(function(){busy(b,false)})};
function modal(html,foot){$('mbody').innerHTML='';if(typeof html==='string')$('mbody').innerHTML=html;else $('mbody').appendChild(html);$('mfoot').innerHTML='';(foot||[]).forEach(function(f){$('mfoot').appendChild(f)});$('modal').classList.add('on')}
function close(){$('modal').classList.remove('on')}$('modal').addEventListener('click',function(e){if(e.target===this)close()});
function btn(t,c,f){var b=el('button','btn '+(c||''),t);b.type='button';b.onclick=f;return b}
$('bPrev').onclick=function(){var b=this;busy(b,true,'Loading…');post('/admin/ai-brief-builder/preview',{data:collect()}).then(function(j){busy(b,false);if(!j.ok){note(j.error||'Preview failed','er');return}
 var w=el('div'),h=el('h3','','FINAL AI BRIEF');h.style.margin='0 0 8px';w.appendChild(h);var q=el('div','fcts');q.textContent=j.check.quality.map(function(x){return (x[1]?'✔ ':'✘ ')+x[0]}).join('\n');w.appendChild(q);
 if(j.check.missing.length){var m=el('div','msg er','Required fields missing: '+j.check.missing.join(', '));m.style.marginBottom='8px';w.appendChild(m)}
 if(j.lookup_errors&&j.lookup_errors.length){var m2=el('div','msg er','Attached item problem: '+j.lookup_errors.join('; '));m2.style.marginBottom='8px';w.appendChild(m2)}
 var pre=el('div','res',j.text);w.appendChild(pre);modal(w,[btn('Close','',close),btn('▶ Run AI','go',function(){close();$('bRun').click()})])}).catch(function(){busy(b,false);note('Preview failed','er')})};
$('bImp').onclick=function(){var b=this;busy(b,true,'Improving…');post('/admin/ai-brief-builder/improve',{data:collect()}).then(function(j){busy(b,false);if(!j.ok){note(j.error||'Improve failed','er');return}
 var keys=Object.keys(j.changes||{});if(!keys.length){note('The brief already looks clear; no changes suggested.');return}
 var w=el('div'),h=el('h3','','Suggested changes (nothing is applied until you tick and apply)');h.style.margin='0 0 8px';w.appendChild(h);if(j.note)w.appendChild(el('div','sub',j.note));
 var t=el('table','tbl'),tr=el('tr');['Apply','Field','Before','After'].forEach(function(x){tr.appendChild(el('th','',x))});t.appendChild(tr);var cbs={};
 keys.forEach(function(k){var r=el('tr'),td=el('td'),cb=el('input');cb.type='checkbox';cb.checked=true;cbs[k]=cb;td.appendChild(cb);r.appendChild(td);r.appendChild(el('td','',k));var f=function(v){return Array.isArray(v)?v.join(', '):String(v||'—')};r.appendChild(el('td','',f(j.changes[k].old)));r.appendChild(el('td','',f(j.changes[k].new)));t.appendChild(r)});w.appendChild(t);
 modal(w,[btn('Cancel','',close),btn('Apply selected','go',function(){keys.forEach(function(k){if(cbs[k].checked)D[k]=j.changes[k].new});fill();close();note('Applied. Review the fields and save.')})])}).catch(function(){busy(b,false);note('Improve failed','er')})};
function showRes(r){RES=r;var s=$('resSec');if(!r||!r.text){s.style.display='none';return}s.style.display='block';var m=r.meta||{};$('resTxt').textContent=r.text;$('resTxt').style.display='block';$('resEdit').style.display='none';
 var bd=$('resBadge');bd.textContent=r.accepted?'✅ Accepted':(r.status==='ok'?'🟢 OK':'🟠 Needs Review');
 var ms=$('resMiss');ms.innerHTML='';if(r.status!=='ok'&&(m.missing||[]).length){var d=el('div','msg er','Needs Review — missing or uncertain: '+m.missing.join('; '));d.style.marginBottom='8px';ms.appendChild(d)}
 var ck=$('resChk');ck.innerHTML='';if((m.checks||[]).length){var t=el('div','fcts');t.textContent=m.checks.map(function(c){return (c.met?'✔ ':'✘ ')+c.criterion}).join('\n');ck.appendChild(t)}
 $('resMeta').textContent=(m.model||'')+(m.in?' · '+m.in+' in / '+m.out+' out tokens':'')+(m.by?' · '+m.by:'')+(m.version?' · v'+m.version:'');
 var pm=D.permission;$('resHint').textContent=(pm==='Action with Confirmation'||pm==='Authorized Action')?'Nothing was executed. This version only prepares the steps; a person must carry them out and confirm every sensitive action.':'';upd();s.scrollIntoView({behavior:'smooth',block:'start'})}
function run(b){var c=calc();if(c.miss){note('Required fields are missing. Use Preview to see what to fill in.','er');return}busy(b,true,'Running AI…');post('/admin/ai-brief-builder/run',{id:CUR,data:collect()}).then(function(j){busy(b,false);if(j.id){CUR=j.id;history.replaceState(null,'','/admin/ai-brief-builder?id='+CUR)}if(!j.ok){note(j.error||'Run failed','er');return}$('runsLeft').textContent=Math.max(0,(+$('runsLeft').textContent||1)-1);showRes(j.result);note('Result is ready below. The brief was saved as v'+j.version+'.')}).catch(function(){busy(b,false);note('The request failed. Try again.','er')})}
$('bRun').onclick=function(){run(this)};$('rRegen').onclick=function(){run(this)};
$('rAcc').onclick=function(){if(!CUR)return;post('/admin/ai-brief-builder/result/'+CUR,{mode:'accept'}).then(function(j){if(j.ok){RES.accepted=true;showRes(RES);note('Accepted.')}})};
$('rEdit').onclick=function(){var t=$('resTxt'),e=$('resEdit');if(e.style.display==='none'){e.value=RES.text;e.style.display='block';t.style.display='none';this.textContent='Save edit'}else{var b=this;post('/admin/ai-brief-builder/result/'+CUR,{mode:'edit',text:e.value}).then(function(j){if(j.ok){RES.text=e.value;RES.accepted=false;b.textContent='Edit';showRes(RES);note('Edited text saved.')}else note(j.error||'Could not save','er')})}};
$('rCopy').onclick=function(){var t=RES&&RES.text||'';if(navigator.clipboard)navigator.clipboard.writeText(t).then(function(){note('Copied.')},function(){note('Copy failed','er')});else{var a=document.createElement('textarea');a.value=t;document.body.appendChild(a);a.select();try{document.execCommand('copy');note('Copied.')}catch(e){note('Copy failed','er')}a.remove()}};
$('rTpl').onclick=function(){tpl()};
function tpl(){var i=el('input');i.type='text';i.placeholder='Template name, e.g. Win-Back SMS';i.value=$('f_title').value;var w=el('div');w.appendChild(el('h3','','Save as Template'));w.appendChild(i);
 modal(w,[btn('Cancel','',close),btn('Save Template','go',function(){post('/admin/ai-brief-builder/template',{name:i.value,data:collect()}).then(function(j){close();if(j.ok){note('Template saved. It appears in "Start from a template".')}else note(j.error||'Could not save','er')})})])}
$('pickBrief').onchange=function(){if(this.value)location.href='/admin/ai-brief-builder?id='+this.value};
$('pickTpl').onchange=function(){if(this.value)location.href='/admin/ai-brief-builder?tpl='+this.value};
document.querySelectorAll('.vload').forEach(function(a){a.onclick=function(e){e.preventDefault();fetch('/admin/ai-brief-builder/'+CUR+'/version/'+a.dataset.v,{credentials:'include',headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(j){if(j.ok){D=j.data;fill();note('Version v'+j.version+' loaded into the form. Save to make it the newest version.')}})}});
fill();if(RES&&RES.text)showRes(RES);
})();
</script>
@endif
@endsection
