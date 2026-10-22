<?php

/**
 * @file
 * Phase 1: content model (vocabularies, roles, users, content types, fields).
 *
 * Re-runnable: every item is checked before it is created.
 * Run with: ddev drush php:script scripts/phase1_content_model.php
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

$etm = \Drupal::entityTypeManager();

// Core modules needed for the field types used below.
$installer = \Drupal::service('module_installer');
foreach (['datetime', 'link', 'telephone', 'options', 'text', 'file', 'image'] as $m) {
  if (!\Drupal::moduleHandler()->moduleExists($m)) {
    $installer->install([$m]);
  }
}

// ---------------------------------------------------------------------------
// 1. Vocabularies + terms.
// ---------------------------------------------------------------------------
$vocabs = [
  'school' => [
    'label' => 'School',
    'terms' => ['District (all schools)', 'Sample Elementary', 'Sample High School'],
  ],
  'audience' => [
    'label' => 'Audience',
    'terms' => ['Current families', 'Prospective families', 'Students', 'Staff', 'Community', 'Job seekers'],
  ],
  'department' => [
    'label' => 'Department',
    'terms' => ['Administration', 'Curriculum & Instruction', 'Technology', 'Transportation', 'Nutrition Services', 'Human Resources'],
  ],
  'document_category' => [
    'label' => 'Document category',
    'terms' => ['Handbooks', 'Policies', 'Forms', 'Board materials', 'Newsletters'],
  ],
];
foreach ($vocabs as $vid => $def) {
  if (!Vocabulary::load($vid)) {
    Vocabulary::create(['vid' => $vid, 'name' => $def['label']])->save();
    echo "Created vocabulary $vid\n";
  }
  foreach ($def['terms'] as $weight => $name) {
    $existing = $etm->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $name]);
    if (!$existing) {
      Term::create(['vid' => $vid, 'name' => $name, 'weight' => $weight])->save();
      echo "  term: $name\n";
    }
  }
}

// ---------------------------------------------------------------------------
// 2. Content types.
// ---------------------------------------------------------------------------
$types = [
  'page' => ['Basic page', 'Static page, built with Layout Builder from Phase 6.'],
  'news' => ['News', 'News article.'],
  'event' => ['Event', 'Calendar event.'],
  'staff_profile' => ['Staff profile', 'Staff directory entry (sample data only).'],
  'document' => ['Document', 'File with category and access level.'],
  'announcement' => ['Announcement', 'Intranet announcement, optionally targeted to a school.'],
  'job_posting' => ['Job posting', 'Open position.'],
  'lunch_menu' => ['Lunch menu', 'Daily lunch menu for a school.'],
];
foreach ($types as $id => [$label, $desc]) {
  if (!NodeType::load($id)) {
    NodeType::create([
      'type' => $id,
      'name' => $label,
      'description' => $desc,
      'new_revision' => TRUE,
      'display_submitted' => FALSE,
    ])->save();
    echo "Created content type $id\n";
  }
}

// ---------------------------------------------------------------------------
// 3. Field helper.
// ---------------------------------------------------------------------------
/**
 * Create a field storage (once) and an instance on a bundle (once).
 */
function wk_field(string $bundle, string $name, string $type, string $label, array $o = []): void {
  $fname = 'field_' . $name;
  $storage = FieldStorageConfig::loadByName('node', $fname);
  if (!$storage) {
    $storage = FieldStorageConfig::create([
      'field_name' => $fname,
      'entity_type' => 'node',
      'type' => $type,
      'cardinality' => $o['cardinality'] ?? 1,
      'settings' => $o['storage_settings'] ?? [],
    ]);
    $storage->save();
  }
  if (!FieldConfig::loadByName('node', $bundle, $fname)) {
    FieldConfig::create([
      'field_name' => $fname,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $label,
      'required' => $o['required'] ?? FALSE,
      'default_value' => $o['default'] ?? [],
      'settings' => $o['settings'] ?? [],
    ])->save();
    echo "  field $bundle.$fname\n";
  }

  // Widget / formatter.
  $form = EntityFormDisplay::load("node.$bundle.default") ?: EntityFormDisplay::create(['targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  $view = EntityViewDisplay::load("node.$bundle.default") ?: EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  if (!$form->getComponent($fname) && !empty($o['widget'])) {
    $form->setComponent($fname, ['type' => $o['widget'], 'weight' => $o['weight'] ?? 10, 'settings' => $o['widget_settings'] ?? []])->save();
  }
  if (!$view->getComponent($fname) && !empty($o['formatter'])) {
    $view->setComponent($fname, ['type' => $o['formatter'], 'label' => $o['label_display'] ?? 'above', 'weight' => $o['weight'] ?? 10, 'settings' => $o['formatter_settings'] ?? []])->save();
  }
}

/** Entity reference to taxonomy terms. */
function wk_term_ref(string $bundle, string $name, string $label, string $vid, bool $multi = FALSE, bool $required = FALSE, int $weight = 10): void {
  wk_field($bundle, $name, 'entity_reference', $label, [
    'cardinality' => $multi ? -1 : 1,
    'required' => $required,
    'storage_settings' => ['target_type' => 'taxonomy_term'],
    'settings' => ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => [$vid => $vid]]],
    'widget' => $multi ? 'options_buttons' : 'options_select',
    'formatter' => 'entity_reference_label',
    'formatter_settings' => ['link' => FALSE],
    'weight' => $weight,
  ]);
}

/** Entity reference to media. */
function wk_media_ref(string $bundle, string $name, string $label, string $media_bundle, bool $required = FALSE, int $weight = 10): void {
  wk_field($bundle, $name, 'entity_reference', $label, [
    'required' => $required,
    'storage_settings' => ['target_type' => 'media'],
    'settings' => ['handler' => 'default:media', 'handler_settings' => ['target_bundles' => [$media_bundle => $media_bundle]]],
    'widget' => 'media_library_widget',
    'widget_settings' => ['media_types' => []],
    'formatter' => 'entity_reference_entity_view',
    'formatter_settings' => ['view_mode' => 'default'],
    'label_display' => 'hidden',
    'weight' => $weight,
  ]);
}

function wk_list(string $bundle, string $name, string $label, array $allowed, string $default, int $weight = 10): void {
  wk_field($bundle, $name, 'list_string', $label, [
    'required' => TRUE,
    'storage_settings' => ['allowed_values' => $allowed],
    'default' => [['value' => $default]],
    'widget' => 'options_buttons',
    'formatter' => 'list_default',
    'weight' => $weight,
  ]);
}

function wk_text(string $bundle, string $name, string $label, string $type = 'string', bool $required = FALSE, int $weight = 10): void {
  $map = [
    'string' => ['string_textfield', 'string'],
    'text_long' => ['text_textarea', 'text_default'],
    'text_with_summary' => ['text_textarea_with_summary', 'text_default'],
    'email' => ['email_default', 'basic_string'],
    'telephone' => ['telephone_default', 'telephone_link'],
  ];
  [$widget, $formatter] = $map[$type];
  if ($type === 'email') {
    $formatter = 'email_mailto';
  }
  wk_field($bundle, $name, $type, $label, [
    'required' => $required,
    'widget' => $widget,
    'formatter' => $formatter,
    'weight' => $weight,
  ]);
}

function wk_date(string $bundle, string $name, string $label, bool $required = FALSE, int $weight = 10): void {
  wk_field($bundle, $name, 'datetime', $label, [
    'required' => $required,
    'storage_settings' => ['datetime_type' => 'date'],
    'widget' => 'datetime_default',
    'formatter' => 'datetime_default',
    'formatter_settings' => ['format_type' => 'medium'],
    'weight' => $weight,
  ]);
}

// ---------------------------------------------------------------------------
// 4. Fields per content type.
// ---------------------------------------------------------------------------
echo "Fields:\n";

// Shared body storage (not present: site was not installed from the standard profile).
if (!FieldStorageConfig::loadByName('node', 'body')) {
  FieldStorageConfig::create([
    'field_name' => 'body',
    'entity_type' => 'node',
    'type' => 'text_with_summary',
  ])->save();
}

// Basic page (body is not added by default when created programmatically).
node_add_body_field(NodeType::load('page'));
wk_field('page', 'owner', 'entity_reference', 'Page owner', [
  'storage_settings' => ['target_type' => 'user'],
  'settings' => ['handler' => 'default:user', 'handler_settings' => ['include_anonymous' => FALSE]],
  'widget' => 'entity_reference_autocomplete',
  'formatter' => 'entity_reference_label',
  'formatter_settings' => ['link' => FALSE],
  'weight' => 20,
]);
wk_date('page', 'review_date', 'Review date', FALSE, 21);

// News.
node_add_body_field(NodeType::load('news'));
wk_text('news', 'summary', 'Summary', 'text_long', TRUE, 1);
wk_media_ref('news', 'image', 'Image', 'image', FALSE, 2);
wk_term_ref('news', 'school', 'School', 'school', TRUE, TRUE, 3);
wk_term_ref('news', 'audience', 'Audience', 'audience', TRUE, FALSE, 4);

// Event.
node_add_body_field(NodeType::load('event'));
wk_field('event', 'when', 'smartdate', 'Date and time', [
  'required' => TRUE,
  'widget' => 'smartdate_default',
  'formatter' => 'smartdate_default',
  'weight' => 1,
]);
wk_text('event', 'location', 'Location', 'string', FALSE, 2);
wk_term_ref('event', 'school', 'School', 'school', TRUE, TRUE, 3);
wk_term_ref('event', 'audience', 'Audience', 'audience', TRUE, FALSE, 4);

// Staff profile.
wk_text('staff_profile', 'job_title', 'Job title', 'string', TRUE, 1);
wk_term_ref('staff_profile', 'department', 'Department', 'department', FALSE, FALSE, 2);
wk_term_ref('staff_profile', 'school', 'School', 'school', FALSE, TRUE, 3);
wk_text('staff_profile', 'email', 'Email', 'email', FALSE, 4);
wk_text('staff_profile', 'phone', 'Phone', 'telephone', FALSE, 5);
wk_media_ref('staff_profile', 'photo', 'Photo', 'image', FALSE, 6);
wk_list('staff_profile', 'visibility', 'Visibility', ['public' => 'Public', 'staff' => 'Staff only'], 'public', 7);

// Document.
wk_media_ref('document', 'file', 'File', 'document', TRUE, 1);
wk_term_ref('document', 'category', 'Category', 'document_category', FALSE, TRUE, 2);
wk_term_ref('document', 'audience', 'Audience', 'audience', TRUE, FALSE, 3);
wk_list('document', 'access_level', 'Access level', ['public' => 'Public', 'staff' => 'Staff', 'restricted' => 'Restricted'], 'public', 4);

// Announcement.
node_add_body_field(NodeType::load('announcement'));
wk_term_ref('announcement', 'target_school', 'Target school', 'school', FALSE, TRUE, 1);
wk_date('announcement', 'expiry_date', 'Expiry date', FALSE, 2);

// Job posting.
node_add_body_field(NodeType::load('job_posting'));
wk_text('job_posting', 'location', 'Location', 'string', TRUE, 1);
wk_date('job_posting', 'closing_date', 'Closing date', TRUE, 2);
wk_field('job_posting', 'apply_link', 'link', 'Apply link', [
  'required' => TRUE,
  'settings' => ['link_type' => 17, 'title' => 0],
  'widget' => 'link_default',
  'formatter' => 'link',
  'weight' => 3,
]);

// Lunch menu.
wk_date('lunch_menu', 'menu_date', 'Date', TRUE, 1);
wk_term_ref('lunch_menu', 'school', 'School', 'school', FALSE, TRUE, 2);
wk_text('lunch_menu', 'items', 'Menu items', 'text_long', TRUE, 3);

// ---------------------------------------------------------------------------
// 5. Roles.
// ---------------------------------------------------------------------------
$roles = [
  'district_admin' => 'District admin',
  'publisher' => 'Publisher',
  'school_editor' => 'School editor',
  'staff' => 'Staff',
];
foreach ($roles as $rid => $label) {
  if (!Role::load($rid)) {
    Role::create(['id' => $rid, 'label' => $label])->save();
    echo "Created role $rid\n";
  }
}

// Baseline permissions. Group-level (own-school) scoping comes in Phase 3,
// publish/review transitions in Phase 4.
$bundles = array_keys($types);
$perm_handler = \Drupal::service('user.permissions');
$known = array_keys($perm_handler->getPermissions());
$grant = function (string $rid, array $perms) use ($known) {
  $role = Role::load($rid);
  $valid = array_values(array_intersect($perms, $known));
  $missing = array_diff($perms, $known);
  if ($missing) {
    echo "  (skipped unknown permissions for $rid: " . implode(', ', $missing) . ")\n";
  }
  foreach ($valid as $p) {
    $role->grantPermission($p);
  }
  $role->save();
};

$content_all = ['access content overview', 'view own unpublished content', 'view all revisions', 'access media overview'];
foreach ($bundles as $b) {
  array_push($content_all,
    "create $b content", "edit own $b content", "edit any $b content",
    "delete own $b content", "view $b revisions");
}
$taxonomy_edit = [];
foreach (array_keys($vocabs) as $v) {
  array_push($taxonomy_edit, "create terms in $v", "edit terms in $v", "delete terms in $v");
}

$grant('district_admin', array_merge($content_all, $taxonomy_edit, [
  'access administration pages', 'view the administration theme', 'access toolbar',
  'administer nodes', 'bypass node access', 'administer users', 'administer taxonomy',
  'access taxonomy overview', 'create media', 'update any media', 'delete any media',
  'view own unpublished media', 'revert all revisions', 'delete all revisions', 'administer menu',
]));
$grant('publisher', array_merge($content_all, [
  'access administration pages', 'view the administration theme', 'access toolbar',
  'access taxonomy overview', 'create media', 'update any media', 'delete any media',
]));
// School editors: create/edit own content only; no "edit any" at site level.
$se = ['access content overview', 'view own unpublished content', 'access media overview',
  'access administration pages', 'view the administration theme', 'access toolbar', 'create media', 'update media'];
foreach (['page', 'news', 'event', 'announcement', 'lunch_menu'] as $b) {
  array_push($se, "create $b content", "edit own $b content", "view $b revisions");
}
$grant('school_editor', $se);
$grant('staff', ['access content']);
$grant('authenticated', ['access content']);

// ---------------------------------------------------------------------------
// 6. Demo users (sample accounts only).
// ---------------------------------------------------------------------------
$users = [
  'admin.demo' => ['district_admin', 'admin.demo@example.test'],
  'comms.publisher' => ['publisher', 'comms.publisher@example.test'],
  'school.editor' => ['school_editor', 'school.editor@example.test'],
  'teacher.staff' => ['staff', 'teacher.staff@example.test'],
];
foreach ($users as $name => [$rid, $mail]) {
  $existing = user_load_by_name($name);
  if (!$existing) {
    User::create([
      'name' => $name,
      'mail' => $mail,
      'pass' => 'Demo#2026',
      'status' => 1,
      'roles' => [$rid],
    ])->save();
    echo "Created user $name ($rid)\n";
  }
  elseif (!$existing->hasRole($rid)) {
    $existing->addRole($rid);
    $existing->save();
  }
}

echo "Phase 1 script complete.\n";
