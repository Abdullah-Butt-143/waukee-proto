#!/bin/bash
# Phase 8 check: /search as different users. Run inside WSL: bash scripts/phase8_check.sh
cd ~/waukee-proto
base=https://waukee-proto.ddev.site
login() {
  rm -f /tmp/jar_$1
  curl -sk -c /tmp/jar_$1 $base/user/login -o /tmp/login.html
  bid=$(grep -o 'name="form_build_id" value="[^"]*"' /tmp/login.html | head -1 | sed 's/.*value="//;s/"//')
  curl -sk -b /tmp/jar_$1 -c /tmp/jar_$1 -o /dev/null -d "name=$1&pass=Demo%232026&form_build_id=$bid&form_id=user_login_form&op=Log+in" $base/user/login
}
search() { # $1 user (or anonymous) $2 query string
  if [ "$1" = anonymous ]; then curl -sk "$base/search?$2" -o /tmp/r.html
  else curl -sk -b /tmp/jar_$1 "$base/search?$2" -o /tmp/r.html; fi
}
show() { # $1 label (also prints the result titles)
  s=$(grep -c 'Staff Handbook' /tmp/r.html); f=$(grep -c 'Family Handbook' /tmp/r.html)
  printf '  %-18s Family Handbook: %-6s Staff Handbook: %-7s results: %s
' "$1" "$([ $f -gt 0 ] && echo shown || echo -)" "$([ $s -gt 0 ] && echo shown || echo hidden)" "$(grep -c 'wk-feed__item' /tmp/r.html)"
}
echo "== Search 'handbook' =="
search anonymous 'keys=handbook'; show "anonymous (public)"
for u in teacher.staff school.editor comms.publisher; do login $u; search $u 'keys=handbook'; show "$u"; done

echo "== Filters present on the page =="
search anonymous ''
printf '  %-40s %s\n' 'keyword box' "$([ $(grep -c 'name="keys"' /tmp/r.html) -gt 0 ] && echo yes || echo NO)"
printf '  %-40s %s\n' 'Content type drop-down' "$([ $(grep -c 'name="type"' /tmp/r.html) -gt 0 ] && echo yes || echo NO)"
printf '  %-40s %s\n' 'School drop-down' "$([ $(grep -c 'name="school"' /tmp/r.html) -gt 0 ] && echo yes || echo NO)"
echo "  type options: $(grep -o '<option value="[a-z_]*"' /tmp/r.html | sed 's/<option value="//;s/"//' | tr '\n' ' ')"

echo "== Filtering =="
elem=$(ddev drush sqlq "select tid from taxonomy_term_field_data where vid='school' and name='Sample Elementary'" | grep -E "^[0-9]+$" | head -1)
search anonymous 'keys=&type=event'; printf '  type=event:        events %s, news %s\n' "$(grep -c 'science fair' /tmp/r.html)" "$(grep -c 'reading night' /tmp/r.html)"
search anonymous "keys=&school=$elem"; printf '  school=Elementary: elementary items %s, high-school robotics %s\n' "$(grep -c 'reading night\|science fair' /tmp/r.html)" "$(grep -c 'robotics' /tmp/r.html)"
search anonymous 'keys=enrollment'; printf '  q=enrollment:      Enrollment page %s\n' "$(grep -c '>Enrollment<' /tmp/r.html)"
search anonymous 'keys=transportation'; printf '  q=transportation:  matches %s\n' "$(grep -c 'wk-feed__item' /tmp/r.html)"
rm -f /tmp/jar_*
ddev drush watchdog:show --severity=Error --count=2 2>&1 | tail -3
