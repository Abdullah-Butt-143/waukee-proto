#!/bin/bash
# Fetch each sample page as an anonymous visitor and report status + a few markers.
# Run: bash scripts/phase13a_check.sh   (from ~/waukee-proto, inside WSL)
base=https://waukee-proto.ddev.site
for p in / /families /prospective-families /students /careers /community /enrollment /transportation /closings \
         /families/nutrition /families/health-services /prospective-families/why-us /prospective-families/schools \
         /prospective-families/enroll /students/activities /students/volunteer-hours /students/resources \
         /careers/benefits /careers/jobs /community/facility-rental /community/board-of-education /community/news \
         /schools/sample-elementary /schools/sample-high-school \
         /intranet/hr-resources /intranet/it-resources /intranet/announcements /staff-directory; do
  code=$(curl -sk -o /tmp/page.html -w '%{http_code}' "$base$p")
  marks=""
  grep -q 'wk-feed__item\|views-row' /tmp/page.html && marks="$marks feed"
  grep -q 'block-inline-blockhero' /tmp/page.html && marks="$marks hero"
  grep -q 'block-inline-blockcard-grid' /tmp/page.html && marks="$marks cards"
  grep -q 'block-inline-blockaudience-pathways' /tmp/page.html && marks="$marks pathways"
  grep -q 'quick-links-bar' /tmp/page.html && marks="$marks quickbar"
  printf '%-34s %s %s\n' "$p" "$code" "$marks"
done
