/* DutyFreeBD service worker. Version: __VER__ */
const VER = '__VER__';
const SHELL = 'dfbd-shell-' + VER;
const IMG = 'dfbd-img-v1';
const OFFLINE = '/offline';
const PRECACHE = [OFFLINE, '/pwa/icon-192.png', '/pwa/logo-240.png'];

self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(SHELL).then(function (c) { return c.addAll(PRECACHE); }));
});

self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (ks) {
    return Promise.all(ks.filter(function (k) { return k.indexOf('dfbd-shell-') === 0 && k !== SHELL; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

self.addEventListener('message', function (e) { if (e.data === 'SKIP_WAITING') self.skipWaiting(); });

function trim(name, max) {
  caches.open(name).then(function (c) { c.keys().then(function (ks) { if (ks.length > max) c.delete(ks[0]).then(function () { trim(name, max); }); }); });
}

self.addEventListener('fetch', function (e) {
  var r = e.request;
  if (r.method !== 'GET') return;
  var u = new URL(r.url);
  if (u.origin !== location.origin) return;
  // Pages: always the network (prices, stock, orders, payment are never served from cache). Offline page only when the network fails.
  if (r.mode === 'navigate') {
    e.respondWith(fetch(r).catch(function () { return caches.match(OFFLINE); }));
    return;
  }
  // Product / banner / logo images: show cached copy instantly, refresh in background.
  if (r.destination === 'image' && !/^\/(admin|api)/.test(u.pathname)) {
    e.respondWith(caches.open(IMG).then(function (c) {
      return c.match(r).then(function (hit) {
        var net = fetch(r).then(function (res) { if (res && res.ok) { c.put(r, res.clone()); trim(IMG, 180); } return res; }).catch(function () { return hit; });
        return hit || net;
      });
    }));
    return;
  }
  // Everything else (JSON, scripts, account, checkout, admin): straight to the network.
});

self.addEventListener('push', function (e) {
  var d = {};
  try { d = e.data ? e.data.json() : {}; } catch (x) { d = { title: 'DutyFreeBD', body: e.data ? e.data.text() : '' }; }
  var opts = {
    body: d.body || '',
    icon: d.icon || '/pwa/icon-192.png',
    badge: '/pwa/badge-96.png',
    image: d.image || undefined,
    tag: d.tag || undefined,
    renotify: !!d.tag,
    data: { url: d.url || '/', nid: d.nid || 0 },
    actions: d.cta ? [{ action: 'open', title: d.cta }] : []
  };
  e.waitUntil(self.registration.showNotification(d.title || 'DutyFreeBD', opts));
});

self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = (e.notification.data && e.notification.data.url) || '/';
  var nid = (e.notification.data && e.notification.data.nid) || 0;
  e.waitUntil((async function () {
    if (nid) { try { fetch('/push/clicked', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ nid: nid }), keepalive: true }); } catch (x) {} }
    var list = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (var i = 0; i < list.length; i++) {
      var c = list[i];
      if ('focus' in c) { try { await c.navigate(url); } catch (x) {} return c.focus(); }
    }
    return self.clients.openWindow(url);
  })());
});
