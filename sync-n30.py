import xml.etree.ElementTree as ET, json, re, html, urllib.request, urllib.parse, sys, os, datetime
SITE='https://dutyfreeshopbd.com'
RAW='https://raw.githubusercontent.com/modinalink-commits/dutyfreeshopbd/main/'
FEEDS=['https://dutyfreebd.com/products.xml','https://www.dutyfreebd.com/products.xml','https://dutyfreebd.shop/products.xml','https://dutyfreebd.store/products.xml','http://dutyfreebd.com/products.xml']
WWW=os.environ.get('WWW','/www')
BASE='https://dutyfreebd.com/public/storage/images/products/'
ns={'g':'http://base.google.com/ns/1.0'}
PERF={'Men Perfume','Women Perfume','Atar','Body Spray For Men','Body Spray For Women'}
PAGES=[
 ('all','','DutyFree Shop BD | ডিউটি ফ্রি দামে অরিজিনাল পারফিউম, সিগারেট ও স্কিন কেয়ার – চট্টগ্রাম ও সারা বাংলাদেশে','ডিউটি ফ্রি দামে অরিজিনাল ইমপোর্টেড পারফিউম, আতর, সিগারেট, ভেপ, স্কিন কেয়ার ও ডেইলি নিডস। WhatsApp-এ অর্ডার করুন, সারা বাংলাদেশে হোম ডেলিভারি।','DutyFree Shop BD – ডিউটি ফ্রি শপ বাংলাদেশ'),
 ('perfume','perfume/','অরিজিনাল ইমপোর্টেড পারফিউম ও আতর – দাম ও অর্ডার | DutyFree Shop BD','পুরুষ ও নারীর অরিজিনাল ইমপোর্টেড পারফিউম, বডি স্প্রে ও আতর ডিউটি ফ্রি দামে। দাম দেখুন, WhatsApp-এ অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি।','পারফিউম ও আতর – অরিজিনাল ইমপোর্টেড'),
 ('smoke','cigarette-vape/','সিগারেট ও ভেপ – কার্টন দামে অরিজিনাল ইমপোর্টেড | DutyFree Shop BD','Marlboro, Dunhill সহ ইমপোর্টেড সিগারেট, ন্যানো সিগারেট ও ভেপ। কার্টন হিসেবে বিক্রি, WhatsApp-এ অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি।','সিগারেট ও ভেপ'),
 ('skin','skin-care/','স্কিন কেয়ার – ফেস ওয়াশ, ক্রিম, সিরাম ও ডিওডোরেন্ট | DutyFree Shop BD','অরিজিনাল ইমপোর্টেড ফেস ওয়াশ, ক্রিম, সিরাম, ডিওডোরেন্ট, শাওয়ার জেল ও আরও স্কিন কেয়ার পণ্য। WhatsApp-এ অর্ডার করুন।','স্কিন কেয়ার'),
 ('hair','hair-care/','হেয়ার কেয়ার – শ্যাম্পু, কন্ডিশনার ও হেয়ার অয়েল | DutyFree Shop BD','ইমপোর্টেড শ্যাম্পু, কন্ডিশনার, হেয়ার অয়েল, হেয়ার ক্রিম ও জেল। দাম দেখুন, WhatsApp-এ অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি।','হেয়ার কেয়ার'),
 ('daily','daily-needs/','ডেইলি নিডস – চকলেট, কফি, অলিভ অয়েল ও বেবি ফুড | DutyFree Shop BD','ইমপোর্টেড চকলেট, কফি, অলিভ অয়েল, মধু, গুঁড়া দুধ ও বেবি ফুড। WhatsApp-এ অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি।','ডেইলি নিডস'),
 ('well','wellness/','পার্সোনাল ওয়েলনেস | DutyFree Shop BD','ইমপোর্টেড পার্সোনাল ওয়েলনেস পণ্য। দাম দেখুন, WhatsApp-এ অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি।','পার্সোনাল ওয়েলনেস'),
 ('other','others/','বেবি কেয়ার, পেইন রিলিফ ও রুম স্প্রে | DutyFree Shop BD','ইমপোর্টেড বেবি কেয়ার, পেইন রিলিফ ও রুম স্প্রে। WhatsApp-এ অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি।','অন্যান্য পণ্য'),
]
def get(u):
    r=urllib.request.Request(u,headers={'User-Agent':'Mozilla/5.0 DutyFreeShopBD-sync'})
    return urllib.request.urlopen(r,timeout=60).read()
def clean(s):
    s=html.unescape(s); s=re.sub(r'[​﻿]','',s)
    s=re.sub(r'([a-z\)\.])([A-Z])',r'\1 \2',s)
    return re.sub(r'\s+',' ',s).strip()
def conv(xml):
    root=ET.fromstring(xml); out=[]
    def g(i,k):
        e=i.find('g:'+k,ns); return (e.text or '').strip() if e is not None else ''
    for i in root.findall('./channel/item'):
        a=[x.strip() for x in g(i,'product_type').split('>')]
        top=a[0]; sec=a[1] if len(a)>1 else ''; third=a[2] if len(a)>2 else ''
        T=top.upper()
        if 'LIFESTYLE' in T:
            if sec in PERF: grp,s='perfume',sec
            elif sec=='SKIN CARE': grp,s='skin',third or 'SKIN CARE'
            elif sec=='HAIR CARE': grp,s='hair',third or 'HAIR CARE'
            elif sec=='Protection': grp,s='well','Protection'
            else: grp,s='other',sec or 'Others'
        elif 'DAILY' in T: grp,s='daily',sec or 'Daily'
        else: grp,s='smoke',sec or top
        try:
            p=int(float(g(i,'sale_price') or g(i,'price'))); o=int(float(g(i,'price'))); pid=int(g(i,'id'))
        except Exception: continue
        img=g(i,'image_link'); m=img[len(BASE):] if img.startswith(BASE) else img
        d=clean(g(i,'description')); full=d[:900]
        if len(d)>170: d=d[:170].rsplit(' ',1)[0]+'…'
        out.append({'i':pid,'n':clean(g(i,'title')),'g':grp,'s':s,'p':p,'o':o if o>p else 0,'m':m,'d':d,'f':full})
    return out
E_=lambda x: html.escape(x,quote=True)
def bn(n):
    return ('{:,}'.format(n)).translate(str.maketrans('0123456789','০১২৩৪৫৬৭৮৯'))
def imgurl(p): return p['m'] if p['m'].startswith('http') else BASE+p['m']

PHONE='8801872404040'
CARE='+8809613828081'
INTRO={
 'all':('ডিউটি ফ্রি শপ বাংলাদেশ – অরিজিনাল ইমপোর্টেড পণ্যের অনলাইন শপ','DutyFree Shop BD চট্টগ্রামভিত্তিক অনলাইন শপ। এখানে অরিজিনাল ইমপোর্টেড পারফিউম ও আতর, সিগারেট ও ভেপ, স্কিন কেয়ার, হেয়ার কেয়ার এবং ডেইলি নিডস পাওয়া যায়। পছন্দের পণ্য অর্ডার স্লিপে যোগ করে WhatsApp-এ অর্ডার পাঠান, সারা বাংলাদেশে হোম ডেলিভারি দেওয়া হয়।'),
 'perfume':('অরিজিনাল ইমপোর্টেড পারফিউম ও আতর','পুরুষ ও নারীর জন্য অরিজিনাল ইমপোর্টেড পারফিউম, বডি স্প্রে ও আতরের সংগ্রহ। প্রতিটি পণ্যের দাম দেখে সরাসরি WhatsApp-এ অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি।'),
 'smoke':('ইমপোর্টেড সিগারেট ও ভেপ','Marlboro, Dunhill, Davidoff, Mond সহ ইমপোর্টেড সিগারেট ব্র্যান্ড, ন্যানো সিগারেট ও ভেপ। সিগারেট কার্টন হিসেবে বিক্রি হয়। WhatsApp-এ অর্ডার করুন।'),
 'skin':('স্কিন কেয়ার পণ্য','ফেস ওয়াশ, ক্রিম, সিরাম, ডিওডোরেন্ট, শেভ জেল, শাওয়ার জেলসহ অরিজিনাল ইমপোর্টেড স্কিন কেয়ার পণ্য। পছন্দের পণ্য বেছে WhatsApp-এ অর্ডার করুন।'),
 'hair':('হেয়ার কেয়ার পণ্য','ইমপোর্টেড শ্যাম্পু, কন্ডিশনার, হেয়ার অয়েল, হেয়ার ক্রিম ও জেল। দাম দেখে WhatsApp-এ অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি।'),
 'daily':('ডেইলি নিডস – ইমপোর্টেড দৈনন্দিন পণ্য','চকলেট, কফি, অলিভ অয়েল, মধু, গুঁড়া দুধ ও বেবি ফুডসহ ইমপোর্টেড দৈনন্দিন পণ্য। WhatsApp-এ অর্ডার করুন।'),
 'well':('পার্সোনাল ওয়েলনেস পণ্য','ইমপোর্টেড পার্সোনাল ওয়েলনেস পণ্য। দাম দেখে WhatsApp-এ অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি।'),
 'other':('বেবি কেয়ার, পেইন রিলিফ ও রুম স্প্রে','ইমপোর্টেড বেবি কেয়ার, পেইন রিলিফ ও রুম স্প্রেসহ অন্যান্য পণ্য। WhatsApp-এ অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি।'),
}
FAQ=[('কীভাবে অর্ডার করব?','পছন্দের পণ্য অর্ডার স্লিপে যোগ করে নাম, মোবাইল নম্বর ও ঠিকানা লিখুন। অর্ডারের মেসেজ নিজে থেকেই WhatsApp-এ তৈরি হবে, শুধু Send চাপুন।'),
     ('পণ্য কি অরিজিনাল?','আমরা ইমপোর্টেড অরিজিনাল পণ্য বিক্রি করি। কোনো প্রশ্ন থাকলে WhatsApp-এ জিজ্ঞেস করুন।'),
     ('সারা বাংলাদেশে ডেলিভারি পাওয়া যায়?','হ্যাঁ, সারা বাংলাদেশে হোম ডেলিভারি দেওয়া হয়। অর্ডার কনফার্মের সময় আমাদের টিম জানিয়ে দেবে।')]
def slug(p):
    s=re.sub(r'[^a-z0-9]+','-',p['n'].lower()).strip('-')[:60].strip('-') or 'product'
    return '%d-%s'%(p['i'],s)
def purl(p): return 'p/'+slug(p)+'/'
def logo_from(tpl):
    m=re.search(r'<img class="lg" src="data:image/webp;base64,([^"]+)"',tpl)
    if m:
        import base64
        open(os.path.join(WWW,'logo.webp'),'wb').write(base64.b64decode(m.group(1)))
def seotxt(key,items,pg):
    h,tx=INTRO[key]
    faq=''.join('<details><summary>%s</summary><p>%s</p></details>'%(E_(q),E_(a)) for q,a in FAQ)
    rel='<div class="rl">'+''.join('<a href="/%s">%s</a>'%(g[1],E_(g[4])) for g in PAGES if g[0]!=key)+'</div>'
    pl=''.join('<a href="/%s">%s – &#2547; %s</a>'%(purl(p),E_(p['n']),bn(p['p'])) for p in items)
    return '<h2>%s</h2><p>%s</p><h3>সাধারণ প্রশ্ন</h3>%s<details><summary>এই ক্যাটাগরির সব পণ্যের তালিকা (%s টি)</summary><div class="pl">%s</div></details><h3>অন্যান্য ক্যাটাগরি</h3>%s'%(E_(h),E_(tx),faq,bn(len(items)),pl,rel)
PCSS="""*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Anek Bangla',system-ui,sans-serif;background:#F3F4F6;color:#0C161F;line-height:1.65;max-width:560px;margin:0 auto;padding-bottom:84px;font-size:16px}a{color:inherit;text-decoration:none}
header{background:#fff;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #E6E8EC;position:sticky;top:0;z-index:5}header img{height:34px;width:auto}header a.b{font-size:13px;font-weight:700;color:#F95505}
.bc{font-size:12.5px;color:#6B7683;padding:10px 14px}.bc a{color:#6B7683}.bc a:hover{color:#F95505}
.card{background:#fff;margin:0 14px 12px;border:1px solid #E6E8EC;border-radius:16px;padding:14px}.im{aspect-ratio:1;max-height:360px;display:grid;place-items:center;background:#fff}.im img{max-width:100%;max-height:100%;object-fit:contain}
h1{font-size:20px;line-height:1.4;font-weight:800;margin:6px 0}.pr{display:flex;gap:10px;align-items:baseline;margin:4px 0 10px}.pr b{font-size:26px;color:#F95505;font-weight:800}.pr s{color:#6B7683}.pr i{font-style:normal;background:#F95505;color:#fff;font-size:12px;font-weight:800;padding:2px 8px;border-radius:8px}
.cta{display:flex;gap:8px}.cta a{flex:1;text-align:center;padding:13px 10px;border-radius:14px;font-weight:800;font-size:15px}.wa{background:#25D366;color:#fff}.call{background:#0C161F;color:#fff}
h2{font-size:17px;font-weight:800;margin:0 0 6px}.d p{font-size:14.5px;color:#3b4652}.meta{font-size:13.5px;color:#6B7683;margin-top:6px}.meta a{color:#F95505;font-weight:700}
.rg{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 14px}.rc{background:#fff;border:1px solid #E6E8EC;border-radius:14px;padding:8px;display:block}.rc img{width:100%;aspect-ratio:1;object-fit:contain}.rc span{display:block;font-size:12.5px;font-weight:600;line-height:1.35;margin-top:4px;min-height:34px}.rc b{font-size:14.5px}
footer{padding:16px 14px;font-size:13px;color:#6B7683}footer a{display:inline-block;margin:2px 10px 2px 0;color:#0C161F;font-weight:600}
.fix{position:fixed;left:50%;transform:translateX(-50%);bottom:0;width:100%;max-width:560px;background:#fff;border-top:1px solid #E6E8EC;padding:8px 14px calc(8px + env(safe-area-inset-bottom));display:flex;gap:8px}.fix a{flex:1;text-align:center;padding:12px;border-radius:14px;font-weight:800}"""
def ppage(p,prods,idx):
    canon=SITE+'/'+purl(p); img=imgurl(p); gname=[g for g in PAGES if g[0]==p['g']][0]
    cname=gname[4]; curl=SITE+'/'+gname[1]
    desc=(p['f'] or p['n']); short=(desc[:150].rsplit(' ',1)[0]+'…') if len(desc)>150 else desc
    mdesc='%s – দাম ৳ %s। অরিজিনাল ইমপোর্টেড পণ্য, WhatsApp-এ অর্ডার করুন, সারা বাংলাদেশে ডেলিভারি। DutyFree Shop BD.'%(p['n'],bn(p['p']))
    title='%s দাম ৳ %s | %s | DutyFree Shop BD'%(p['n'],bn(p['p']),cname)
    ld=[{"@context":"https://schema.org","@type":"Product","name":p['n'],"image":[img],"sku":str(p['i']),"description":desc or p['n'],"category":cname,
         "brand":{"@type":"Brand","name":p['n'].split(' ')[0]},
         "offers":{"@type":"Offer","url":canon,"priceCurrency":"BDT","price":str(p['p']),"availability":"https://schema.org/InStock","itemCondition":"https://schema.org/NewCondition","seller":{"@type":"Organization","name":"DutyFree Shop BD"}}},
        {"@context":"https://schema.org","@type":"BreadcrumbList","itemListElement":[
          {"@type":"ListItem","position":1,"name":"হোম","item":SITE+'/'},
          {"@type":"ListItem","position":2,"name":cname,"item":curl},
          {"@type":"ListItem","position":3,"name":p['n'],"item":canon}]}]
    rel=[q for q in prods if q['g']==p['g'] and q['i']!=p['i']]
    same=[q for q in rel if q['s']==p['s']]; rest=[q for q in rel if q['s']!=p['s']]
    rel=(same+rest)[:8]
    rhtml=''.join('<a class="rc" href="/%s"><img src="%s" alt="%s" width="200" height="200" loading="lazy"><span>%s</span><b>&#2547; %s</b></a>'%(purl(q),E_(imgurl(q)),E_(q['n']),E_(q['n']),bn(q['p'])) for q in rel)
    off=int(round((p['o']-p['p'])/p['o']*100)) if p['o'] else 0
    msg='আসসালামু আলাইকুম, আমি এই পণ্যটি অর্ডার করতে চাই: %s (DF-%d) – ৳ %s। %s'%(p['n'],p['i'],bn(p['p']),canon)
    wa='https://wa.me/%s?text=%s'%(PHONE,urllib.parse.quote(msg))
    cats=''.join('<a href="/%s">%s</a>'%(g[1],E_(g[4])) for g in PAGES[1:])
    return ('<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
      '<title>%s</title><meta name="description" content="%s"><link rel="canonical" href="%s"><meta name="robots" content="index,follow,max-image-preview:large">'
      '<meta name="theme-color" content="#0C161F"><meta property="og:type" content="product"><meta property="og:site_name" content="DutyFree Shop BD"><meta property="og:locale" content="bn_BD">'
      '<meta property="og:title" content="%s"><meta property="og:description" content="%s"><meta property="og:url" content="%s"><meta property="og:image" content="%s">'
      '<meta name="twitter:card" content="summary_large_image"><link rel="icon" href="/icon.png"><link rel="apple-touch-icon" href="/icon.png">'
      '<script async src="https://www.googletagmanager.com/gtag/js?id=G-8WB5RDDK4K"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag("js",new Date());gtag("config","G-8WB5RDDK4K",{currency:"BDT"});</script><script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");fbq("init","27119431750984671");fbq("track","PageView");</script><link rel="preconnect" href="https://dutyfreebd.com"><script type="application/ld+json">%s</script><style>%s</style></head><body>'
      '<header><a href="/"><img src="/logo.webp" alt="DutyFree Shop BD" width="80" height="34"></a><a class="b" href="/%s">%s ›</a></header>'
      '<nav class="bc" aria-label="breadcrumb"><a href="/">হোম</a> › <a href="/%s">%s</a> › %s</nav>'
      '<main><div class="card"><div class="im"><img src="%s" alt="%s" width="480" height="480" fetchpriority="high"></div></div>'
      '<div class="card"><h1>%s</h1><div class="pr"><b>&#2547; %s</b>%s%s</div>'
      '<div class="cta"><a class="wa" href="%s" rel="noopener">WhatsApp-এ অর্ডার</a><a class="call" href="tel:+8801871600600">কল করুন</a></div>'
      '<p class="meta">কোড: DF-%d · ক্যাটাগরি: <a href="/%s">%s</a> · স্টকে আছে</p></div>'
      '<div class="card d"><h2>পণ্যের বিবরণ</h2><p>%s</p></div>'
      '<div class="card d"><h2>অর্ডার ও ডেলিভারি</h2><p>WhatsApp-এ অর্ডার পাঠান, আমাদের টিম কনফার্ম করবে। সারা বাংলাদেশে হোম ডেলিভারি। যোগাযোগ: WhatsApp ০১৮৭২-৪০৪০৪০, কল ০১৮৭১-৬০০৬০০, কাস্টমার কেয়ার ০৯৬১৩-৮২৮০৮১।</p></div>'
      '<div style="padding:4px 14px 8px"><h2>আরও দেখুন</h2></div><div class="rg">%s</div></main>'
      '<footer><div>ক্যাটাগরি: %s</div><p style="margin-top:8px">© DutyFree Shop BD – অরিজিনাল ইমপোর্টেড পণ্য, সারা বাংলাদেশে ডেলিভারি।</p></footer>'
      '<div class="fix"><a class="wa" href="%s" rel="noopener">WhatsApp-এ অর্ডার</a><a class="call" href="/">আরও পণ্য</a></div><script>gtag("event","view_item",{currency:"BDT",value:%d,items:[{item_id:"%d",item_name:%s,price:%d}]});fbq("track","ViewContent",{currency:"BDT",value:%d,content_ids:["%d"],content_type:"product"});document.addEventListener("click",function(e){var a=e.target.closest("a");if(!a)return;var h=a.getAttribute("href")||"";if(h.indexOf("wa.me")>-1){gtag("event","whatsapp_order_click",{item_id:"%d"});fbq("track","Contact")}else if(h.indexOf("tel:")===0)gtag("event","call_click")});</script></body></html>')%(
      E_(title),E_(mdesc),canon,E_(title),E_(mdesc),canon,E_(img),json.dumps(ld,ensure_ascii=False).replace('</','<\\/'),PCSS,
      gname[1],E_(cname),gname[1],E_(cname),E_(p['n']),E_(img),E_(p['n']),E_(p['n']),bn(p['p']),('<s>&#2547; %s</s>'%bn(p['o']) if p['o'] else ''),('<i>-%s%%</i>'%bn(off) if off>0 else ''),
      E_(wa),p['i'],gname[1],E_(cname),E_(desc or p['n']),rhtml,cats,E_(wa),p['p'],p['i'],json.dumps(p['n'],ensure_ascii=False).replace('</','<\\/'),p['p'],p['p'],p['i'],p['i'])
def page(tpl,data,prods,pg):
    key,path,title,desc,h1=pg
    canon=SITE+'/'+path
    items=[p for p in prods if key=='all' or p['g']==key]
    top=items[:36]
    ld=[{"@context":"https://schema.org","@type":"OnlineStore","name":"DutyFree Shop BD","url":SITE+'/',"logo":SITE+"/icon.png","telephone":"+8801872404040","contactPoint":[{"@type":"ContactPoint","telephone":"+8801871600600","contactType":"sales","areaServed":"BD","availableLanguage":"bn"},{"@type":"ContactPoint","telephone":"+8809613828081","contactType":"customer service","areaServed":"BD","availableLanguage":"bn"}],"areaServed":"BD","address":{"@type":"PostalAddress","addressLocality":"Chattogram","addressCountry":"BD"}},
        {"@context":"https://schema.org","@type":"FAQPage","mainEntity":[{"@type":"Question","name":q,"acceptedAnswer":{"@type":"Answer","text":a}} for q,a in FAQ]},
        {"@context":"https://schema.org","@type":"WebSite","name":"DutyFree Shop BD","url":SITE+'/',"inLanguage":"bn-BD"},
        {"@context":"https://schema.org","@type":"ItemList","name":h1,"itemListElement":[
          {"@type":"ListItem","position":k+1,"item":{"@type":"Product","name":p['n'],"image":imgurl(p),"sku":str(p['i']),"description":p['d'] or p['n'],
            "offers":{"@type":"Offer","url":canon+'#df-'+str(p['i']),"priceCurrency":"BDT","price":str(p['p']),"availability":"https://schema.org/InStock","itemCondition":"https://schema.org/NewCondition"}}} for k,p in enumerate(top)]}]
    head=('<title>%s</title>\n<meta name="description" content="%s">\n<link rel="canonical" href="%s">\n'
      '<meta name="robots" content="index,follow,max-image-preview:large">\n'
      '<meta property="og:type" content="website"><meta property="og:site_name" content="DutyFree Shop BD"><meta property="og:locale" content="bn_BD">\n'
      '<meta property="og:title" content="%s"><meta property="og:description" content="%s"><meta property="og:url" content="%s"><meta property="og:image" content="%s/og.png">\n'
      '<meta name="twitter:card" content="summary_large_image">\n<link rel="icon" href="/icon.png"><link rel="apple-touch-icon" href="/icon.png">\n'
      '<script type="application/ld+json">%s</script>\n<script>window.DF_G=%s</script>')%(
      E_(title),E_(desc),canon,E_(title),E_(desc),canon,SITE,json.dumps(ld,ensure_ascii=False).replace('</','<\\/'),json.dumps(key))
    ssr=''.join('<article><h3><a href="/%s">%s</a></h3><p>%s &#2547; %s</p></article>'%(purl(p),E_(p['n']),E_(p['s']),bn(p['p'])) for p in items[:60])
    cats=''.join('<a href="/%s">%s</a>'%(g[1],E_(g[4])) for g in PAGES[1:])
    catblock='<div class="cl"><h4>ক্যাটাগরি</h4>'+cats+'</div>'
    out=tpl.replace('__PDATA__',data).replace('__HEAD__',head).replace('__SSR__',ssr).replace('__H1__',E_(h1)).replace('__CATLINKS__',catblock).replace('__SEOTXT__',seotxt(key,items,pg))
    return out
def write(path,content):
    full=os.path.join(WWW,path); os.makedirs(os.path.dirname(full) or WWW,exist_ok=True)
    tmp=full+'.tmp'; open(tmp,'w',encoding='utf8').write(content); os.replace(tmp,full)
def build(tpl,feed):
    prods=conv(feed)
    if len(prods)<50: raise SystemExit('too few products: %d'%len(prods))
    data=json.dumps([{k:v for k,v in p.items() if k!='f'} for p in prods],ensure_ascii=False,separators=(',',':')).replace('</','<\\/')
    for pg in PAGES: write(pg[1]+'index.html',page(tpl,data,prods,pg))
    logo_from(tpl)
    for p in prods: write(purl(p)+'index.html',ppage(p,prods,0))
    write('404.html','<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>পেজ পাওয়া যায়নি</title><body style="font-family:sans-serif;text-align:center;padding:60px 20px"><h1>পেজ পাওয়া যায়নি</h1><p><a href="/">DutyFree Shop BD হোমে ফিরে যান</a></p></body>')
    today=datetime.date.today().isoformat()
    write('sitemap.xml','<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'+''.join('<url><loc>%s/%s</loc><lastmod>%s</lastmod><changefreq>daily</changefreq><priority>%s</priority></url>'%(SITE,pg[1],today,'1.0' if not pg[1] else '0.8') for pg in PAGES)+''.join('<url><loc>%s/%s</loc><lastmod>%s</lastmod><changefreq>weekly</changefreq><priority>0.6</priority><image:image><image:loc>%s</image:loc><image:title>%s</image:title></image:image></url>'%(SITE,purl(p),today,E_(imgurl(p)),E_(p['n'])) for p in prods)+'</urlset>')
    write('robots.txt','User-agent: *\nAllow: /\nSitemap: %s/sitemap.xml\n'%SITE)
    print('ok',len(prods),flush=True)
def assets():
    for n in ('og.png','icon.png','ban-sx555.webp'):
        try:
            b=get(RAW+n); open(os.path.join(WWW,n),'wb').write(b)
        except Exception as e: print('asset',n,e,file=sys.stderr,flush=True)
def run():
    print('start',flush=True)
    tpl=get(RAW+'template-v5-n30.html').decode('utf8')
    print('template ok',len(tpl),flush=True)
    assets()
    feed=None
    for n in range(3):
        for u in FEEDS:
            try:
                r=urllib.request.Request(u,headers={'User-Agent':'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36','Accept':'application/xml,text/xml,*/*'})
                feed=urllib.request.urlopen(r,timeout=90).read(); print('feed ok',u,len(feed),flush=True); break
            except Exception as e: print('feed fail',u,e,flush=True)
        if feed: break
    if not feed: raise SystemExit('no feed reachable')
    build(tpl,feed)
if __name__=='__main__':
    try: run()
    except BaseException as e: print('sync failed:',e,file=sys.stderr,flush=True)
