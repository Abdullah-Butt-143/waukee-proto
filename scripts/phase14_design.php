<?php

/**
 * @file
 * Phase 14: design refresh (content/config side).
 *
 * The look itself lives in web/themes/custom/waukee_theme (templates + CSS).
 * This script adds what needs config/content:
 * - Footer: contact block + "Explore" and "Quick links" menus in footer_top.
 * - Homepage and school pages: news (2/3) beside events (1/3), with titles.
 *
 * Re-runnable. Run: ddev drush php:script scripts/phase14_design.php
 */

use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;

// ---------------------------------------------------------------------------
// 1. Footer blocks.
// ---------------------------------------------------------------------------
$found = \Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties(['info' => 'Footer contact']);
if (!$found) {
  $contact = BlockContent::create([
    'type' => 'basic', 'info' => 'Footer contact', 'reusable' => TRUE,
    'body' => ['format' => 'basic_html', 'value' =>
      '<p class="wk-footer__name">Sample Community Schools</p>'
      . '<p>100 Sample Street<br>Sample City, IA 50000</p>'
      . '<p>(515) 555-0100<br><a href="mailto:info@example.test">info@example.test</a></p>'
      . '<p><em>Prototype with sample content only.</em></p>'],
  ]);
  $contact->save();
  echo "Created footer contact block\n";
}
else {
  $contact = reset($found);
}

$blocks = [
  'waukee_theme_footer_contact' => ['plugin' => 'block_content:' . $contact->uuid(), 'weight' => 0, 'label' => 'Contact', 'label_display' => FALSE,
    'settings' => ['provider' => 'block_content', 'status' => TRUE, 'info' => '', 'view_mode' => 'default']],
  'waukee_theme_footer_explore' => ['plugin' => 'system_menu_block:audience', 'weight' => 1, 'label' => 'Explore', 'label_display' => TRUE,
    'settings' => ['provider' => 'system', 'level' => 1, 'depth' => 1, 'expand_all_items' => FALSE]],
  'waukee_theme_footer_quick' => ['plugin' => 'system_menu_block:quick-links', 'weight' => 2, 'label' => 'Quick links', 'label_display' => TRUE,
    'settings' => ['provider' => 'system', 'level' => 1, 'depth' => 1, 'expand_all_items' => FALSE]],
];
foreach ($blocks as $id => $b) {
  if (Block::load($id)) {
    continue;
  }
  Block::create([
    'id' => $id, 'theme' => 'waukee_theme', 'region' => 'footer_top', 'plugin' => $b['plugin'], 'weight' => $b['weight'],
    'status' => TRUE, 'visibility' => [],
    'settings' => ['id' => $b['plugin'], 'label' => $b['label'], 'label_display' => $b['label_display']] + $b['settings'],
  ])->save();
  echo "Footer block: $id\n";
}

// ---------------------------------------------------------------------------
// 2. Home and school pages: layout tweaks.
// ---------------------------------------------------------------------------
$titles = ['news_feed' => 'Latest news', 'events' => 'Upcoming events'];
foreach (['Home', 'Sample Elementary', 'Sample High School', 'Community news'] as $page_title) {
  $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'page', 'title' => $page_title]);
  if (!$nodes) {
    continue;
  }
  $node = reset($nodes);
  $changed = FALSE;
  foreach ($node->get('layout_builder__layout') as $item) {
    $section = $item->section;
    if ($section->getLayoutId() === 'layout_twocol_section' && ($section->getLayoutSettings()['column_widths'] ?? '') !== '67-33') {
      $section->setLayoutSettings(['column_widths' => '67-33'] + $section->getLayoutSettings());
      $changed = TRUE;
    }
    foreach ($section->getComponents() as $component) {
      $config = $component->get('configuration');
      foreach ($titles as $view => $label) {
        if (str_starts_with($config['id'] ?? '', "views_block:$view-")) {
          $want = ['label' => $label, 'label_display' => TRUE, 'items_per_page' => $view === 'news_feed' ? '4' : '5'];
          if (array_intersect_key($config, $want) != $want) {
            $component->setConfiguration($want + $config);
            $changed = TRUE;
          }
        }
      }
    }
  }
  if ($changed) {
    $node->save();
    echo "Updated layout: $page_title\n";
  }
}

echo "Phase 14 script complete.\n";

// ---------------------------------------------------------------------------
// 3. Site timezone = the District's (Central), for everyone.
//    Smart Date events carry America/Chicago; the calendar and date displays
//    otherwise render in the server/user timezone (it showed 5 AM for a 7 PM event).
// ---------------------------------------------------------------------------
\Drupal::configFactory()->getEditable('system.date')
  ->set('timezone.default', 'America/Chicago')
  ->set('timezone.user.configurable', FALSE)
  ->save();
echo "Site timezone: America/Chicago (not user-configurable)\n";

// ---------------------------------------------------------------------------
// 4. Calendar page title, and callout headings that repeated the page title.
// ---------------------------------------------------------------------------
if ($events = \Drupal\views\Entity\View::load('events')) {
  $displays = $events->get('display');
  if (isset($displays['page_calendar'])) {
    $displays['page_calendar']['display_options']['defaults']['title'] = FALSE;
    $displays['page_calendar']['display_options']['title'] = 'Calendar';
    $events->set('display', $displays);
    $events->save();
    echo "Calendar page title set\n";
  }
}
$storage = \Drupal::entityTypeManager()->getStorage('block_content');
foreach ($storage->loadByProperties(['type' => 'callout', 'reusable' => 0]) as $block) {
  if (in_array($block->get('field_heading')->value, ['Facility rentals', 'Volunteer hours'], TRUE)) {
    $block->set('field_heading', 'Before you start')->save();
  }
}
