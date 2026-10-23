<?php

/**
 * @file
 * Phase 26: one "School" taxonomy term per school group, so every school shows
 * in the School boxes on Add news / Add event and in the /news filter (they
 * only listed the 3 sample terms before). Re-runnable; new school groups get
 * their term automatically (waukee_core_group_insert()).
 *
 * Run: ddev drush php:script scripts/phase26_school_terms.php
 */

$groups = \Drupal::entityTypeManager()->getStorage('group')->loadByProperties(['type' => 'school']);
foreach ($groups as $group) {
  waukee_core_ensure_school_term($group);
  echo '  ' . $group->label() . "\n";
}
$count = count(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'school']));
echo count($groups) . " school groups; $count School terms.\n";
drupal_flush_all_caches();
echo "Done.\n";
