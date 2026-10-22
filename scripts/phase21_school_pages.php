<?php

/**
 * @file
 * Phase 21: "Pages from <school>" list on each school landing page, and
 * allow the block in the page builder. Pages created through a school's
 * "Add page" link (group content) show up here automatically. Re-runnable.
 *
 * Run: ddev drush cr && ddev drush php:script scripts/phase21_school_pages.php
 */

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

$etm = \Drupal::entityTypeManager();
$uuid = \Drupal::service('uuid');

// 1. Allow the block in the page builder (Layout Builder Restrictions).
$display = EntityViewDisplay::load('node.page.default');
$restrictions = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', []);
$allowed = $restrictions['allowlisted_blocks']['Lists (Views)'] ?? [];
if (!in_array('waukee_school_pages', $allowed, TRUE)) {
  $allowed[] = 'waukee_school_pages';
  $restrictions['allowlisted_blocks']['Lists (Views)'] = $allowed;
  $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', $restrictions);
  $display->save();
  echo "Allowed 'School pages list' in the page builder\n";
}

// 2. Add the block to each school landing page (once).
foreach (['Sample Elementary', 'Sample High School'] as $label) {
  $groups = $etm->getStorage('group')->loadByProperties(['type' => 'school', 'label' => $label]);
  $nodes = $etm->getStorage('node')->loadByProperties(['type' => 'page', 'title' => $label]);
  if (!$groups || !$nodes) {
    echo "  skipped $label (group or landing page missing)\n";
    continue;
  }
  $group = reset($groups);
  $node = reset($nodes);
  $sections = array_map(fn($item) => $item->section, iterator_to_array($node->get('layout_builder__layout')));
  $has = FALSE;
  foreach ($sections as $section) {
    foreach ($section->getComponents() as $component) {
      if (($component->get('configuration')['id'] ?? '') === 'waukee_school_pages') {
        $has = TRUE;
      }
    }
  }
  if ($has) {
    echo "  $label: already has the list\n";
    continue;
  }
  $section = new Section('layout_onecol');
  $section->appendComponent(new SectionComponent($uuid->generate(), 'content', [
    'id' => 'waukee_school_pages', 'label' => 'Pages', 'label_display' => FALSE, 'provider' => 'waukee_core',
    'context_mapping' => [], 'group_id' => (int) $group->id(), 'limit' => 12,
  ], ['weight' => 0]));
  $sections[] = $section;
  $node->set('layout_builder__layout', $sections);
  $node->setNewRevision(FALSE);
  $node->save();
  echo "  $label: added 'Pages from' list\n";
}
drupal_flush_all_caches();
echo "Done.\n";
