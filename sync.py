import xml.etree.ElementTree as ET, json, re, html, urllib.request, sys, os, datetime
SITE='https://dutyfreeshopbd.com'
RAW='https://raw.githubusercontent.com/modinalink-commits/dutyfreeshopbd/main/'
FEED='https://dutyfreebd.com/products.xml'
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
        d=clean(g(i,'description'))
        if len(d)>170: d=d[:170].rsplit(' ',1)[0]+'…'
        out.append({'i':pid,'n':clean(g(i,'title')),'g':grp,'s':s,'p':p,'o':o if o>p else 0,'m':m,'d':d})
    return out
E_=lambda x: html.escape(x,quote=True)
def bn(n):
    return ('{:,}'.format(n)).translate(str.maketrans('0123456789','০১২৩৪৫৬৭৮৯'))
def imgurl(p): return p['m'] if p['m'].startswith('http') else BASE+p['m']
def page(tpl,data,prods,pg):
    key,path,title,desc,h1=pg
    canon=SITE+'/'+path
    items=[p for p in prods if key=='all' or p['g']==key]
    top=items[:36]
    ld=[{"@context":"https://schema.org","@type":"OnlineStore","name":"DutyFree Shop BD","url":SITE+'/',"logo":SITE+"/icon.png","telephone":"+8801872404040","areaServed":"BD","address":{"@type":"PostalAddress","addressLocality":"Chattogram","addressCountry":"BD"}},
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
    ssr=''.join('<article><h3>%s</h3><p>%s &#2547; %s</p></article>'%(E_(p['n']),E_(p['s']),bn(p['p'])) for p in items[:60])
    cats=''.join('<a href="/%s">%s</a>'%(g[1],E_(g[4])) for g in PAGES[1:])
    catblock='<div class="cl"><h4>ক্যাটাগরি</h4>'+cats+'</div>'
    out=tpl.replace('__PDATA__',data).replace('__HEAD__',head).replace('__SSR__',ssr).replace('__H1__',E_(h1)).replace('__CATLINKS__',catblock)
    return out
def write(path,content):
    full=os.path.join(WWW,path); os.makedirs(os.path.dirname(full) or WWW,exist_ok=True)
    tmp=full+'.tmp'; open(tmp,'w',encoding='utf8').write(content); os.replace(tmp,full)
def build(tpl,feed):
    prods=conv(feed)
    if len(prods)<50: raise SystemExit('too few products: %d'%len(prods))
    data=json.dumps(prods,ensure_ascii=False,separators=(',',':')).replace('</','<\\/')
    for pg in PAGES: write(pg[1]+'index.html',page(tpl,data,prods,pg))
    today=datetime.date.today().isoformat()
    write('sitemap.xml','<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'+''.join('<url><loc>%s/%s</loc><lastmod>%s</lastmod><changefreq>daily</changefreq><priority>%s</priority></url>'%(SITE,pg[1],today,'1.0' if not pg[1] else '0.8') for pg in PAGES)+'</urlset>')
    write('robots.txt','User-agent: *\nAllow: /\nSitemap: %s/sitemap.xml\n'%SITE)
    print('ok',len(prods),flush=True)
def assets():
    for n in ('og.png','icon.png'):
        try:
            b=get(RAW+n); open(os.path.join(WWW,n),'wb').write(b)
        except Exception as e: print('asset',n,e,file=sys.stderr,flush=True)
def run():
    print('start',flush=True)
    tpl=get(RAW+'template-v4.html').decode('utf8')
    print('template ok',len(tpl),flush=True)
    assets()
    build(tpl,get(FEED))
if __name__=='__main__':
    try: run()
    except BaseException as e: print('sync failed:',e,file=sys.stderr,flush=True)
