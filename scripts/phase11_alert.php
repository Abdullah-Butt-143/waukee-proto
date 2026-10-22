<?php

/**
 * @file
 * Phase 11: emergency alert.
 *
 * - One reusable "Emergency alert" content block (message, link, Active box).
 * - The waukee_emergency_alert block (waukee_core) sits in the theme's
 *   full-width alert region and renders only while an alert is Active.
 * - Alerts are managed from the block library, not from page layouts.
 *
 * Re-runnable. Run: ddev drush php:script scripts/phase11_alert.php
 */

use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\user\Entity\Role;

\Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();

// 1. The reusable alert. Starts inactive.
$found = \Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties(['type' => 'emergency_alert', 'info' => 'Site emergency alert']);
if (!$found) {
  BlockContent::create([
    'type' => 'emergency_alert', 'info' => 'Site emergency alert', 'reusable' => TRUE,
    'field_message' => ['value' => '<p>Snow day: all Sample Community Schools are closed on Tuesday. Stay safe and check back for updates.</p>', 'format' => 'basic_html'],
    'field_link' => ['uri' => 'internal:/closings', 'title' => 'School closings'],
    'field_active' => 0,
  ])->save();
  echo "Created 'Site emergency alert' (inactive)\n";
}

// The Active checkbox is an editor control, not something visitors need to see.
$display = EntityViewDisplay::load('block_content.emergency_alert.default');
$display->removeComponent('field_active')->save();

// 2. Block in the alert region of the theme.
if (!Block::load('waukee_theme_emergency_alert')) {
  Block::create([
    'id' => 'waukee_theme_emergency_alert', 'theme' => 'waukee_theme', 'region' => 'alert', 'plugin' => 'waukee_emergency_alert',
    'weight' => -10, 'status' => TRUE, 'visibility' => [],
    'settings' => ['id' => 'waukee_emergency_alert', 'label' => 'Emergency alert', 'label_display' => FALSE, 'provider' => 'waukee_core'],
  ])->save();
  echo "Placed emergency alert block in the alert region\n";
}

// 3. Alerts are not a page component: remove from the Layout Builder allow-list.
$lb = LayoutBuilderEntityViewDisplay::load('node.page.default');
$restriction = $lb->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction');
$allowed = $restriction['allowlisted_blocks']['Inline blocks'] ?? [];
$restriction['allowlisted_blocks']['Inline blocks'] = array_values(array_diff($allowed, ['inline_block:emergency_alert']));
$lb->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', $restriction);
$lb->save();

// 4. Who may switch the alert on and off: district admins and publishers.
$known = array_keys(\Drupal::service('user.permissions')->getPermissions());
foreach (['district_admin', 'publisher'] as $rid) {
  $role = Role::load($rid);
  foreach (['access block library', 'create and edit custom blocks', 'administer block content'] as $p) {
    if (in_array($p, $known, TRUE)) {
      if ($p === 'administer block content' && $rid !== 'district_admin') {
        continue;
      }
      $role->grantPermission($p);
    }
    else {
      echo "  (unknown permission $p)\n";
    }
  }
  $role->save();
}
echo "Phase 11 script complete.\n";
