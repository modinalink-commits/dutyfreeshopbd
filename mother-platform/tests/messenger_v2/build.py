"""Builds page.html and wjs.js from the real inbox files so the two Playwright tests can run them against a mock server (not deployed: this folder is outside overlay/).
Run:  python3 build.py && node page_test.js && node follow_up_widget_test.js"""
import re, os
ov = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'overlay')
b = open(ov + '/resources/views/chat_inbox.blade.php', encoding='utf8').read()
css = re.search(r'<style>(.*?)</style>', b, re.S).group(1); css = re.sub(r"@if\(request\(\)->boolean\('embed'\)\).*?@endif", '', css, flags=re.S)
body = re.search(r'</style>(.*?)@endsection', b, re.S).group(1); js = re.search(r'@verbatim\s*<script>(.*)</script>\s*@endverbatim', b, re.S).group(1)
w = open(ov + '/resources/views/chat_widget.blade.php', encoding='utf8').read(); wcss = ''.join(re.findall(r'<style>(.*?)</style>', w, re.S))
open('wjs.js', 'w', encoding='utf8').write(open(ov + '/public/js/admin-chat-widget.js', encoding='utf8').read())
open('page.html', 'w', encoding='utf8').write('<!doctype html><html><head><meta charset="utf8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="x"><style>:root{--bd:#E5E7EB}body{margin:0;font:14px Inter,system-ui,sans-serif;background:#F6F7F9}main{padding:14px;box-sizing:border-box}' + css + wcss + '</style></head><body><main>' + body + '</main><script>window.MCSRF="x";window.MCAN=true;window.MUID=1;</script><script>__WJS__</script><script>' + js + '</script></body></html>')
