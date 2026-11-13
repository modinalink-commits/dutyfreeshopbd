#!/bin/bash
# DutyFreeBD SIP relay (FreeSWITCH). Receives wholesale client calls by IP + prefix.
# MODE=test : accept the client's call, ring for 8s, then reject (no carrier connected yet, nothing is billed).
# Secrets come ONLY from container environment (SIGNALWIRE_TOKEN). Nothing secret is stored in this file.
set -e
: "${CLIENT_IP:?}"; : "${PREFIX:?}"; : "${PUBLIC_IP:?}"; MAX_CALLS="${MAX_CALLS:-20}"; MODE="${MODE:-test}"
if [ -z "$SIGNALWIRE_TOKEN" ]; then echo "SIGNALWIRE_TOKEN is empty - set it in the project environment, then restart."; sleep infinity; fi
if ! command -v freeswitch >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq && apt-get install -y -qq gnupg2 wget lsb-release ca-certificates >/dev/null
  wget -q --http-user=signalwire --http-password="$SIGNALWIRE_TOKEN" -O /usr/share/keyrings/signalwire-freeswitch-repo.gpg https://freeswitch.signalwire.com/repo/deb/debian-release/signalwire-freeswitch-repo.gpg
  printf 'machine freeswitch.signalwire.com login signalwire password %s\n' "$SIGNALWIRE_TOKEN" > /etc/apt/auth.conf; chmod 600 /etc/apt/auth.conf
  echo "deb [signed-by=/usr/share/keyrings/signalwire-freeswitch-repo.gpg] https://freeswitch.signalwire.com/repo/deb/debian-release/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/freeswitch.list
  apt-get update -qq
  apt-get install -y -qq freeswitch freeswitch-mod-console freeswitch-mod-logfile freeswitch-mod-event-socket freeswitch-mod-sofia freeswitch-mod-commands freeswitch-mod-dptools freeswitch-mod-dialplan-xml freeswitch-mod-hash freeswitch-mod-db freeswitch-mod-sndfile >/dev/null
  rm -f /etc/apt/auth.conf
fi
# G729 transcoding (client sends only G729): build the open-source mod_bcg729 once (needs the same SignalWire token for freeswitch dev headers).
if [ ! -f /usr/lib/freeswitch/mod/mod_bcg729.so ]; then
  export DEBIAN_FRONTEND=noninteractive
  printf 'machine freeswitch.signalwire.com login signalwire password %s\n' "$SIGNALWIRE_TOKEN" > /etc/apt/auth.conf; chmod 600 /etc/apt/auth.conf
  apt-get update -qq || true
  apt-get install -y -qq git build-essential cmake pkg-config libbcg729-dev libfreeswitch-dev >/dev/null || true
  rm -f /etc/apt/auth.conf
  ( cd /usr/src && rm -rf mod_bcg729 && git clone -q https://github.com/xadhoom/mod_bcg729.git && cd mod_bcg729 && make >/dev/null && make install >/dev/null ) || echo "mod_bcg729 build failed - G729 calls will not transcode"
fi
# Optional: pull live settings (PBX line, extensions, mode) from the admin Call Center. Needs RELAY_TOKEN (environment only).
RELAY_CONFIG_URL="${RELAY_CONFIG_URL:-https://dutyfreebd.com/api/voice/relay-config}"
pull_cfg() { wget -q -T 15 -O - --header="Authorization: Bearer $RELAY_TOKEN" "$RELAY_CONFIG_URL" 2>/dev/null; }
if [ -n "$RELAY_TOKEN" ]; then
  CFG=$(pull_cfg || true)
  if [ -n "$CFG" ]; then
    while IFS='=' read -r k v; do
      case "$k" in MODE|PBX_HOST|PBX_PORT|PBX_TRANSPORT|PBX_REALM|PBX_EXTS|CALLER_ID|CLIENT_IP|MAX_CALLS) export "$k=$v" ;; esac
    done <<< "$CFG"
    echo "relay config pulled from admin panel (mode=$MODE host=${PBX_HOST:-none})"
  else echo "relay config pull failed or empty - using environment values"; fi
  CFGSUM=$(printf '%s' "$CFG" | md5sum | cut -d' ' -f1)
  ( while :; do sleep 60 || sleep 60 || true; N=$(pull_cfg || true); [ -n "$N" ] && [ "$(printf '%s' "$N" | md5sum | cut -d' ' -f1)" != "$CFGSUM" ] && { echo "relay config changed - restarting"; pkill -x freeswitch; kill 1; }; done ) &
fi
MODE="${MODE:-test}"; PBX_PORT="${PBX_PORT:-5080}"; PBX_TRANSPORT="${PBX_TRANSPORT:-tcp}"
IPRE=$(printf '%s' "$CLIENT_IP" | sed 's/\./\\./g')
ESLPW=$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 20)
# Optional PBX leg (MODE=live): the relay registers as ordinary PBX extension(s), like a softphone, and sends calls out through them.
# PBX_HOST, PBX_PORT(5080), PBX_TRANSPORT(tcp), PBX_REALM(optional), PBX_EXTS="ext1:pass1,ext2:pass2" (secrets: environment only), CALLER_ID(optional)
PBX_PORT="${PBX_PORT:-5080}"; PBX_TRANSPORT="${PBX_TRANSPORT:-tcp}"
xe() { printf '%s' "$1" | sed 's/&/\&amp;/g;s/</\&lt;/g;s/"/\&quot;/g'; }
GWXML=""; BRIDGE=""; i=0
CNAME="${CALLER_NAME:-ChildCity}";
if [ -n "$PBX_HOST" ] && [ -n "$PBX_EXTS" ]; then
  IFS=',' read -ra EXA <<< "$PBX_EXTS"
  for e in "${EXA[@]}"; do
    i=$((i+1)); u=$(xe "${e%%:*}"); p=$(xe "${e#*:}")
    GWXML="$GWXML<gateway name=\"pbx$i\"><param name=\"username\" value=\"$u\"/><param name=\"password\" value=\"$p\"/><param name=\"realm\" value=\"${PBX_REALM:-$PBX_HOST}\"/><param name=\"proxy\" value=\"${PBX_REALM:-$PBX_HOST}:$PBX_PORT;transport=$PBX_TRANSPORT\"/><param name=\"register\" value=\"true\"/><param name=\"from-domain\" value=\"${PBX_REALM:-$PBX_HOST}\"/><param name=\"register-transport\" value=\"$PBX_TRANSPORT\"/><param name=\"retry-seconds\" value=\"30\"/><param name=\"caller-id-in-from\" value=\"true\"/></gateway>"
    BRIDGE="$BRIDGE<action application=\"limit_execute\" data=\"hash relay_pbx pbx$i ${EXT_MAX:-5} bridge {absolute_codec_string='PCMA,PCMU',origination_caller_id_name=$CNAME,effective_caller_id_name=$CNAME,origination_caller_id_number=$u,effective_caller_id_number=$u}sofia/gateway/pbx$i/0\$1\"/>"
  done
fi
CID=""  # caller-id = each extension's own number (PBX applies its outbound caller ID)
if [ "$MODE" = "live" ] && [ -n "$BRIDGE" ]; then
  CALLACTS="$CID<action application=\"set\" data=\"continue_on_fail=true\"/><action application=\"set\" data=\"call_timeout=45\"/>$BRIDGE<action application=\"hangup\" data=\"NORMAL_TEMPORARY_FAILURE\"/>"
else
  CALLACTS="<action application=\"ring_ready\"/><action application=\"sleep\" data=\"8000\"/><action application=\"hangup\" data=\"NO_ROUTE_DESTINATION\"/>"
fi
# Billing CDR: after each hangup, report answered calls (billsec>0) to the admin panel. Only uuid/seconds/dialled digits are passed.
if [ -n "$RELAY_TOKEN" ]; then
  RELAY_CDR_URL="${RELAY_CDR_URL:-${RELAY_CONFIG_URL%relay-config}relay-cdr}"
  cat > /usr/local/bin/relay-cdr.sh <<CDR
#!/bin/sh
# one arg: uuid|billsec|dialled|cause|caller  (every finished call is reported, answered or not)
IFS='|' read -r U B D C P <<ARGS
\$1
ARGS
case "\$U" in *[!0-9a-fA-F-]*|"") exit 0;; esac
case "\$B" in *[!0-9]*|"") B=0;; esac
case "\$D" in *[!0-9]*|"") exit 0;; esac
case "\$C" in *[!A-Z_]*|"") C=UNKNOWN;; esac
case "\$P" in *[!0-9+]*) P="";; esac
# The dialplan hook expands billsec/cause when the call starts (always empty): read the real values from the live channel at hangup, bounded by a timeout.
if [ "\$B" = 0 ]; then V=\$(timeout 3 fs_cli -p "$ESLPW" -x "uuid_getvar \$U billsec" 2>/dev/null); case "\$V" in ""|*[!0-9]*) ;; *) B=\$V;; esac; fi
if [ "\$C" = UNKNOWN ]; then V=\$(timeout 3 fs_cli -p "$ESLPW" -x "uuid_getvar \$U hangup_cause" 2>/dev/null); case "\$V" in ""|*[!A-Z_]*) ;; *) C=\$V;; esac; fi
wget -q -t 3 -T 8 -O /dev/null --header="Authorization: Bearer $RELAY_TOKEN" --post-data="uuid=\$U&billsec=\$B&dest=\$D&cause=\$C&caller=\$P" "$RELAY_CDR_URL"
# Self-heal: if the live push has been silent for 5 minutes, start it again (a new start replaces any old loop).
[ -x /usr/local/bin/relay-push.sh ] || exit 0
L=\$(cat /tmp/live.ok 2>/dev/null); N=\$(date +%s)
case "\$L" in ""|*[!0-9]*) L=0;; esac
case "\$N" in ""|*[!0-9]*) exit 0;; esac
if [ \$((N-L)) -gt 300 ] && mkdir /tmp/heal.lock 2>/dev/null; then
  echo "\$N" > /tmp/live.ok
  echo "cdr hook: live push silent for \$((N-L)) s - starting it again" > /proc/1/fd/1 2>/dev/null
  setsid bash /usr/local/bin/relay-push.sh > /proc/1/fd/1 2>&1 < /dev/null &
  rmdir /tmp/heal.lock 2>/dev/null
fi
CDR
  chmod 700 /usr/local/bin/relay-cdr.sh
fi
CDRHOOK=""; [ -x /usr/local/bin/relay-cdr.sh ] && CDRHOOK="<action application=\"set\" data=\"api_hangup_hook=system /usr/local/bin/relay-cdr.sh '\${uuid}|\${billsec}|\${destination_number}|\${hangup_cause}|\${caller_id_number}'\"/>"
rm -rf /etc/freeswitch; mkdir -p /etc/freeswitch /var/log/freeswitch
cat > /etc/freeswitch/freeswitch.xml <<XML
<?xml version="1.0"?>
<document type="freeswitch/xml">
<X-PRE-PROCESS cmd="set" data="local_ip_v4=\$\${local_ip_v4}"/>
<section name="configuration" description="cfg">
<configuration name="switch.conf" description="Core"><settings>
  <param name="rtp-start-port" value="16384"/><param name="rtp-end-port" value="32768"/>
  <param name="max-sessions" value="1000"/><param name="sessions-per-second" value="200"/>
  <param name="loglevel" value="info"/>
</settings></configuration>
<configuration name="modules.conf" description="Modules"><modules>
  <load module="mod_console"/><load module="mod_logfile"/><load module="mod_event_socket"/><load module="mod_sofia"/>
  <load module="mod_commands"/><load module="mod_dptools"/><load module="mod_dialplan_xml"/><load module="mod_hash"/><load module="mod_db"/><load module="mod_sndfile"/>
  <load module="mod_bcg729"/>
</modules></configuration>
<configuration name="console.conf"><mappings><map name="all" value="console,info,notice,warning,err,crit,alert"/></mappings><settings><param name="colorize" value="false"/></settings></configuration>
<configuration name="logfile.conf"><settings><param name="rotate-on-hup" value="true"/></settings><profiles><profile name="default"><settings><param name="logfile" value="/var/log/freeswitch/freeswitch.log"/></settings><mappings><map name="all" value="info,notice,warning,err,crit,alert"/></mappings></profile></profiles></configuration>
<configuration name="event_socket.conf"><settings><param name="listen-ip" value="127.0.0.1"/><param name="listen-port" value="8021"/><param name="password" value="$ESLPW"/></settings></configuration>
<configuration name="acl.conf"><network-lists>
  <list name="relay_clients" default="deny"><node type="allow" cidr="$CLIENT_IP/32"/></list>
</network-lists></configuration>
<configuration name="sofia.conf"><global_settings><param name="log-level" value="0"/></global_settings><profiles>
<profile name="external"><settings>
  <param name="sip-ip" value="\$\${local_ip_v4}"/><param name="rtp-ip" value="\$\${local_ip_v4}"/>
  <param name="ext-sip-ip" value="$PUBLIC_IP"/><param name="ext-rtp-ip" value="$PUBLIC_IP"/>
  <param name="sip-port" value="5060"/><param name="context" value="public"/><param name="dialplan" value="XML"/>
  <param name="apply-inbound-acl" value="relay_clients"/><param name="auth-calls" value="false"/>
  <param name="inbound-codec-prefs" value="G729,PCMA,PCMU"/><param name="outbound-codec-prefs" value="PCMA,PCMU"/>
  <param name="inbound-codec-negotiation" value="generous"/><param name="inbound-late-negotiation" value="true"/><param name="rtp-timeout-sec" value="60"/><param name="rtp-hold-timeout-sec" value="120"/>
  <param name="disable-register" value="true"/><param name="manage-presence" value="false"/><param name="nonce-ttl" value="60"/>
</settings></profile>
<profile name="pbx"><gateways>$GWXML</gateways><settings>
  <param name="sip-ip" value="\$\${local_ip_v4}"/><param name="rtp-ip" value="\$\${local_ip_v4}"/>
  <param name="ext-sip-ip" value="$PUBLIC_IP"/><param name="ext-rtp-ip" value="$PUBLIC_IP"/>
  <param name="sip-port" value="5070"/><param name="context" value="pbxin"/><param name="dialplan" value="XML"/>
  <param name="apply-inbound-acl" value="relay_clients"/><param name="disable-register" value="true"/><param name="manage-presence" value="false"/>
  <param name="inbound-late-negotiation" value="true"/><param name="inbound-codec-negotiation" value="generous"/>
</settings></profile></profiles></configuration>
</section>
<section name="dialplan" description="Dialplan"><context name="public">
  <extension name="client_in">
    <condition field="network_addr" expression="^$IPRE\$"/>
    <condition field="destination_number" expression="^${PREFIX%1}(?:10)?(1[3-9]\\d{8})\$">
      <action application="limit" data="hash relay_in $CLIENT_IP $MAX_CALLS !USER_BUSY"/>
      <action application="log" data="NOTICE RELAY-HIT from \${network_addr} to \${destination_number} -> \$1"/>
      $CDRHOOK
      $CALLACTS
    </condition>
  </extension>
  <extension name="deny"><condition field="destination_number" expression=".*"><action application="respond" data="403"/></condition></extension>
</context></section>
</document>
XML
echo "relay ready. MODE=$MODE client=$CLIENT_IP prefix=$PREFIX max=$MAX_CALLS public=$PUBLIC_IP"
# Live-call snapshot: every 3 s push the relay's channel list to the admin panel (Call Clients live board + running-minute count).
# Three independent safeguards keep it alive: (1) the loops never exit when a `sleep` fails (a `while sleep` loop ends for good on one failed fork),
# (2) a watchdog restarts the push when it is stale > 45 s, (3) the hangup hook restarts it when it has been silent for 5 minutes.
if [ -n "$RELAY_TOKEN" ]; then
  RELAY_LIVE_URL="${RELAY_LIVE_URL:-${RELAY_CONFIG_URL%relay-config}relay-live}"
  mkfifo /tmp/nap 2>/dev/null
  cat > /usr/local/bin/relay-push.sh <<PUSH
#!/bin/bash
# Singleton: a new start replaces the previous loop. Writes /tmp/live.ok after every successful push.
nap() { sleep "\$1" 2>/dev/null || read -r -t "\$1" _ <> /tmp/nap || true; }
OLD=\$(cat /tmp/push.pid 2>/dev/null); echo \$\$ > /tmp/push.pid
case "\$OLD" in ""|*[!0-9]*) ;; *) [ "\$OLD" != "\$\$" ] && kill "\$OLD" 2>/dev/null;; esac
K=0; nap 25
while :; do
  nap 3
  [ "\$(cat /tmp/push.pid 2>/dev/null)" = "\$\$" ] || exit 0
  if timeout -k 2 5 fs_cli -p "$ESLPW" -x 'show channels as json' > /tmp/live.json 2>/tmp/live.err && [ -s /tmp/live.json ]; then
    if wget -q -t 1 -T 5 -O /dev/null --header="Authorization: Bearer $RELAY_TOKEN" --header="Content-Type: application/json" --post-file=/tmp/live.json "$RELAY_LIVE_URL"; then date +%s > /tmp/live.ok; else echo "live push failed (wget to $RELAY_LIVE_URL)"; fi
  else echo "live push: fs_cli show channels failed: \$(head -c 120 /tmp/live.err 2>/dev/null)"; fi
  K=\$((K+1)); if [ \$((K%5)) -eq 1 ]; then timeout -k 2 5 fs_cli -p "$ESLPW" -x 'sofia status gateway' > /tmp/gw.txt 2>/dev/null; [ -s /tmp/gw.txt ] && wget -q -t 1 -T 5 -O /dev/null --header="Authorization: Bearer $RELAY_TOKEN" --header="Content-Type: text/plain" --post-file=/tmp/gw.txt "${RELAY_LIVE_URL%relay-live}relay-gw"; fi
done
PUSH
  chmod 700 /usr/local/bin/relay-push.sh
  date +%s > /tmp/live.ok
  bash /usr/local/bin/relay-push.sh &
  ( nap() { sleep "$1" 2>/dev/null || read -r -t "$1" _ <> /tmp/nap || true; }
    nap 60; while :; do
      nap 15
      NOW=$(date +%s); LAST=$(cat /tmp/live.ok 2>/dev/null)
      case "$NOW" in ""|*[!0-9]*) continue;; esac
      case "$LAST" in ""|*[!0-9]*) LAST=0;; esac
      if [ $((NOW-LAST)) -gt 45 ]; then
        echo "watchdog: live push stale for $((NOW-LAST)) s - restarting push loop"
        date +%s > /tmp/live.ok
        bash /usr/local/bin/relay-push.sh &
      fi
    done ) &
fi
exec freeswitch -nonat -nf -c -conf /etc/freeswitch -log /var/log/freeswitch -db /var/lib/freeswitch/db -run /var/run/freeswitch
