#!/bin/bash
# Phase 13b check: what each kind of visitor sees. Run inside WSL: bash scripts/phase13b_check.sh
cd ~/waukee-proto
base=https://waukee-proto.ddev.site
login() {
  rm -f /tmp/jar_$1
  curl -sk -c /tmp/jar_$1 $base/user/login -o /tmp/login.html
  bid=$(grep -o 'name="form_build_id" value="[^"]*"' /tmp/login.html | head -1 | sed 's/.*value="//;s/"//')
  curl -sk -b /tmp/jar_$1 -c /tmp/jar_$1 -o /dev/null -d "name=$1&pass=Demo%232026&form_build_id=$bid&form_id=user_login_form&op=Log+in" $base/user/login
}
get() { # user path -> /tmp/p.html ; prints HTTP code
  if [ "$1" = anonymous ]; then curl -sk "$base$2" -o /tmp/p.html -w '%{http_code}'
  else curl -sk -b /tmp/jar_$1 "$base$2" -o /tmp/p.html -w '%{http_code}'; fi
}
cnt() { grep -o "$1" /tmp/p.html | wc -l; }
users="anonymous teacher.staff school.editor comms.publisher"
for u in teacher.staff school.editor comms.publisher; do login $u; done

echo "== Staff directory: public page (/staff-directory) =="
for u in $users; do get $u /staff-directory > /dev/null
  printf '  %-16s public profiles listed: %-3s  staff-only names (Reese/Dana/Parker): %s\n' $u "$(cnt 'Taylor Teacher\|Jordan Ellis\|Casey Morgan\|Riley Chen\|Sam Okafor\|Morgan Patel\|Avery Brooks\|Quinn Rivera\|Drew Hamilton\|Jamie Nguyen\|Cameron Lopez\|Skyler Bennett')" "$(cnt 'Reese Carter\|Dana Kim\|Parker Wallace')"; done

echo "== Staff directory: intranet page (/intranet/staff-directory) =="
for u in $users; do code=$(get $u /intranet/staff-directory)
  printf '  %-16s HTTP %s  profiles: %s\n' $u $code "$(cnt 'Taylor Teacher\|Jordan Ellis\|Casey Morgan\|Riley Chen\|Sam Okafor\|Morgan Patel\|Avery Brooks\|Quinn Rivera\|Drew Hamilton\|Jamie Nguyen\|Cameron Lopez\|Skyler Bennett\|Reese Carter\|Dana Kim\|Parker Wallace')"; done
echo "  search 'jordan' (teacher.staff):  $(get teacher.staff '/intranet/staff-directory?search=jordan' >/dev/null; cnt 'Jordan Ellis') match(es); Dana Kim hidden: $([ $(cnt 'Dana Kim') -eq 0 ] && echo yes || echo NO)"

echo "== Announcements (/intranet/announcements) =="
for u in $users; do code=$(get $u /intranet/announcements)
  printf '  %-16s HTTP %s  shown: %-2s  expired one hidden: %s\n' $u $code "$(cnt 'picture day\|fire drill\|testing window\|Staff meeting moved\|Open enrollment for benefits')" "$([ $(cnt 'Parking lot repaving') -eq 0 ] && echo yes || echo NO)"; done
elem=$(ddev drush sqlq "select tid from taxonomy_term_field_data where vid='school' and name='Sample Elementary'" | grep -E "^[0-9]+$" | head -1)
get teacher.staff "/intranet/announcements?school=$elem" > /dev/null
echo "  school=Elementary filter: elementary items $(cnt 'picture day\|fire drill'), high school item $(cnt 'testing window')"

echo "== Document library (/intranet/documents): public / staff / restricted =="
for u in $users; do code=$(get $u /intranet/documents)
  printf '  %-16s HTTP %s  public: %-2s staff: %-2s restricted: %s\n' $u $code "$(cnt 'Board Policy Manual\|Enrollment Form (sample)\|School Calendar\|Board Meeting Agenda\|Student and Family Handbook')" "$(cnt 'Employee Handbook\|Leave Request\|Technology Acceptable')" "$(cnt 'Salary Schedule\|Crisis Response')"; done

echo "== Direct links to documents =="
for entry in "Student and Family Handbook:public" "Employee Handbook:staff" "Salary Schedule:restricted"; do
  title=${entry%%:*}; kind=${entry##*:}
  path=$(ddev drush sqlq "select a.alias from path_alias a join node_field_data n on a.path=concat('/node/',n.nid) where n.title like '$title%' limit 1" | grep '^/' | head -1)
  line="  $kind ($path):"
  for u in $users; do line="$line $u=$(get $u $path)"; done
  echo "$line"
done

echo "== Search =="
for q in handbook salary; do
  line="  '$q':"
  for u in $users; do get $u "/search?keys=$q" >/dev/null; line="$line $u=$(cnt 'wk-feed__item')"; done
  echo "$line"
done

echo "== Totals (anonymous) =="
get anonymous /events > /dev/null; echo "  /events list items: $(cnt 'views-row\|wk-feed__item')"
get anonymous /community/news > /dev/null; echo "  /community/news items: $(cnt 'wk-feed__item')  (feed shows latest 5)"
get anonymous /calendar > /dev/null; echo "  /calendar HTTP ok, events in calendar data: $(cnt 'Kindergarten round-up\|Homecoming parade\|Winter choir')"
rm -f /tmp/jar_*
