<?php

/**
 * @file
 * Phase 2b: restrict the "Staff" audience term with permissions_by_term.
 *
 * Nodes tagged with a restricted term get real node access grants, which the
 * Search API "Content access" processor honours. Term permissions live in
 * database tables (not config), so this script is the reproducible source.
 *
 * Re-runnable. Run: ddev drush php:script scripts/phase2b_term_access.php
 * Then re-run scripts/phase2_search_access_spike.php to reindex and re-test.
 */

$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'audience', 'name' => 'Staff']);
$term = reset($terms);
if (!$term) {
  throw new \RuntimeException('Audience term "Staff" not found. Run phase 1 first.');
}

$storage = \Drupal::service('permissions_by_term.access_storage');
$allowed_roles = ['staff', 'school_editor', 'publisher', 'district_admin', 'administrator'];
$existing = $storage->getRoleTermPermissionsByTid($term->id(), 'en');
$missing = array_diff($allowed_roles, $existing);
if ($missing) {
  $storage->addTermPermissionsByRoleIds($missing, $term->id(), 'en');
  echo "Staff term: granted roles " . implode(', ', $missing) . "\n";
}
else {
  echo "Staff term: roles already set (" . implode(', ', $existing) . ")\n";
}
