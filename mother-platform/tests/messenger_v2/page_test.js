const {chromium}=require('playwright');const fs=require('fs');
const stub='window.MChat={mounts:[],mount:function(el,id){this.mounts.push(id);el.innerHTML="<div id=mounted>chat "+id+"</div>"},badge:function(){},cycleSound:function(){},soundLabel:function(){return "Sound on"},refresh:function(){return Promise.resolve()},ico:{}};window.MSla={chip:function(){return ""}};';
const html=fs.readFileSync('page.html','utf8').replace('__WJS__',stub);
const now=Date.now(),iso=m=>new Date(now+m*60000).toISOString();
const base={channel:'facebook',channel_name:'Facebook',source:'DutyFreeBD',phone:'017',avatar:null,unread:1,replied:false,stale:false,last_dir:'in',ai:true,assigned_to:null,assigned_name:null,orders:2,intent:null,mood:null,needs_human:false,reported:false,report_solved:false,sla_w:5,sla_r:15,handoff_at:null,person:false,follow_at:null,follow_note:null,follow_due:false,waiting_since:null};
const T=(o)=>Object.assign({},base,o);
const open=[
 T({id:1,name:'A New',status:'new',awaiting:true,person:true,waiting_since:iso(-3),last:'order chai',last_at:iso(-3)}),
 T({id:2,name:'B AI',status:'open',awaiting:true,person:false,waiting_since:iso(-4),last:'ok',last_at:iso(-4),channel:'whatsapp'}),
 T({id:3,name:'C Work',status:'open',awaiting:true,person:true,assigned_to:2,assigned_name:'Tania',waiting_since:iso(-30),last:'refund?',last_at:iso(-30),reported:true,mood:'upset'}),
 T({id:4,name:'D Wait',status:'waiting',awaiting:false,unread:0,replied:true,assigned_to:1,assigned_name:'Rafi',last:'sent',last_dir:'out',last_at:iso(-50)}),
 T({id:5,name:'E FollowDue',status:'waiting',awaiting:false,unread:0,assigned_to:1,assigned_name:'Rafi',follow_at:iso(-35),follow_due:true,follow_note:'stock',last:'x',last_at:iso(-200)}),
 T({id:6,name:'F FollowLater',status:'waiting',awaiting:false,unread:0,assigned_to:2,assigned_name:'Tania',follow_at:iso(90),last:'y',last_at:iso(-100)}),
 T({id:8,name:'H Taken no reply',status:'open',awaiting:false,unread:0,assigned_to:1,assigned_name:'Rafi',last:'z',last_at:iso(-5)}),
 T({id:9,name:'I Idle AI',status:'open',awaiting:false,unread:0,last:'hi',last_dir:'out',last_at:iso(-9),channel:'web'}),
 T({id:10,name:'J Instagram idle',status:'open',awaiting:false,unread:0,last:'hi',last_at:iso(-9),channel:'instagram'})];
const done=[T({id:7,name:'G Done',status:'resolved',awaiting:false,unread:0,last:'thanks',last_at:iso(-300)})];
const A=(c,m)=>console.log((c?'PASS ':'FAIL ')+m);
(async()=>{const b=await chromium.launch();
for(const vp of [{width:1366,height:768},{width:400,height:820}]){const tg=vp.width<760?'[m] ':'[d] ';
 const p=await b.newPage({viewport:vp});const errs=[];p.on('pageerror',e=>errs.push(e.message));const calls=[];
 await p.route('**/admin/chat/threads**',r=>{const u=new URL(r.request().url());const f=u.searchParams.get('f');calls.push(f);let th=f==='resolved'?done:f==='followup'?open.filter(t=>t.follow_at):f==='assigned'?open.filter(t=>t.assigned_to===1):open;
  r.fulfill({contentType:'application/json',body:JSON.stringify({follow:2,follow_due:1,ai_now:2,mine:3,queue:1,me_id:1,threads:th,unread:3,order_requests:0,human:2,reports:1,ai_handled:1,svc:null,share:null})})});
 await p.route('**/admin/**',r=>{if(r.request().url().includes('/chat/threads'))return r.fallback();r.fulfill({contentType:'application/json',body:'{}'})});
 await p.goto('http://stg.test/admin/chat').catch(()=>{});await p.setContent(html,{waitUntil:'domcontentloaded'});await p.waitForTimeout(700);
 A(await p.evaluate(()=>!document.getElementById('ibk').hidden&&getComputedStyle(document.getElementById('ibx')).display==='none'&&document.querySelectorAll('#ibk .kc').length>0),tg+'Messages opens on the board first');
 A(await p.locator('#ibsum button').count()===6,tg+'six summary cards');
 A((await p.locator('#ibsum button[data-f=followup] b').textContent())==='1',tg+'follow-up due count shown');
 A(await p.locator('#ibf1 a[data-f]').count()===8&&await p.locator('#ibf2').isHidden(),tg+'eight main filters, rest under More');await p.click('#ibmore');A(await p.locator('#ibf2 a[data-f=aih]').isVisible(),tg+'More opens the rest');
 A(await p.locator('#ibfb a[data-f]').count()===19,tg+'all 17 old filters kept (19 with the 2 new)');
 A(await p.evaluate(()=>document.querySelector('#ibl .th[data-id="5"]').textContent.includes('Follow-up overdue')),tg+'list row shows follow-up overdue text');
 A(await p.evaluate(()=>document.querySelector('#ibl .th[data-id="6"]').textContent.includes('Follow-up in')),tg+'list row shows upcoming follow-up');
 await p.screenshot({path:(vp.width<760?'m':'d')+'-inbox.png'});
 await p.click('#ibvw [data-v=board]');await p.waitForTimeout(500);
 const colOf=id=>p.evaluate(id=>{const k=document.querySelector('#ibk .kc[data-id="'+id+'"]');return k?k.closest('.kcol').dataset.c:null},id);
 const exp={1:'new',2:'ai',3:'work',4:'wait',5:'follow',6:'follow',7:'done',8:'work',9:'ai',10:'new'};
 for(const k in exp)A(await colOf(k)===exp[k],tg+'board: thread '+k+' in '+exp[k]+' (got '+await colOf(k)+')');
 A(await p.evaluate(()=>document.querySelector('#ibk .kc[data-id="5"]').classList.contains('l')&&document.querySelector('#ibk .kc[data-id="5"]').textContent.includes('Follow-up overdue')),tg+'overdue card red with text');
 A(await p.evaluate(()=>getComputedStyle(document.getElementById('ibx')).display==='none'),tg+'inbox hidden in board view');
 await p.screenshot({path:(vp.width<760?'m':'d')+'-board.png'});
 await p.click('#ibk .kc[data-id="3"]');await p.waitForTimeout(300);
 A(await p.evaluate(()=>MChat.mounts.slice(-1)[0]===3&&document.getElementById('ibk').hidden&&!!document.getElementById('mounted')),tg+'card click opens the same chat in Inbox');
 A(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),tg+'no sideways page scroll');
 if(vp.width<760){await p.click('#ibback')}
 await p.click('#ibsum button[data-f=followup]');await p.waitForTimeout(400);
 A(calls.includes('followup')&&await p.locator('#ibl .th').count()===2,tg+'summary card filters the list');
 if(vp.width>900){A(await p.evaluate(()=>{const x=document.getElementById('ibx').getBoundingClientRect();return getComputedStyle(document.getElementById('ibk')).display==='none'&&innerHeight-x.bottom<40}),tg+'Inbox fills the screen down to the bottom (hidden board takes no space)');A(await p.evaluate(()=>document.documentElement.scrollHeight<=innerHeight+1),tg+'fixed screen: no page scroll in Inbox');await p.click('#ibmore');await p.waitForTimeout(150);A(await p.evaluate(()=>document.documentElement.scrollHeight<=innerHeight+1),tg+'fixed screen holds with More open');await p.click('#ibvw [data-v=board]');await p.waitForTimeout(400);A(await p.evaluate(()=>document.documentElement.scrollHeight<=innerHeight+1),tg+'fixed screen: no page scroll on the board')}
 // a saved board view must not hide a deep-linked chat, and the embedded inbox never shows the board
 await p.goto('http://stg.test/admin/chat?f=unread').catch(()=>{});await p.setContent(html,{waitUntil:'domcontentloaded'});await p.waitForTimeout(700);
 A(await p.evaluate(()=>document.getElementById('ibk').hidden),tg+'a filter link (?f=) opens the list, not the board');
 await p.goto('http://stg.test/admin/chat?open=3').catch(()=>{});await p.setContent(html,{waitUntil:'domcontentloaded'});await p.waitForTimeout(900);
 A(await p.evaluate(()=>document.getElementById('ibk').hidden&&MChat.mounts.includes(3)&&getComputedStyle(document.getElementById('ibx')).display!=='none'),tg+'?open= link shows the chat, not the board');
 await p.goto('http://stg.test/admin/chat?embed=1').catch(()=>{});await p.setContent(html,{waitUntil:'domcontentloaded'});await p.waitForTimeout(700);
 A(await p.evaluate(()=>document.getElementById('ibk').hidden&&document.querySelectorAll('#ibl .th').length>0),tg+'embedded inbox stays on the list');
 console.log(tg+'errors',errs);await p.close()}
await b.close()})();
