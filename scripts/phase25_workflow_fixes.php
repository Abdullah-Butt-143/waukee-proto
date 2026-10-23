<?php

/**
 * @file
 * Phase 25: editorial flow fixes. Re-runnable.
 *
 * 1. Alerts: Drupal 11 removed "create and edit custom blocks"; publishers and
 *    district admins get the per-type permissions for the Emergency alert type
 *    so they can switch the alert on and off (/admin/content/block).
 * 2. Rebuilds node access so unpublished pages/news show in /admin/content and
 *    /admin/content/moderated for reviewers (see waukee_core_node_grants()).
 *
 * Run: ddev drush php:script scripts/phase25_workflow_fixes.php
 *      ddev drush cache:rebuild
 */

use Drupal\user\Entity\Role;

foreach (['publisher', 'district_admin'] as $rid) {
  $role = Role::load($rid);
  if (!$role) {
    continue;
  }
  $role->revokePermission('create and edit custom blocks');
  foreach (['create emergency_alert block content', 'edit any emergency_alert block content'] as $perm) {
    $role->grantPermission($perm);
  }
  $role->save();
  echo "Alert permissions granted to $rid\n";
}
if ($role = Role::load('school_editor')) {
  $role->revokePermission('create and edit custom blocks')->save();
}

node_access_rebuild();
echo "Node access rebuilt.\n";
drupal_flush_all_caches();
echo "Done.\n";
