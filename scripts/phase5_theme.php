<?php

/**
 * @file
 * Phase 5: enable the waukee_theme sub-theme, create the audience and quick
 * link menus, place blocks, and make it the default theme.
 *
 * Theme code lives in web/themes/custom/waukee_theme. Re-runnable.
 * Run: ddev drush php:script scripts/phase5_theme.php
 */

use Drupal\block\Entity\Block;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;

$theme = 'waukee_theme';

// ---------------------------------------------------------------------------
// 1. Install the theme.
// ---------------------------------------------------------------------------
if (!\Drupal::service('theme_handler')->themeExists($theme)) {
  \Drupal::service('theme_installer')->install([$theme]);
  echo "Installed theme $theme\n";
}
drupal_flush_all_caches();

// ---------------------------------------------------------------------------
// 2. Menus + links.
//    Targets are placeholders: the pages/views behind them arrive in later
//    phases (calendar, jobs, sample pages). Staff login is real.
// ---------------------------------------------------------------------------
$menus = [
  'audience' => [
    'label' => 'Audience links',
    'description' => 'Header audience navigation.',
    'links' => [
      'Families' => '/families',
      'Prospective Families' => '/prospective-families',
      'Students' => '/students',
      'Careers' => '/careers',
      'Community' => '/community',
      'Staff login' => '/user/login',
    ],
  ],
  'quick-links' => [
    'label' => 'Quick links',
    'description' => 'Quick links bar under the header.',
    'links' => [
      'Calendar' => '/calendar',
      'Menus' => '/lunch-menus',
      'Enrollment' => '/enrollment',
      'Jobs' => '/careers/jobs',
      'Transportation' => '/transportation',
      'Closings' => '/closings',
    ],
  ],
];
foreach ($menus as $id => $def) {
  if (!Menu::load($id)) {
    Menu::create(['id' => $id, 'label' => $def['label'], 'description' => $def['description']])->save();
    echo "Created menu $id\n";
  }
  $weight = 0;
  foreach ($def['links'] as $title => $path) {
    $existing = \Drupal::entityTypeManager()->getStorage('menu_link_content')
      ->loadByProperties(['menu_name' => $id, 'title' => $title]);
    if (!$existing) {
      MenuLinkContent::create([
        'menu_name' => $id,
        'title' => $title,
        'link' => ['uri' => 'internal:' . $path],
        'weight' => $weight,
        'expanded' => FALSE,
      ])->save();
      echo "  link: $title -> $path\n";
    }
    $weight++;
  }
}

// ---------------------------------------------------------------------------
// 3. Blocks: clone Olivero's placement (except its main menu), then add ours.
// ---------------------------------------------------------------------------
foreach (Block::loadMultiple() as $block) {
  if ($block->getTheme() !== 'olivero' || $block->id() === 'olivero_main_menu') {
    continue;
  }
  $new_id = preg_replace('/^olivero_/', $theme . '_', $block->id());
  if (Block::load($new_id)) {
    continue;
  }
  $clone = $block->createDuplicate();
  $clone->set('id', $new_id);
  $clone->setTheme($theme);
  // Dependencies are rebuilt on save.
  $clone->save();
  echo "  block $new_id ({$clone->getRegion()})\n";
}

// The audience links replace the main menu in the header.
if ($main = Block::load($theme . '_main_menu')) {
  $main->delete();
  echo "  removed {$theme}_main_menu (replaced by audience links)\n";
}

$new_blocks = [
  [
    'id' => 'waukee_theme_audience_links',
    'plugin' => 'system_menu_block:audience',
    'region' => 'primary_menu',
    'label' => 'Audience links',
    'label_display' => FALSE,
    'settings' => ['level' => 1, 'depth' => 1, 'expand_all_items' => FALSE],
    'weight' => 0,
  ],
  [
    'id' => 'waukee_theme_quick_links',
    'plugin' => 'system_menu_block:quick-links',
    'region' => 'quick_links',
    'label' => 'Quick links',
    // Visible label element is hidden visually by CSS; kept for screen readers.
    'label_display' => 'visible',
    'settings' => ['level' => 1, 'depth' => 1, 'expand_all_items' => FALSE],
    'weight' => 0,
  ],
];
foreach ($new_blocks as $b) {
  if (Block::load($b['id'])) {
    continue;
  }
  Block::create([
    'id' => $b['id'],
    'theme' => $theme,
    'region' => $b['region'],
    'plugin' => $b['plugin'],
    'weight' => $b['weight'],
    'status' => TRUE,
    'visibility' => [],
    'settings' => [
      'id' => $b['plugin'],
      'label' => $b['label'],
      'label_display' => $b['label_display'],
      'provider' => 'system',
    ] + $b['settings'],
  ])->save();
  echo "  block {$b['id']} ({$b['region']})\n";
}

// ---------------------------------------------------------------------------
// 4. Default theme (admin theme stays Gin).
// ---------------------------------------------------------------------------
\Drupal::configFactory()->getEditable('system.theme')->set('default', $theme)->save();
echo "Default theme: " . \Drupal::config('system.theme')->get('default') . "; admin: " . \Drupal::config('system.theme')->get('admin') . "\n";
