#!/bin/bash
# Re-runs the webform verification and lists the emails that reached Mailpit.
# Run inside WSL: bash scripts/phase9_mailcheck.sh
cd ~/waukee-proto
ddev drush php:script scripts/phase9_webforms.php 2>&1 | head -3
ddev exec 'curl -s -X DELETE localhost:8025/api/v1/messages' > /dev/null 2>&1
ddev drush php:script scripts/phase9_verify.php 2>&1 | grep -E 'Email not sent|accepted|rejected|request does'
echo "--- Mailpit (also viewable at https://waukee-proto.ddev.site:8025) ---"
ddev exec 'curl -s localhost:8025/api/v1/messages' | python3 -c '
import sys, json
d = json.load(sys.stdin)
print(d["total"], "messages")
for m in d["messages"]:
    print(" -", m["Subject"], "->", m["To"][0]["Address"])
'
