<?php

/**
 * @file
 * Phase 19: feeds the real data scraped from waukeeschools.org
 * (scripts/migration/output/schools.json, produced by
 * scripts/migration/scrape-schools.mjs) into the "school" GROUP entities
 * from scripts/phase17_schools_data.php — the ones that actually power the
 * live /schools and /schools/{school} pages.
 *
 * This is the reconciliation of the two parallel data models: the scraped
 * data landed in "school" NODES via web/modules/custom/waukee_migrate
 * (a separate PoC); this script instead brings it into the live site.
 *
 * Updates, per school: phone, attendance phone, bell hours, early dismissal,
 * principal name + email, a principal-message paragraph built from the
 * scraped bio, and a leadership-team list built from the scraped staff
 * (excluding the principal, already shown separately) — then turns on
 * field_full_profile so every school's page shows these sections, not just
 * Waukee High's.
 *
 * Deliberately NOT synced: announcements. The scraper found the same two
 * district-wide news stories on every school's page (confirmed — this
 * site's template shows recent district news there, not school-specific
 * announcements), so copying them into every school's field_news_highlights
 * would just duplicate the same generic items 22 times.
 *
 * Re-runnable. Run: ddev drush php:script scripts/phase19_sync_scraped_data.php
 */

// Scraped slug => existing group slug, where the two differ.
const SLUG_ALIASES = [
  'natatorium-2' => 'district-natatorium',
  'timberline-middle-school' => 'timberline-middle',
];

$jsonPath = __DIR__ . '/migration/output/schools.json';
if (!file_exists($jsonPath)) {
  echo "Not found: $jsonPath\n";
  echo "Run the scraper first: node scripts/migration/scrape-schools.mjs\n";
  return;
}
$schools = json_decode(file_get_contents($jsonPath), TRUE);
if (!is_array($schools)) {
  echo "Could not parse $jsonPath as JSON.\n";
  return;
}

function wk19_html_escape(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$storage = \Drupal::entityTypeManager()->getStorage('group');
$updated = 0;
$skipped = [];

foreach ($schools as $scraped) {
  $slug = SLUG_ALIASES[$scraped['slug']] ?? $scraped['slug'];
  $groups = $storage->loadByProperties(['type' => 'school', 'field_slug' => $slug]);
  if (!$groups) {
    $skipped[] = "{$scraped['slug']} (no group with field_slug=$slug)";
    continue;
  }
  $group = reset($groups);

  if (!empty($scraped['phone'])) {
    $group->set('field_phone', $scraped['phone']);
  }
  if (!empty($scraped['attendancePhone'])) {
    $group->set('field_attendance_phone', $scraped['attendancePhone']);
  }
  if (!empty($scraped['hours'])) {
    $group->set('field_bell_hours', $scraped['hours']);
  }
  if (!empty($scraped['earlyDismissal'])) {
    $group->set('field_early_dismissal', $scraped['earlyDismissal']);
  }

  $principal = NULL;
  foreach ($scraped['staff'] ?? [] as $member) {
    if (strtolower(trim($member['role'])) === 'principal') {
      $principal = $member;
      break;
    }
  }
  if ($principal) {
    $group->set('field_principal', $principal['name']);
    if (!empty($principal['email'])) {
      $group->set('field_principal_email', $principal['email']);
    }
  }

  if (!empty($scraped['principalBio'])) {
    $group->set('field_principal_message', [
      'value' => '<p>' . wk19_html_escape($scraped['principalBio']) . '</p>',
      'format' => 'basic_html',
    ]);
  }

  $otherStaff = array_filter($scraped['staff'] ?? [], fn($m) => $m !== $principal);
  if ($otherStaff) {
    $items = array_map(
      fn($m) => '<li><strong>' . wk19_html_escape($m['name']) . '</strong> — ' . wk19_html_escape($m['role'])
        . ($m['email'] ? ' <a href="mailto:' . wk19_html_escape($m['email']) . '">' . wk19_html_escape($m['email']) . '</a>' : '')
        . '</li>',
      $otherStaff,
    );
    $group->set('field_leadership_team', [
      'value' => '<ul>' . implode('', $items) . '</ul>',
      'format' => 'basic_html',
    ]);
  }

  // Now that every school has a real bio and staff list, show those
  // sections on all of them, not just Waukee High.
  if ($principal || $otherStaff) {
    $group->set('field_full_profile', TRUE);
  }

  $group->save();
  $updated++;
  echo "  synced: {$scraped['name']} ($slug)\n";
}

drupal_flush_all_caches();
echo "Phase 19 complete: $updated school(s) updated.\n";
if ($skipped) {
  echo "Skipped (no matching group — check SLUG_ALIASES or phase17 data):\n";
  foreach ($skipped as $s) {
    echo "  $s\n";
  }
}
