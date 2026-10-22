<?php

/**
 * @file
 * Phase 3: groups.
 *
 * - Group types "school" and "intranet", with gnode plugins per content type.
 * - Groups: Sample Elementary, Sample High School (school); Staff Intranet.
 * - Group roles (Group 3.x scopes): anonymous / outsider / member (synchronized
 *   to Drupal roles) plus an individual "school editor" role on school groups.
 * - Memberships: school.editor and teacher.staff.
 *
 * Re-runnable. Run: ddev drush php:script scripts/phase3_groups.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupType;
use Drupal\group\PermissionScopeInterface;

$etm = \Drupal::entityTypeManager();
$rel_storage = $etm->getStorage('group_relationship_type');

// ---------------------------------------------------------------------------
// 1. Group types + gnode plugins.
// ---------------------------------------------------------------------------
$group_types = [
  'school' => [
    'label' => 'School',
    'description' => 'A school site. Content belongs to one school.',
    'plugins' => ['page', 'news', 'event', 'announcement', 'lunch_menu', 'staff_profile', 'document'],
  ],
  'intranet' => [
    'label' => 'Intranet',
    'description' => 'Staff-only space. Content is visible to members only.',
    'plugins' => ['page', 'news', 'announcement', 'staff_profile', 'document'],
  ],
];
foreach ($group_types as $id => $def) {
  if (!GroupType::load($id)) {
    GroupType::create(['id' => $id, 'label' => $def['label'], 'description' => $def['description'], 'creator_membership' => FALSE])->save();
    echo "Created group type $id\n";
  }
  $gt = GroupType::load($id);
  foreach ($def['plugins'] as $bundle) {
    $plugin_id = "group_node:$bundle";
    $rt_id = $rel_storage->getRelationshipTypeId($id, $plugin_id);
    if (!$rel_storage->load($rt_id)) {
      $rel = $rel_storage->createFromPlugin($gt, $plugin_id, [
        // A node belongs to at most one group of this type.
        'group_cardinality' => 0,
        'entity_cardinality' => 1,
      ]);
      $rel->save();
      echo "  $id: enabled $plugin_id\n";
    }
  }
}

// ---------------------------------------------------------------------------
// 2. Group roles + permissions.
// ---------------------------------------------------------------------------
$perm_handler = \Drupal::service('group.permissions');
$cache = [];
$valid = function (string $gtid) use ($perm_handler, &$cache) {
  if (!isset($cache[$gtid])) {
    $cache[$gtid] = array_keys($perm_handler->getPermissionsByGroupType(GroupType::load($gtid)));
  }
  return $cache[$gtid];
};

/** Create or update a group role, granting only permissions that exist. */
$ensure_role = function (string $id, string $label, string $scope, string $gtid, ?string $global_role, array $perms, int $weight = 0) use ($valid) {
  $role = GroupRole::load($id);
  if (!$role) {
    $role = GroupRole::create([
      'id' => $id,
      'label' => $label,
      'weight' => $weight,
      'scope' => $scope,
      'global_role' => $global_role,
      'group_type' => $gtid,
    ]);
    echo "  role $id\n";
  }
  $ok = array_values(array_intersect($perms, $valid($gtid)));
  $skipped = array_diff($perms, $valid($gtid));
  $role->set('permissions', array_values(array_unique(array_merge($role->getPermissions(), $ok))));
  $role->save();
  if ($skipped) {
    echo "    (unknown permissions skipped on $id: " . implode(', ', $skipped) . ")\n";
  }
};

$view = function (array $bundles, bool $rel = TRUE) {
  $p = [];
  foreach ($bundles as $b) {
    $p[] = "view group_node:$b entity";
    if ($rel) {
      $p[] = "view group_node:$b relationship";
    }
  }
  return $p;
};
$manage = function (array $bundles) {
  $p = [];
  foreach ($bundles as $b) {
    array_push($p,
      "create group_node:$b entity", "create group_node:$b relationship",
      "update own group_node:$b entity", "update any group_node:$b entity",
      "delete own group_node:$b entity",
      "view group_node:$b entity", "view group_node:$b relationship",
      "view unpublished group_node:$b entity");
  }
  return $p;
};

echo "Roles:\n";
// --- School groups ---
$public_bundles = ['page', 'news', 'event', 'lunch_menu'];
$ensure_role('school-anonymous', 'Anonymous', PermissionScopeInterface::OUTSIDER_ID, 'school', 'anonymous', array_merge(['view group'], $view($public_bundles)));
$ensure_role('school-outsider', 'Outsider', PermissionScopeInterface::OUTSIDER_ID, 'school', 'authenticated', array_merge(['view group'], $view(['page', 'news', 'event', 'lunch_menu', 'announcement', 'document'])));
$ensure_role('school-member', 'Member', PermissionScopeInterface::INSIDER_ID, 'school', 'authenticated', array_merge(['view group'], $view(['page', 'news', 'event', 'lunch_menu', 'announcement', 'document', 'staff_profile'])));
// Individual role: only members holding it can edit; it is per-school because
// it is assigned per group membership.
$ensure_role('school-editor', 'School editor', PermissionScopeInterface::INDIVIDUAL_ID, 'school', NULL,
  array_merge(['view group'], $manage(['page', 'news', 'event', 'announcement', 'lunch_menu'])), 10);

// --- Intranet: members only ---
$ensure_role('intranet-member', 'Member', PermissionScopeInterface::INSIDER_ID, 'intranet', 'authenticated',
  array_merge(['view group', 'view group_membership relationship'], $view(['page', 'news', 'announcement', 'staff_profile', 'document'])));
// Outsider / anonymous roles intentionally not created: no access.

// --- District-level staff manage every group without being members ---
$school_all = ['page', 'news', 'event', 'announcement', 'lunch_menu', 'staff_profile', 'document'];
$intranet_all = ['page', 'news', 'announcement', 'staff_profile', 'document'];
foreach (['publisher' => 'Publisher', 'district_admin' => 'District admin'] as $global => $label) {
  $ensure_role("school-$global", $label, PermissionScopeInterface::OUTSIDER_ID, 'school', $global,
    array_merge(['view group'], $manage($school_all), ['delete any group_node:news entity']), 20);
  $ensure_role("intranet-$global", $label, PermissionScopeInterface::OUTSIDER_ID, 'intranet', $global,
    array_merge(['view group', 'view group_membership relationship'], $manage($intranet_all)), 20);
}

// ---------------------------------------------------------------------------
// 3. Link school groups to the school taxonomy (used by views in later phases).
// ---------------------------------------------------------------------------
if (!FieldStorageConfig::loadByName('group', 'field_school')) {
  FieldStorageConfig::create([
    'field_name' => 'field_school',
    'entity_type' => 'group',
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'taxonomy_term'],
  ])->save();
}
if (!FieldConfig::loadByName('group', 'school', 'field_school')) {
  FieldConfig::create([
    'field_name' => 'field_school',
    'entity_type' => 'group',
    'bundle' => 'school',
    'label' => 'School term',
    'settings' => ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => ['school' => 'school']]],
  ])->save();
  \Drupal::service('entity_display.repository')->getFormDisplay('group', 'school')
    ->setComponent('field_school', ['type' => 'options_select', 'weight' => 5])->save();
  \Drupal::service('entity_display.repository')->getViewDisplay('group', 'school')
    ->setComponent('field_school', ['type' => 'entity_reference_label', 'weight' => 5, 'label' => 'inline'])->save();
}

// ---------------------------------------------------------------------------
// 4. Groups.
// ---------------------------------------------------------------------------
$term_id = function (string $name) use ($etm) {
  $t = $etm->getStorage('taxonomy_term')->loadByProperties(['vid' => 'school', 'name' => $name]);
  return $t ? reset($t)->id() : NULL;
};
$get_group = function (string $type, string $label, array $extra = []) use ($etm) {
  $found = $etm->getStorage('group')->loadByProperties(['type' => $type, 'label' => $label]);
  if ($found) {
    return reset($found);
  }
  $g = Group::create(['type' => $type, 'label' => $label, 'uid' => 1] + $extra);
  $g->save();
  echo "Created group: $label ($type)\n";
  return $g;
};
$elementary = $get_group('school', 'Sample Elementary', ['field_school' => $term_id('Sample Elementary')]);
$high = $get_group('school', 'Sample High School', ['field_school' => $term_id('Sample High School')]);
$intranet = $get_group('intranet', 'Staff Intranet');

// ---------------------------------------------------------------------------
// 5. Memberships.
// ---------------------------------------------------------------------------
$editor = user_load_by_name('school.editor');
$teacher = user_load_by_name('teacher.staff');

$ensure_member = function (Group $group, $user, array $roles = []) {
  $membership = $group->getMember($user);
  if (!$membership) {
    $group->addMember($user, $roles ? ['group_roles' => $roles] : []);
    echo "  added {$user->getAccountName()} to {$group->label()}" . ($roles ? ' as ' . implode(',', $roles) : '') . "\n";
    return;
  }
  $have = array_keys($membership->getRoles(FALSE));
  $missing = array_diff($roles, $have);
  if ($missing) {
    $rel = $membership->getGroupRelationship();
    foreach ($missing as $r) {
      $rel->group_roles[] = $r;
    }
    $rel->save();
    echo "  gave {$user->getAccountName()} roles " . implode(',', $missing) . " in {$group->label()}\n";
  }
};
echo "Memberships:\n";
$ensure_member($elementary, $editor, ['school-editor']);   // edits Elementary only.
$ensure_member($intranet, $editor);
$ensure_member($elementary, $teacher);
$ensure_member($intranet, $teacher);

// Put existing sample documents where they belong, if not placed already.
echo "Done. Group types: " . implode(', ', array_keys(GroupType::loadMultiple())) . "; groups: " . count($etm->getStorage('group')->loadMultiple()) . "\n";
