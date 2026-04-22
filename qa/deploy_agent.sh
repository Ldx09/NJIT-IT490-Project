#!/bin/bash
set -euo pipefail

MYAPP_BASE="${MYAPP_BASE:-/opt/myapp}"
WEB_PORT="${WEB_PORT:-8080}"
HEALTH_PATH="${HEALTH_PATH:-/health.php}"
REL="${MYAPP_BASE}/releases"
CUR="${MYAPP_BASE}/current"
VERFILE="${MYAPP_BASE}/current_version.txt"
# file that remembers last version you said was ok after manual testing
GOODVER="${MYAPP_BASE}/last_pass.txt"
LOG="${MYAPP_BASE}/logs/deploy.log"
WEB_UNIT="${WEB_UNIT:-myapp-web.service}"
LIS_UNIT="${LIS_UNIT:-myapp-listener.service}"
HEALTH_RETRIES="${HEALTH_RETRIES:-3}"

logline() {
  echo "[qa] $*"
  mkdir -p "${MYAPP_BASE}/logs" 2>/dev/null || true
  echo "$(date '+%Y-%m-%d %H:%M') $*" >>"$LOG" 2>/dev/null || true
}

bail() {
  echo "qa deploy: $*" >&2
  exit 1
}

unpack_bundle() {
  archive=$1
  dest=$2
  tmp=$(mktemp -d)
  tar -xzf "$archive" -C "$tmp"
  if [ -z "$(ls -A "$tmp" 2>/dev/null)" ]; then
    rm -rf "$tmp"
    bail "tar was empty"
  fi
  n=$(ls -1 "$tmp" | wc -l)
  if [ "$n" -eq 1 ]; then
    one=$(ls -1 "$tmp" | head -1)
    if [ -d "$tmp/$one" ]; then
      mkdir -p "$dest"
      cp -a "$tmp/$one"/. "$dest"/
    else
      bail "top of tarball wasn't a folder: $one"
    fi
  else
    mkdir -p "$dest"
    cp -a "$tmp"/. "$dest"/
  fi
  rm -rf "$tmp"
}

ping_health() {
  u="http://127.0.0.1:${WEB_PORT}${HEALTH_PATH}"
  tries=0
  while [ "$tries" -lt "$HEALTH_RETRIES" ]; do
    tries=$((tries + 1))
    if command -v curl >/dev/null 2>&1; then
      curl -sf --max-time 5 "$u" | grep -q . && return 0
    else
      command -v wget >/dev/null 2>&1 || bail "need curl or wget"
      wget -q -O- --timeout=5 "$u" | grep -q . && return 0
    fi
    sleep 1
  done
  return 1
}

stop_stuff() {
  systemctl stop "$LIS_UNIT" 2>/dev/null || true
  systemctl stop "$WEB_UNIT" 2>/dev/null || true
}

start_stuff() {
  if ! systemctl start "$WEB_UNIT"; then
    echo "couldnt start web unit" >&2
    exit 1
  fi
  systemctl start "$LIS_UNIT" 2>/dev/null || true
  sleep 1
}

# main actions (kept a bit copy-pastey on purpose) 

if [ "${1:-}" = "deploy" ]; then
  shift
  [ "${1:-}" ] && [ "${2:-}" ] || bail "deploy needs tarball and version"
  f=$1
  v=$2
  [ -f "$f" ] || bail "missing file: $f"
  mkdir -p "$REL" "${MYAPP_BASE}/logs" 2>/dev/null || true
  stop_stuff
  logline "putting $v live from $f"
  out="${REL}/${v}"
  rm -rf "$out"
  mkdir -p "$out"
  unpack_bundle "$f" "$out"
  if [ ! -d "$out/Webserver" ]; then
    logline "heads up: no Webserver/ — double check the bundle"
  fi
  ln -sfn "$out" "$CUR"
  printf "%s" "$v" > "$VERFILE"
  start_stuff
  if ! ping_health; then
    logline "health check blew it"
    bail "health check failed, url was http://127.0.0.1:${WEB_PORT}${HEALTH_PATH}"
  fi
  logline "ok deployed $v"
  exit 0
fi

if [ "${1:-}" = "rollback" ]; then
  shift
  [ "${1:-}" ] || bail "rollback <version>"
  v=$1
  tgt="${REL}/${v}"
  [ -d "$tgt" ] || bail "dont have a release called $v under $REL"
  mkdir -p "$REL" 2>/dev/null || true
  stop_stuff
  logline "rollback to $v"
  ln -sfn "$tgt" "$CUR"
  printf "%s" "$v" > "$VERFILE"
  start_stuff
  if ! ping_health; then
    bail "health still bad after rollback"
  fi
  logline "rolled back to $v"
  exit 0
fi

if [ "${1:-}" = "rollback_last_good" ]; then
  if [ ! -f "$GOODVER" ]; then
    bail "no good version saved yet (run mark_pass when something works)"
  fi
  good=$(tr -d '\n\r' < "$GOODVER")
  [ -n "$good" ] || bail "last good file is empty"
  logline "going back to last good: $good"
  "$0" rollback "$good"
  exit $?
fi

if [ "${1:-}" = "mark_pass" ]; then
  [ -f "$VERFILE" ] || bail "nothing deployed / no version file"
  curv=$(tr -d '\n\r' < "$VERFILE")
  printf "%s" "$curv" > "$GOODVER"
  logline "saved $curv as last good"
  echo "wrote $curv to last_pass.txt"
  exit 0
fi

if [ "${1:-}" = "mark_fail" ]; then
  [ -f "$VERFILE" ] || bail "nothing to mark fail"
  curv=$(tr -d '\n\r' < "$VERFILE")
  echo "$(date '+%F %T') fail $curv" >> "${MYAPP_BASE}/last_fail.log"
  logline "logged fail for $curv"
  exit 0
fi

if [ "${1:-}" = "status" ]; then
  mkdir -p "$REL" 2>/dev/null || true
  if [ -f "$VERFILE" ]; then
    echo "version: $(tr -d '\n\r' < "$VERFILE")"
  else
    echo "version: (none yet)"
  fi
  if [ -f "$GOODVER" ]; then
    echo "last good test: $(tr -d '\n\r' < "$GOODVER")"
  else
    echo "last good test: (none)"
  fi
  echo "current points to: $(readlink -f "$CUR" 2>/dev/null || echo '?')"
  if command -v systemctl >/dev/null; then
    echo "web: $(systemctl is-active "$WEB_UNIT" 2>/dev/null || echo '?')"
    echo "rabbit listener: $(systemctl is-active "$LIS_UNIT" 2>/dev/null || echo '?')"
  fi
  if ping_health 2>/dev/null; then
    echo "health: fine"
  else
    echo "health: nope"
  fi
  exit 0
fi

echo "usage: $0 deploy <tarball> <ver> | rollback <ver> | rollback_last_good | mark_pass | mark_fail | status" >&2
exit 1
