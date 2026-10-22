<?php

/**
 * @file
 * Phase 18: schema for the Migrate Plus PoC requested alongside
 * scripts/migration/scrape-schools.mjs and
 * web/modules/custom/waukee_migrate. Creates:
 *   - node bundle "school" (field_school_type, field_grades, field_address,
 *     field_phone, field_attendance_phone, field_fax, field_hours,
 *     field_early_dismissal, field_principal_name, field_principal_bio,
 *     field_staff, field_announcements)
 *   - paragraph bundle "staff_member" (field_staff_name, field_staff_role,
 *     field_staff_email)
 *   - paragraph bundle "school_announcement" (field_announcement_title,
 *     field_announcement_link)
 *
 * ARCHITECTURE NOTE: this is a SEPARATE data model from the "school" GROUP
 * bundle created by scripts/phase17_schools_data.php, which already backs
 * the live /schools and /schools/{school} pages. Running this script does
 * not touch those pages or groups. Decide which model is authoritative
 * before pointing real content/URLs at both.
 *
 * Requires: composer require drupal/paragraphs drupal/migrate_plus
 *   drupal/migrate_tools drupal/migrate_source_csv
 * then: ddev drush en paragraphs migrate_plus migrate_tools migrate_source_csv waukee_migrate
 *
 * Re-runnable. Run: ddev drush php:script scripts/phase18_school_migration_schema.php
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;

if (!\Drupal::moduleHandler()->moduleExists('paragraphs')) {
  echo "The 'paragraphs' module is not installed. Run:\n";
  echo "  composer require drupal/paragraphs drupal/migrate_plus drupal/migrate_tools drupal/migrate_source_csv\n";
  echo "  ddev drush en paragraphs migrate_plus migrate_tools migrate_source_csv waukee_migrate\n";
  echo "then re-run this script.\n";
  return;
}

// ---------------------------------------------------------------------------
// Field helpers (same pattern as scripts/phase17_schools_data.php).
// ---------------------------------------------------------------------------
function wk18_field(string $entity_type, string $bundle, string $name, string $type, string $label, array $o = []): void {
  $fname = 'field_' . $name;
  if (!FieldStorageConfig::loadByName($entity_type, $fname)) {
    FieldStorageConfig::create([
      'field_name' => $fname,
      'entity_type' => $entity_type,
      'type' => $type,
      'cardinality' => $o['cardinality'] ?? 1,
      'settings' => $o['storage_settings'] ?? [],
    ])->save();
  }
  if (!FieldConfig::loadByName($entity_type, $bundle, $fname)) {
    FieldConfig::create([
      'field_name' => $fname,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $label,
      'required' => $o['required'] ?? FALSE,
      'settings' => $o['settings'] ?? [],
    ])->save();
  }
  $form = EntityFormDisplay::load("$entity_type.$bundle.default") ?: EntityFormDisplay::create(['targetEntityType' => $entity_type, 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  $view = EntityViewDisplay::load("$entity_type.$bundle.default") ?: EntityViewDisplay::create(['targetEntityType' => $entity_type, 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  $weight = $o['weight'] ?? 10;
  $form->setComponent($fname, ['type' => $o['widget'], 'weight' => $weight, 'settings' => $o['widget_settings'] ?? []])->save();
  $view->setComponent($fname, ['type' => $o['formatter'], 'label' => 'inline', 'weight' => $weight, 'settings' => $o['formatter_settings'] ?? []])->save();
}

// ---------------------------------------------------------------------------
// 1. Paragraph bundles.
// ---------------------------------------------------------------------------
foreach (['staff_member' => 'Staff member', 'school_announcement' => 'School announcement'] as $id => $label) {
  if (!ParagraphsType::load($id)) {
    ParagraphsType::create(['id' => $id, 'label' => $label])->save();
    echo "Created paragraph type $id\n";
  }
}

wk18_field('paragraph', 'staff_member', 'staff_name', 'string', 'Name', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 0]);
wk18_field('paragraph', 'staff_member', 'staff_role', 'string', 'Role', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 1]);
wk18_field('paragraph', 'staff_member', 'staff_email', 'email', 'Email', ['widget' => 'email_default', 'formatter' => 'basic_string', 'weight' => 2]);

wk18_field('paragraph', 'school_announcement', 'announcement_title', 'string', 'Title', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 0]);
wk18_field('paragraph', 'school_announcement', 'announcement_link', 'link', 'Link', [
  'storage_settings' => ['link_type' => 17, 'title' => 0],
  'widget' => 'link_default', 'formatter' => 'link', 'weight' => 1,
]);

// ---------------------------------------------------------------------------
// 2. Node bundle "school".
// ---------------------------------------------------------------------------
if (!NodeType::load('school')) {
  NodeType::create([
    'type' => 'school',
    'name' => 'School',
    'description' => 'A school building, imported from waukeeschools.org (Migrate Plus PoC).',
  ])->save();
  echo "Created node type: school\n";
}

$bundle = 'school';
wk18_field('node', $bundle, 'school_type', 'string', 'Type / Category', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 1]);
wk18_field('node', $bundle, 'grades', 'string', 'Grades', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 2]);
wk18_field('node', $bundle, 'address', 'string', 'Address', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 3]);
wk18_field('node', $bundle, 'phone', 'string', 'Phone', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 4]);
wk18_field('node', $bundle, 'attendance_phone', 'string', 'Attendance line', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 5]);
wk18_field('node', $bundle, 'fax', 'string', 'Fax', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 6]);
wk18_field('node', $bundle, 'hours', 'string', 'Hours', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 7]);
wk18_field('node', $bundle, 'early_dismissal', 'string', 'Early dismissal', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 8]);
wk18_field('node', $bundle, 'principal_name', 'string', 'Principal / Administrator', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 9]);
wk18_field('node', $bundle, 'principal_bio', 'text_long', 'Principal biography', ['widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 10]);
wk18_field('node', $bundle, 'staff', 'entity_reference_revisions', 'Staff', [
  'cardinality' => -1,
  'storage_settings' => ['target_type' => 'paragraph'],
  'settings' => ['handler' => 'default:paragraph', 'handler_settings' => ['target_bundles' => ['staff_member' => 'staff_member'], 'negate' => 0]],
  'widget' => 'entity_reference_paragraphs', 'formatter' => 'entity_reference_revisions_entity_view', 'weight' => 11,
]);
wk18_field('node', $bundle, 'announcements', 'entity_reference_revisions', 'Announcements', [
  'cardinality' => -1,
  'storage_settings' => ['target_type' => 'paragraph'],
  'settings' => ['handler' => 'default:paragraph', 'handler_settings' => ['target_bundles' => ['school_announcement' => 'school_announcement'], 'negate' => 0]],
  'widget' => 'entity_reference_paragraphs', 'formatter' => 'entity_reference_revisions_entity_view', 'weight' => 12,
]);

drupal_flush_all_caches();
echo "Phase 18 complete.\n";
