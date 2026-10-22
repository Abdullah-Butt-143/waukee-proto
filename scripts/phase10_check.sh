#!/bin/bash
# Phase 10 check (anonymous visitor). Run inside WSL: bash scripts/phase10_check.sh
cd ~/waukee-proto
base=https://waukee-proto.ddev.site
elem=$(ddev drush sqlq "select tid from taxonomy_term_field_data where vid='school' and name='Sample Elementary'" | grep -E "^[0-9]+$" | head -1)
high=$(ddev drush sqlq "select tid from taxonomy_term_field_data where vid='school' and name='Sample High School'" | grep -E "^[0-9]+$" | head -1)

has() { grep -c "$2" "$1" | awk -v l="$3" '{printf "  %-52s %s\n", l, ($1>0 ? "yes" : "NO")}'; }

echo "== /calendar =="
curl -sk -o /tmp/p.html -w '  HTTP %{http_code}\n' $base/calendar
has /tmp/p.html 'fullCalendarView' 'FullCalendar settings present'
has /tmp/p.html 'Back-to-school night' 'event "Back-to-school night" in calendar data'
has /tmp/p.html 'name="school"\|id="edit-school"' 'school filter present'

echo "== /events (all) =="
curl -sk -o /tmp/p.html -w '  HTTP %{http_code}\n' $base/events
for t in 'Back-to-school night' 'Fall concert' 'Elementary science fair'; do has /tmp/p.html "$t" "$t"; done

echo "== /events?school=Elementary (expect district + elementary, NOT high school) =="
curl -sk -o /tmp/p.html "$base/events?school=$elem"
has /tmp/p.html 'Back-to-school night' 'Back-to-school night (district)'
has /tmp/p.html 'Elementary science fair' 'Elementary science fair'
printf '  %-52s %s\n' 'Fall concert (high school only) hidden' "$(grep -c 'Fall concert' /tmp/p.html | awk '{print ($1==0 ? "yes" : "NO - LEAK")}')"

echo "== /events?school=High =="
curl -sk -o /tmp/p.html "$base/events?school=$high"
has /tmp/p.html 'Fall concert' 'Fall concert'
has /tmp/p.html 'Back-to-school night' 'Back-to-school night (district)'
printf '  %-52s %s\n' 'Elementary science fair hidden' "$(grep -c 'Elementary science fair' /tmp/p.html | awk '{print ($1==0 ? "yes" : "NO - LEAK")}')"

echo "== School pages (own feeds + district) =="
curl -sk -o /tmp/p.html -w '  /schools/sample-elementary HTTP %{http_code}\n' $base/schools/sample-elementary
has /tmp/p.html 'Sample Elementary reading night' 'Elementary news shown'
has /tmp/p.html 'Welcome back to the new school year' 'District news shown'
printf '  %-52s %s\n' 'High School robotics news hidden' "$(grep -c 'robotics team' /tmp/p.html | awk '{print ($1==0 ? "yes" : "NO - LEAK")}')"
curl -sk -o /tmp/p.html -w '  /schools/sample-high-school HTTP %{http_code}\n' $base/schools/sample-high-school
has /tmp/p.html 'robotics team' 'High School news shown'
has /tmp/p.html 'Welcome back to the new school year' 'District news shown'
printf '  %-52s %s\n' 'Elementary reading night hidden' "$(grep -c 'reading night' /tmp/p.html | awk '{print ($1==0 ? "yes" : "NO - LEAK")}')"

echo "== /lunch-menus =="
curl -sk -o /tmp/p.html -w '  HTTP %{http_code}\n' $base/lunch-menus
has /tmp/p.html 'Cheese pizza' 'Elementary menu shown'
has /tmp/p.html 'Chicken wrap' 'High School menu shown'
curl -sk -o /tmp/p.html "$base/lunch-menus?school=$elem"
printf '  %-52s %s\n' 'school filter hides High School menu' "$(grep -c 'Chicken wrap' /tmp/p.html | awk '{print ($1==0 ? "yes" : "NO")}')"

ddev drush watchdog:show --severity=Error --count=3 2>&1 | tail -4
