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
IPRE=$(printf '%s' "$CLIENT_IP" | sed 's/\./\\./g')
ESLPW=$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 20)
rm -rf /etc/freeswitch; mkdir -p /etc/freeswitch /var/log/freeswitch
cat > /etc/freeswitch/freeswitch.xml <<XML
<?xml version="1.0"?>
<document type="freeswitch/xml">
<X-PRE-PROCESS cmd="set" data="local_ip_v4=\$\${local_ip_v4}"/>
<section name="configuration" description="cfg">
<configuration name="switch.conf" description="Core"><settings>
  <param name="rtp-start-port" value="16384"/><param name="rtp-end-port" value="32768"/>
  <param name="max-sessions" value="1000"/><param name="sessions-per-second" value="30"/>
  <param name="loglevel" value="info"/>
</settings></configuration>
<configuration name="modules.conf" description="Modules"><modules>
  <load module="mod_console"/><load module="mod_logfile"/><load module="mod_event_socket"/><load module="mod_sofia"/>
  <load module="mod_commands"/><load module="mod_dptools"/><load module="mod_dialplan_xml"/><load module="mod_hash"/><load module="mod_db"/><load module="mod_sndfile"/>
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
  <param name="inbound-codec-prefs" value="PCMA,PCMU"/><param name="outbound-codec-prefs" value="PCMA,PCMU"/>
  <param name="inbound-codec-negotiation" value="generous"/><param name="rtp-timeout-sec" value="60"/><param name="rtp-hold-timeout-sec" value="120"/>
  <param name="disable-register" value="true"/><param name="manage-presence" value="false"/><param name="nonce-ttl" value="60"/>
</settings></profile></profiles></configuration>
</section>
<section name="dialplan" description="Dialplan"><context name="public">
  <extension name="client_in">
    <condition field="network_addr" expression="^$IPRE\$"/>
    <condition field="destination_number" expression="^${PREFIX}(0?1[3-9]\\d{8})\$">
      <action application="limit" data="hash relay_in $CLIENT_IP $MAX_CALLS !USER_BUSY"/>
      <action application="log" data="NOTICE RELAY-HIT from \${network_addr} to \${destination_number} -> \$1"/>
      <action application="ring_ready"/><action application="sleep" data="8000"/>
      <action application="hangup" data="NO_ROUTE_DESTINATION"/>
    </condition>
  </extension>
  <extension name="deny"><condition field="destination_number" expression=".*"><action application="respond" data="403"/></condition></extension>
</context></section>
</document>
XML
echo "relay ready. MODE=$MODE client=$CLIENT_IP prefix=$PREFIX max=$MAX_CALLS public=$PUBLIC_IP"
exec freeswitch -nonat -nf -c -conf /etc/freeswitch -log /var/log/freeswitch -db /var/lib/freeswitch/db -run /var/run/freeswitch
