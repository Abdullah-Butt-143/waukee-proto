<?php

/**
 * @file
 * Removes the Admin page from school editors.
 *
 * Idempotent. Revokes "access administration pages" from school_editor so the
 * account bar no longer shows the Admin button and /admin gives Access denied.
 * Editors keep Content, Add page and Add news. Run once with:
 *   ddev drush php:script scripts/phase27_editor_no_admin.php
 */

use Drupal\user\Entity\Role;

$role = Role::load('school_editor');
if ($role && $role->hasPermission('access administration pages')) {
  $role->revokePermission('access administration pages')->save();
  print "Revoked 'access administration pages' from school_editor.\n";
}
else {
  print "school_editor already has no admin page access.\n";
}
