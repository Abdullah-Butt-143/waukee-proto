<?php

/**
 * @file
 * Phase 4: editorial workflow (Draft -> Review -> Published), scheduler, diff.
 *
 * Re-runnable. Run: ddev drush php:script scripts/phase4_workflow.php
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\workflows\Entity\Workflow;

$bundles = array_keys(NodeType::loadMultiple());

// ---------------------------------------------------------------------------
// 1. Workflow.
// ---------------------------------------------------------------------------
// content_moderation ships a default "editorial" workflow (Draft/Published/
// Archived). Reuse its id and replace states + transitions with ours.
$workflow = Workflow::load('editorial');
if (!$workflow) {
  $workflow = Workflow::create(['id' => 'editorial', 'label' => 'Editorial', 'type' => 'content_moderation']);
}
$workflow->set('label', 'Editorial');
$settings = $workflow->get('type_settings');
$settings['states'] = [
  'draft' => ['label' => 'Draft', 'weight' => 0, 'published' => FALSE, 'default_revision' => FALSE],
  'review' => ['label' => 'In review', 'weight' => 1, 'published' => FALSE, 'default_revision' => FALSE],
  'published' => ['label' => 'Published', 'weight' => 2, 'published' => TRUE, 'default_revision' => TRUE],
];
$settings['transitions'] = [
  'create_new_draft' => ['label' => 'Save as draft', 'from' => ['draft', 'review', 'published'], 'to' => 'draft', 'weight' => 0],
  'submit_for_review' => ['label' => 'Submit for review', 'from' => ['draft', 'review'], 'to' => 'review', 'weight' => 1],
  'send_back' => ['label' => 'Send back to draft', 'from' => ['review'], 'to' => 'draft', 'weight' => 2],
  'publish' => ['label' => 'Publish', 'from' => ['draft', 'review', 'published'], 'to' => 'published', 'weight' => 3],
];
$settings['default_moderation_state'] = 'draft';
$settings['entity_types']['node'] = $bundles;
$workflow->set('type_settings', $settings);
$workflow->save();
echo "Workflow editorial: draft -> review -> published on " . implode(', ', $bundles) . "\n";

// ---------------------------------------------------------------------------
// 2. Scheduler on every content type.
// ---------------------------------------------------------------------------
foreach ($bundles as $b) {
  $type = NodeType::load($b);
  $type->setThirdPartySetting('scheduler', 'publish_enable', TRUE);
  $type->setThirdPartySetting('scheduler', 'unpublish_enable', TRUE);
  $type->setThirdPartySetting('scheduler', 'publish_past_date', 'schedule');
  $type->setThirdPartySetting('scheduler', 'publish_touch', FALSE);
  $type->setThirdPartySetting('scheduler', 'fields_display_mode', 'vertical_tab');
  $type->save();

  $form = EntityFormDisplay::load("node.$b.default");
  if ($form) {
    $form->setComponent('publish_on', ['type' => 'datetime_timestamp_no_default', 'weight' => 30, 'settings' => []]);
    $form->setComponent('unpublish_on', ['type' => 'datetime_timestamp_no_default', 'weight' => 31, 'settings' => []]);
    $form->setComponent('publish_state', ['type' => 'scheduler_moderation', 'weight' => 32, 'settings' => []]);
    $form->setComponent('unpublish_state', ['type' => 'scheduler_moderation', 'weight' => 33, 'settings' => []]);
    $form->setComponent('moderation_state', ['type' => 'moderation_state_default', 'weight' => 100, 'settings' => []]);
    $form->save();
  }
}
echo "Scheduler + moderation widget enabled on: " . implode(', ', $bundles) . "\n";

// ---------------------------------------------------------------------------
// 3. Permissions.
// ---------------------------------------------------------------------------
$known = array_keys(\Drupal::service('user.permissions')->getPermissions());
$grant = function (string $rid, array $perms) use ($known) {
  $role = Role::load($rid);
  $ok = array_intersect($perms, $known);
  foreach ($ok as $p) {
    $role->grantPermission($p);
  }
  $role->save();
  if ($skipped = array_diff($perms, $known)) {
    echo "  (unknown permissions skipped for $rid: " . implode(', ', $skipped) . ")\n";
  }
};

// School editors: save drafts and submit for review only (no publish).
$grant('school_editor', [
  'use editorial transition create_new_draft',
  'use editorial transition submit_for_review',
  'view latest version',
  'view all revisions',
]);
// Publishers and district admins: every transition, scheduling, diffs.
foreach (['publisher', 'district_admin'] as $rid) {
  $grant($rid, [
    'use editorial transition create_new_draft',
    'use editorial transition submit_for_review',
    'use editorial transition send_back',
    'use editorial transition publish',
    'view latest version',
    'view any unpublished content',
    'view all revisions',
    'schedule publishing of nodes',
    'view scheduled content',
  ]);
}
// Revision permissions per content type for people who review.
foreach (['school_editor', 'publisher', 'district_admin'] as $rid) {
  $perms = [];
  foreach ($bundles as $b) {
    $perms[] = "view $b revisions";
  }
  $grant($rid, $perms);
}
$grant('publisher', array_map(fn($b) => "revert $b revisions", $bundles));
$grant('district_admin', array_map(fn($b) => "revert $b revisions", $bundles));

echo "Phase 4 script complete.\n";
