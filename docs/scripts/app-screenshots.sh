#!/bin/bash
#
# Takes the app's screenshots for the documentation on a phone or an emulator, in English
# (public/screenshots/app/), German (app/de/) and Spanish (app/es/): signed in as the demo agent
# DemoFrog, cropped below the status bar and at half size, as WebP.
#
# Needs, before it runs:
#   - the demo agents in the local database (symfony console app:demo-agents);
#   - the web app on loopback with fake tokens, e.g. in web/:
#       APP_FAKE_GOOGLE_TOKENS=1 php -d variables_order=EGPCS -S 127.0.0.1:8001 -t public
#     (DATABASE_URL from `symfony var:export DATABASE_URL`), and `adb reverse tcp:8001 tcp:8001`;
#   - the debug app built with debug.fakeSignInEmail=demofrog@demo.example.com in
#     app/local.properties and its default server (not the live one) in its settings;
#   - adb (ADB, else on the PATH or in ~/Android/Sdk), python3 with Pillow, the Symfony CLI.
#
# With a phone and an emulator connected, ANDROID_SERIAL picks one (adb devices), e.g. the
# Pixel 6a emulator (the phone's screen). Its AVD asks for 6 GB, too much next to Gradle and the
# web app without swap; with the host GPU the emulator crashed, and its clock is UTC:
#   ~/Android/Sdk/emulator/emulator -avd Pixel_6a_API_35 -memory 2048 -no-snapshot-save \
#       -gpu swiftshader_indirect -timezone America/Guayaquil
#   ANDROID_SERIAL=emulator-5554 npm run screenshots:app
# Its colors follow its wallpaper (dynamic colors), so they differ a little from the phone's.
#
# Real users stay out of the agents list: the script makes them private while it runs and
# restores their settings when it ends. It also restores the app's language and the device's
# stay-awake setting. The upload result is DemoFrog's newest upload, removed and shared again.
#
#   npm run screenshots:app [-- en de es]
#
# The screenshots are cropped below the status bar, its height read from the device; STATUS_BAR
# (pixels) sets it instead.

set -euo pipefail

SCRIPTS=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
DOCS=$(dirname "$SCRIPTS")
WEB=$(dirname "$DOCS")/web
OUT=$DOCS/public/screenshots/app
APP=com.elkuku.agenttools
ADB=${ADB:-$(command -v adb || echo ~/Android/Sdk/platform-tools/adb)}
TMP=$(mktemp -d)
LANGUAGES=(en de es)
[ $# -gt 0 ] && LANGUAGES=("$@")

sql() { (cd "$WEB" && symfony console dbal:run-sql "$1"); }

# --- Phone helpers ---

# The visible elements as "x,y<TAB>text<TAB>content-desc", x and y their centre
texts() {
    "$ADB" shell uiautomator dump /sdcard/ui.xml >/dev/null
    "$ADB" exec-out cat /sdcard/ui.xml > "$TMP/ui.xml"
    python3 - "$TMP/ui.xml" <<'EOF'
import re, sys
for node in re.findall(r'<node [^>]*>', open(sys.argv[1]).read()):
    text = re.search(r' text="([^"]*)"', node).group(1)
    desc = re.search(r'content-desc="([^"]*)"', node).group(1)
    b = [int(v) for v in re.search(r'bounds="\[(\d+),(\d+)\]\[(\d+),(\d+)\]"', node).groups()]
    if text or desc:
        print(f'{(b[0] + b[2]) // 2},{(b[1] + b[3]) // 2}\t{text}\t{desc}')
EOF
}

# Taps the first element whose text or content-desc is $1; fails if there is none
tap() {
    local xy
    xy=$(texts | awk -F'\t' -v t="$1" '$2 == t || $3 == t { sub(",", " ", $1); print $1; exit }')
    [ -n "$xy" ] || { echo "Not on the screen: $1" >&2; return 1; }
    # shellcheck disable=SC2086
    "$ADB" shell input tap $xy
}

# Taps $1, a second time after a moment if a menu was still opening
tap_again() { tap "$1" 2>/dev/null || { sleep 2; tap "$1"; }; }

# The y of the first element whose text starts with $1
y_of() { texts | awk -F'\t' -v t="$1" 'index($2, t) == 1 { split($1, a, ","); print a[2]; exit }'; }

swipe() { "$ADB" shell input swipe 540 "$1" 540 "$2" "${3:-1500}"; sleep 2; }
back() { "$ADB" shell input keyevent BACK; sleep 1; }

# Saves the screen below the status bar at half size as $1.webp
shot() {
    "$ADB" exec-out screencap -p > "$TMP/screen.png"
    python3 - "$TMP/screen.png" "$DIR/$1.webp" "$STATUS_BAR" <<'EOF'
import sys
from PIL import Image
image = Image.open(sys.argv[1]).convert('RGB')
top = int(sys.argv[3])
width, height = image.size
image.crop((0, top, width, height)).resize((width // 2, (height - top) // 2), Image.LANCZOS) \
    .save(sys.argv[2], 'WEBP', quality=85, method=6)
EOF
    echo "$LANG_TAG/$1.webp"
}

restart() {
    "$ADB" shell am force-stop $APP
    "$ADB" shell am start -W -n $APP/.MainActivity >/dev/null
    sleep 5
}

# --- Checks ---

if ! "$ADB" get-state >/dev/null 2>&1; then
    echo "No device, or several: connect one, or set ANDROID_SERIAL to one of \`adb devices\`" >&2
    exit 1
fi
# The status bar's height, as Android lays it out: [0,0][width,height]; the largest, with the
# camera cutout (a window without it may come first)
STATUS_BAR=${STATUS_BAR:-$("$ADB" shell dumpsys window | grep -oE 'type=statusBars frame=\[0,0\]\[[0-9]+,[0-9]+\]' | grep -oE '[0-9]+\]$' | tr -d ']' | sort -n | tail -1)}
[ -n "$STATUS_BAR" ] || { echo "The status bar's height is unknown: set STATUS_BAR" >&2; exit 1; }
# The app's own language, restored at the end
APP_LOCALES=$("$ADB" shell cmd locale get-app-locales $APP | grep -oE '\[.*\]' | tr -d '[] ')
curl -sf -o /dev/null http://127.0.0.1:8001/api/docs || { echo "No web app on 127.0.0.1:8001 (see the top of this script)" >&2; exit 1; }
"$ADB" reverse --list | grep -q 'tcp:8001' || "$ADB" reverse tcp:8001 tcp:8001 >/dev/null
if "$ADB" shell run-as $APP cat shared_prefs/settings.xml 2>/dev/null | grep -q server_url; then
    echo "The app uses another server: choose \"Use default\" in its settings" >&2
    exit 1
fi

# --- DemoFrog's newest upload as Ingress share text ---

sql "select row_to_json(s)::text from agent_stat s join system_user u on u.id = s.owner_id
     where u.agent_name = 'DemoFrog' order by s.datetime desc limit 1" | grep -o '{.*}' > "$TMP/upload.json"
python3 - "$TMP/upload.json" "$DOCS/../app/app/src/main/java/com/elkuku/agenttools/data/IngressStats.kt" > "$TMP/upload.tsv" <<'EOF'
import json, re, sys
row = json.load(open(sys.argv[1]))
# The app's "Ingress header" to "agent_stat column" map, read backwards
columns = re.findall(r'"([^"]+)" to "([^"]+)"', open(sys.argv[2]).read().split('INGRESS_COLUMNS')[1].split('\n)\n')[0])
date, time = row['datetime'].split('T')
cells = [('Time Span', 'ALL TIME'), ('Agent Name', row['nickname']), ('Agent Faction', row['faction']),
         ('Date (yyyy-mm-dd)', date), ('Time (hh:mm:ss)', time)]
done = {'nickname', 'faction'}
for header, column in columns:
    if column not in done and row.get(column) is not None:
        done.add(column)
        cells.append((header, str(row[column])))
print('\t'.join(h for h, _ in cells))
print('\t'.join(v for _, v in cells))
EOF
UPLOAD_DATETIME=$(python3 -c "import json,sys; print(json.load(open(sys.argv[1]))['datetime'].replace('T', ' '))" "$TMP/upload.json")
"$ADB" push "$TMP/upload.tsv" /data/local/tmp/agenttools-upload.tsv >/dev/null

# --- Real users private while the script runs, everything restored when it ends ---

sql "select id || ' ' || visible_parts::text from system_user
     where agent_name not like 'Demo%' and visible_parts::text <> '[]'" \
    | grep -oE '^ *[0-9]+ \[.*\]' | sed 's/^ *//' > "$TMP/visible_parts" || true

restore() {
    while read -r id parts; do
        sql "update system_user set visible_parts = '$parts' where id = $id" >/dev/null
    done < "$TMP/visible_parts"
    "$ADB" shell cmd locale set-app-locales $APP --locales "$APP_LOCALES" || true
    "$ADB" shell svc power stayon false || true
    "$ADB" shell rm -f /data/local/tmp/agenttools-upload.tsv /sdcard/ui.xml || true
    rm -rf "$TMP"
}
trap restore EXIT

while read -r id _; do
    sql "update system_user set visible_parts = '[]' where id = $id" >/dev/null
done < "$TMP/visible_parts"
"$ADB" shell svc power stayon usb

# --- The screenshots ---

for LANG_TAG in "${LANGUAGES[@]}"; do
    case $LANG_TAG in
        en) SIGN_IN="Sign in"; CLOSE=Close; OPEN=Open; TIMELINE=Timeline; BANNERS="My banners"; MENU=Menu; AGENTS=Agents; SETTINGS=Settings; WELCOME=Welcome ;;
        de) SIGN_IN=Anmelden; CLOSE=Schließen; OPEN=Öffnen; TIMELINE=Zeitleiste; BANNERS="Meine Banner"; MENU=Menü; AGENTS=Agenten; SETTINGS=Einstellungen; WELCOME=Willkommen ;;
        es) SIGN_IN="Iniciar sesión"; CLOSE=Cerrar; OPEN=Abrir; TIMELINE="Línea de tiempo"; BANNERS="Mis banners"; MENU=Menú; AGENTS=Agentes; SETTINGS=Ajustes; WELCOME=Hola ;;
        *) echo "Unknown language: $LANG_TAG" >&2; exit 1 ;;
    esac
    DIR=$OUT; [ "$LANG_TAG" = en ] || DIR=$OUT/$LANG_TAG
    mkdir -p "$DIR"
    "$ADB" shell cmd locale set-app-locales $APP --locales "$LANG_TAG"

    # A fresh start of the app is signed out
    restart
    shot landing
    tap "$SIGN_IN"; sleep 6
    texts | grep -q "$WELCOME" || { echo "The fake sign-in as DemoFrog failed" >&2; exit 1; }
    shot home

    # The upload result: the newest upload removed and shared again from "Ingress"
    sql "delete from agent_stat where datetime = '$UPLOAD_DATETIME'
         and owner_id = (select id from system_user where agent_name = 'DemoFrog')" >/dev/null
    "$ADB" shell 'am start -W -a android.intent.action.SEND -t text/plain --es android.intent.extra.TEXT "$(cat /data/local/tmp/agenttools-upload.tsv)" -n '$APP'/.MainActivity' >/dev/null
    sleep 6
    shot import-result

    # My stats from Home; a fresh start drops the upload's "saved" card
    restart
    tap "$SIGN_IN"; sleep 6
    tap "$OPEN"; sleep 4
    shot my-stats
    tap "Trekker: Onyx"; sleep 2
    shot medal-details
    tap "$CLOSE"; sleep 1
    # To the end, then back up until the Dronenet line shows whole (a swipe loses ~45 px to the
    # touch slop before the page moves)
    swipe 2000 800; swipe 2000 800
    y=$(y_of Portal)
    [ -n "$y" ] && [ "$y" -lt 360 ] && swipe 1000 $((1000 + 367 - y + 45)) 2000
    shot my-stats-medals
    tap "$TIMELINE"; sleep 4
    shot timeline

    back; back; sleep 1
    "$ADB" shell input tap 1000 216; sleep 3     # the account menu: the profile picture
    tap_again "$BANNERS"; sleep 10              # the map's tiles
    shot banners

    back; sleep 1
    tap "$MENU"; sleep 2
    tap "$AGENTS"; sleep 4
    shot agents
    tap DemoKey; sleep 4
    shot agent-profile

    back; back; sleep 1
    "$ADB" shell input tap 1000 216; sleep 3
    tap_again "$SETTINGS"; sleep 3
    shot settings
done
