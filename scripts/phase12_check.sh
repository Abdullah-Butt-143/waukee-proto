#!/bin/bash
# Phase 12 check. Run inside WSL: bash scripts/phase12_check.sh
cd ~/waukee-proto
base=https://waukee-proto.ddev.site
yn() { [ "$1" -gt 0 ] && echo yes || echo NO; }

ldjson() { # extract JSON-LD blocks from a file
  python3 - "$1" <<'PY'
import re, sys, json
html = open(sys.argv[1], encoding="utf8").read()
for m in re.findall(r'<script type="application/ld\+json">(.*?)</script>', html, re.S):
    try:
        d = json.loads(m)
    except Exception as e:
        print("INVALID JSON-LD:", e); continue
    items = d.get("@graph", [d])
    for i in items:
        print(json.dumps({k: i.get(k) for k in ("@type", "name", "headline", "startDate", "endDate", "datePublished", "url", "location", "image", "publisher") if i.get(k)}))
PY
}

echo "== Home page =="
curl -sk $base/ -o /tmp/h.html
printf '  %-50s %s\n' '<title>' "$(grep -o '<title>[^<]*</title>' /tmp/h.html | head -1)"
printf '  %-50s %s\n' 'meta description present' "$(yn $(grep -c 'name="description"' /tmp/h.html))"
printf '  %-50s %s\n' 'canonical link present' "$(yn $(grep -c 'rel="canonical"' /tmp/h.html))"
printf '  %-50s %s\n' 'Open Graph og:title present' "$(yn $(grep -c 'property="og:title"' /tmp/h.html))"
printf '  %-50s %s\n' 'Twitter card present' "$(yn $(grep -c 'name="twitter:card"' /tmp/h.html))"
echo "  JSON-LD:"; ldjson /tmp/h.html | sed 's/^/    /'

echo "== Event page =="
path=$(curl -sk $base/events | grep -o 'href="/events/[a-z0-9-]*"' | head -1 | sed 's/href="//;s/"//')
echo "  $path"
curl -sk "$base$path" -o /tmp/e.html -w '  HTTP %{http_code}\n'
echo "  JSON-LD:"; ldjson /tmp/e.html | sed 's/^/    /'

echo "== News page =="
path=$(curl -sk $base/community/news | grep -o 'href="/news/[a-z0-9-]*"' | head -1 | sed 's/href="//;s/"//')
echo "  $path"
curl -sk "$base$path" -o /tmp/n.html -w '  HTTP %{http_code}\n'
printf '  %-50s %s\n' 'og:image present' "$(yn $(grep -c 'property="og:image"' /tmp/n.html))"
echo "  JSON-LD:"; ldjson /tmp/n.html | sed 's/^/    /'

echo "== sitemap.xml =="
curl -sk $base/sitemap.xml -o /tmp/s.xml -w '  HTTP %{http_code}\n'
printf '  %-50s %s\n' 'URLs listed' "$(grep -c '<loc>' /tmp/s.xml)"
printf '  %-50s %s\n' 'contains /families and /calendar' "$(yn $(grep -c '/families\|/calendar' /tmp/s.xml))"
printf '  %-50s %s\n' 'contains news and events' "$(yn $(grep -c '/news/\|/events/' /tmp/s.xml))"
printf '  %-50s %s\n' 'excludes /intranet, /staff/, /documents/' "$([ $(grep -c '/intranet\|/staff/\|/documents/' /tmp/s.xml) -eq 0 ] && echo yes || echo 'NO - LEAK')"

echo "== Editoria11y checker (loads for editors only) =="
login() { # $1 user -> cookie jar /tmp/jar_$1
  rm -f /tmp/jar_$1
  curl -sk -c /tmp/jar_$1 $base/user/login -o /tmp/login.html
  bid=$(grep -o 'name="form_build_id" value="[^"]*"' /tmp/login.html | head -1 | sed 's/.*value="//;s/"//')
  curl -sk -b /tmp/jar_$1 -c /tmp/jar_$1 -o /dev/null -d "name=$1&pass=Demo%232026&form_build_id=$bid&form_id=user_login_form&op=Log+in" $base/user/login
}
for u in anonymous school.editor comms.publisher teacher.staff; do
  if [ "$u" = anonymous ]; then
    curl -sk $base/families -o /tmp/f.html
  else
    login $u
    curl -sk -b /tmp/jar_$u $base/families -o /tmp/f.html
  fi
  printf '  %-18s %s\n' "$u" "$([ $(grep -ci 'editoria11y-drupal.js' /tmp/f.html) -gt 0 ] && echo 'checker loaded' || echo 'no checker')"
done
rm -f /tmp/jar_*
