<?php

/**
 * @file
 * Phase 15: school listing page (/schools). Adds detail fields to the school
 * group type, fills in the two sample schools, and adds a "Schools" main-menu
 * link. Re-runnable.
 *
 * Run: ddev drush php:script scripts/phase15_schools.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\menu_link_content\Entity\MenuLinkContent;

$display = \Drupal::service('entity_display.repository');
$fields = [
  'field_grades' => ['Grades', 'string'],
  'field_principal' => ['Principal', 'string'],
  'field_address' => ['Address', 'string'],
  'field_phone' => ['Phone', 'string'],
  'field_summary' => ['Summary', 'string_long'],
];
$weight = 10;
foreach ($fields as $name => [$label, $type]) {
  if (!FieldStorageConfig::loadByName('group', $name)) {
    FieldStorageConfig::create(['field_name' => $name, 'entity_type' => 'group', 'type' => $type])->save();
  }
  if (!FieldConfig::loadByName('group', 'school', $name)) {
    FieldConfig::create(['field_name' => $name, 'entity_type' => 'group', 'bundle' => 'school', 'label' => $label])->save();
  }
  $display->getFormDisplay('group', 'school')->setComponent($name, ['type' => $type === 'string' ? 'string_textfield' : 'string_textarea', 'weight' => $weight])->save();
  $display->getViewDisplay('group', 'school')->setComponent($name, ['type' => $type === 'string' ? 'string' : 'basic_string', 'label' => 'inline', 'weight' => $weight])->save();
  $weight++;
}

$data = [
  'Sample Elementary' => ['PK-5', 'Dr. Jane Miller', '123 Main Street, Waukee, IA 50263', '(515) 555-0101', 'A welcoming neighborhood school with small classes, hands-on learning and a strong arts program.'],
  'Sample High School' => ['9-12', 'Mark Thompson', '456 Warrior Way, Waukee, IA 50263', '(515) 555-0102', 'Advanced coursework, career pathways, athletics and clubs that prepare students for what comes next.'],
];
foreach ($data as $label => $values) {
  $groups = \Drupal::entityTypeManager()->getStorage('group')->loadByProperties(['type' => 'school', 'label' => $label]);
  if (!$groups) {
    echo "Missing group: $label\n";
    continue;
  }
  $group = reset($groups);
  foreach (array_keys($fields) as $i => $name) {
    $group->set($name, $values[$i]);
  }
  $group->save();
  echo "Updated $label\n";
}

$existing = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['menu_name' => 'main', 'link__uri' => 'internal:/schools']);
if (!$existing) {
  MenuLinkContent::create(['title' => 'Schools', 'menu_name' => 'main', 'link' => ['uri' => 'internal:/schools'], 'weight' => -5])->save();
  echo "Added Schools menu link\n";
}
drupal_flush_all_caches();
echo "Done.\n";
