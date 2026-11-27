// node snapv.js <variant> [w] [h]  -> v-<variant>.png   (states of the real chat panel)
const {chromium}=require('playwright');const fs=require('fs');const R=require('./rich.js');
const v=process.argv[2]||'base',w=+(process.argv[3]||1500),h=+(process.argv[4]||900);
const th=JSON.parse(JSON.stringify(R.th)),detail=JSON.parse(JSON.stringify(R.detail));detail.thread=th;
detail.owner={on:true,id:1,name:'Rafi',mine:true,supervisor:true};th.assigned_to=1;th.assigned_name='Rafi';
if(v==='closed'){th.status='resolved';th.awaiting=false;th.needs_human=false;th.handoff_at=null}
if(v==='fb'){th.channel='facebook';th.channel_name='Facebook';th.phone=null;th.source='DutyFreeBD Page';detail.customer=null;detail.orders=[];detail.intel=null;th.orders=0;th.needs_human=false;th.handoff_at=null}
if(v==='follow'){th.follow_at=R.iso(90);th.follow_note='Call back about payment';th.needs_human=false;th.handoff_at=null}
const W='<div class="mcp" id="mcp"><div class="mcs" id="mcs"><span class="sp"></span></div></div><div class="mcd" id="mcd"><button class="cls" type="button" id="mcx">×</button><div id="mcdb" style="height:100%"></div></div>';
const html=fs.readFileSync('page.html','utf8').replace('<script>window.MCSRF',()=>W+'<script>window.MCSRF').replace('__WJS__',()=>'window.MSla={chip:function(t){return t&&t.awaiting?"<span class=\\"msla r\\">Waiting: 20m 00s</span>":""}};'+fs.readFileSync('wjs.js','utf8'));
(async()=>{const b=await chromium.launch();const p=await b.newPage({viewport:{width:w,height:h}});const errs=[];p.on('pageerror',e=>errs.push(e.message));
await p.route('**/admin/**',r=>{const u=r.request().url();const J=o=>r.fulfill({contentType:'application/json',body:JSON.stringify(o)});
 if(u.includes('/chat/threads'))return J({follow:0,follow_due:0,ai_now:3,mine:1,queue:0,me_id:1,threads:[th],unread:1,order_requests:1,human:1,reports:0,ai_handled:0,svc:null,share:null});
 if(/\/chat\/thread\/5$/.test(u))return J(detail);
 if(u.includes('draft'))return J({ok:true,drafts:['জি স্যার, অর্ডারটি কনফার্ম করা হয়েছে।','Sir, your order is confirmed.','Order ta confirm hoyeche sir.']});
 if(u.includes('product'))return J({ok:true,items:[{id:1,name:'Sample Product 100g',sku:'SP-100',price:450,stock:12}],products:[{id:1,name:'Sample Product 100g',sku:'SP-100',price:450,stock:12}]});
 return J({ok:true,messages:[],staff:[],unread:0})});
await p.goto('http://stg.test/admin/chat?f=all').catch(()=>{});await p.setContent(html,{waitUntil:'domcontentloaded'});await p.waitForTimeout(800);await p.click('#ibl .th[data-id="5"]');await p.waitForTimeout(1200);
try{
if(v==='note')await p.click('#mf .axt button[data-md="note"]');
if(v==='drafts'){await p.click('#mad');await p.waitForTimeout(600)}
if(v==='picker'){await p.click('#mpb');await p.waitForTimeout(600)}
if(v==='sum'){await p.click('#mh .sumb');await p.waitForTimeout(200)}
if(v==='voice')await p.evaluate(()=>{const b=document.getElementById('mvr');b.hidden=false;b.innerHTML='<span class="rd"></span><b>Recording 0:07</b><button class="mini">Cancel</button><button class="mini pr">Stop</button>'});
}catch(e){errs.push('act:'+e.message.slice(0,120))}
const m=await p.evaluate(()=>{const q=s=>document.querySelector(s),r=e=>e?Math.round(e.getBoundingClientRect().height):null;const vis=s=>[...document.querySelectorAll(s)].filter(e=>e.offsetParent!==null).map(e=>e.id||e.className);return {scroll:document.documentElement.scrollHeight>innerHeight+2,head:r(q('#mh')),msgs:r(q('#mm')),foot:r(q('#mf')),visAI:vis('#mad,#mfx,#msh,#msn,#mdu,#msu,#hdt,#mtl')}});
await p.screenshot({path:'v-'+v+'.png'});console.log(v,JSON.stringify(m),'errors',errs);await b.close()})();
