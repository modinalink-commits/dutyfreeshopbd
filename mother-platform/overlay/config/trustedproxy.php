<?php

// The site runs behind Traefik (and nginx) inside a private Docker network. Without this,
// every visitor looked like the same gateway IP (172.16.x.x): rate limits, blocked IPs and
// order/session IPs were shared by ALL customers. Trust the private ranges only, so the
// real client IP is read from X-Forwarded-For (set by Traefik) and cannot be spoofed from outside.
return [
    'proxies' => ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '::1', 'fc00::/7'],
];
