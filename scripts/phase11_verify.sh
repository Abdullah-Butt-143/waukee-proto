#!/bin/bash
# Phase 11 verification: switches the emergency alert on and off (as separate
# steps, like an admin saving the form) and checks what an anonymous visitor
# gets on many pages. Leaves the alert INACTIVE.
# Run inside WSL: bash scripts/phase11_verify.sh
cd ~/waukee-proto
base=https://waukee-proto.ddev.site
pages="/ /families /events /calendar /lunch-menus /schools/sample-elementary /schools/sample-high-school /community/facility-rental /staff-directory"

set_alert() {
  ddev drush ev '$s=\Drupal::entityTypeManager()->getStorage("block_content"); $a=reset($s->loadByProperties(["info"=>"Site emergency alert"])); $a->set("field_active",'"$1"')->save();' > /dev/null
}
sweep() { # $1 = expected (1 visible, 0 hidden)
  bad=""
  for p in $pages; do
    n=$(curl -sk "$base$p" | grep -c 'wk-emergency-alert')
    if [ "$1" = "1" ] && [ "$n" -eq 0 ]; then bad="$bad $p"; fi
    if [ "$1" = "0" ] && [ "$n" -gt 0 ]; then bad="$bad $p"; fi
  done
  [ -z "$bad" ] && echo yes || echo "NO:$bad"
}

set_alert 0
printf '%-62s %s\n' "Inactive: no banner on any page" "$(sweep 0)"
set_alert 1
printf '%-62s %s\n' "Active: banner on all $(echo $pages | wc -w) pages (district + both schools)" "$(sweep 1)"
curl -sk $base/ -o /tmp/h.html
printf '%-62s %s\n' "Banner is above the header (full-width alert region)" "$(awk 'BEGIN{a=0;h=0} /wk-emergency-alert/ && !a {a=NR} /id="header"/ && !h {h=NR} END{print (a>0 && a<h) ? "yes" : "NO"}' /tmp/h.html)"
printf '%-62s %s\n' "Banner has role=alert and a link to /closings" "$(grep -q 'role="alert"' /tmp/h.html && grep -q 'href="/closings"' /tmp/h.html && echo yes || echo NO)"
set_alert 0
printf '%-62s %s\n' "Deactivated: banner gone everywhere" "$(sweep 0)"
echo "Alert left INACTIVE."
