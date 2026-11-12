# Cold standby relay (same IP/ports - client settings never change)

Only one relay can bind the host SIP/RTP ports, so the standby is deployed ONLY when
the primary `sip-relay` project is down or being replaced.

Swap (about 5-10 min, same content as primary):
1. Hostinger > VPS 2094141 > Docker: stop project `sip-relay` (do not delete).
2. Create project `sip-relay-standby` from `standby/docker-compose.yml`,
   with environment SIGNALWIRE_TOKEN and RELAY_TOKEN (same values as primary).
3. Check /admin/call-center/clients: gateway status up, Live Calls updating.
4. Switch back the same way when the primary is fixed.

The standby file is generated from ../docker-compose.yml; regenerate it after every relay script change.
