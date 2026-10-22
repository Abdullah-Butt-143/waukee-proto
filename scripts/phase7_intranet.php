<?php

/**
 * @file
 * Phase 7: intranet. Views (announcements, document library, staff directory),
 * app launcher block, and an intranet menu hidden from anonymous users.
 *
 * Re-runnable. Run: ddev drush php:script scripts/phase7_intranet.php
 */

use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use Drupal\views\Entity\View;

$staff_roles = ['staff', 'school_editor', 'publisher', 'district_admin', 'administrator'];
$roles_map = array_combine($staff_roles, $staff_roles);

// ---------------------------------------------------------------------------
// Handler builders.
// ---------------------------------------------------------------------------
$field = function (string $name, string $label, string $formatter, array $settings = [], string $table = NULL) {
  $table = $table ?? "node__$name";
  return [
    'id' => $name, 'table' => $table, 'field' => $name, 'relationship' => 'none', 'group_type' => 'group',
    'admin_label' => '', 'label' => $label, 'plugin_id' => 'field', 'type' => $formatter, 'settings' => $settings,
    'entity_type' => 'node', 'entity_field' => $name, 'element_label_colon' => FALSE, 'hide_empty' => TRUE,
  ];
};
$title_field = [
  'id' => 'title', 'table' => 'node_field_data', 'field' => 'title', 'relationship' => 'none', 'group_type' => 'group',
  'admin_label' => '', 'label' => 'Name', 'plugin_id' => 'field', 'type' => 'string', 'settings' => ['link_to_entity' => TRUE],
  'entity_type' => 'node', 'entity_field' => 'title', 'element_label_colon' => FALSE,
];
$expose = function (string $identifier, string $label) {
  return [
    'operator_id' => $identifier . '_op', 'label' => $label, 'description' => '', 'use_operator' => FALSE,
    'operator' => $identifier . '_op', 'operator_limit_selection' => FALSE, 'operator_list' => [],
    'identifier' => $identifier, 'required' => FALSE, 'remember' => FALSE, 'multiple' => FALSE,
    'remember_roles' => ['authenticated' => 'authenticated'], 'reduce' => FALSE,
  ];
};
$tid_filter = function (string $id, string $vid, string $identifier, string $label) use ($expose) {
  return [
    'id' => $id, 'table' => 'taxonomy_index', 'field' => 'tid', 'relationship' => 'none', 'group_type' => 'group',
    'admin_label' => '', 'operator' => 'or', 'value' => [], 'group' => 1, 'exposed' => TRUE,
    'expose' => $expose($identifier, $label) + ['reduce' => FALSE],
    'is_grouped' => FALSE, 'group_info' => ['label' => '', 'description' => '', 'identifier' => '', 'optional' => TRUE, 'widget' => 'select', 'multiple' => FALSE, 'remember' => FALSE, 'default_group' => 'All', 'default_group_multiple' => [], 'group_items' => []],
    'plugin_id' => 'taxonomy_index_tid', 'type' => 'select', 'limit' => TRUE, 'vid' => $vid, 'hierarchy' => FALSE,
    'error_message' => TRUE, 'reduce_duplicates' => FALSE,
  ];
};
$status = ['id' => 'status', 'table' => 'node_field_data', 'field' => 'status', 'value' => '1', 'group' => 1, 'plugin_id' => 'boolean', 'entity_type' => 'node', 'entity_field' => 'status'];
$type = fn(string $bundle) => ['id' => 'type', 'table' => 'node_field_data', 'field' => 'type', 'value' => [$bundle => $bundle], 'group' => 1, 'plugin_id' => 'bundle', 'entity_type' => 'node', 'entity_field' => 'type'];
$role_access = ['type' => 'role', 'options' => ['role' => $roles_map]];
$sort = fn(string $f, string $table, string $order, string $plugin = 'standard') => [
  'id' => $f, 'table' => $table, 'field' => $f, 'order' => $order, 'plugin_id' => $plugin, 'relationship' => 'none',
  'group_type' => 'group', 'admin_label' => '', 'exposed' => FALSE, 'expose' => ['label' => ''],
] + ($plugin === 'date' ? ['entity_type' => 'node', 'entity_field' => $f, 'granularity' => 'second'] : []);
$exposed_form = ['type' => 'basic', 'options' => ['submit_button' => 'Apply', 'reset_button' => TRUE, 'reset_button_label' => 'Reset', 'exposed_sorts_label' => 'Sort by', 'expose_sort_order' => FALSE, 'sort_asc_label' => 'Asc', 'sort_desc_label' => 'Desc']];
$base = fn(array $o) => $o + [
  'cache' => ['type' => 'tag', 'options' => []],
  'query' => ['type' => 'views_query', 'options' => []],
  'use_ajax' => FALSE,
  'display_extenders' => [],
];

// ---------------------------------------------------------------------------
// 1. Announcements: filter by school, hide expired.
// ---------------------------------------------------------------------------
$expiry_ok = [
  'field_expiry_date_value' => [
    'id' => 'field_expiry_date_value', 'table' => 'node__field_expiry_date', 'field' => 'field_expiry_date_value',
    'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'operator' => '>=',
    'value' => ['type' => 'offset', 'value' => 'today', 'min' => '', 'max' => ''], 'group' => 2, 'exposed' => FALSE,
    'plugin_id' => 'datetime',
  ],
  'field_expiry_date_value_1' => [
    'id' => 'field_expiry_date_value_1', 'table' => 'node__field_expiry_date', 'field' => 'field_expiry_date_value',
    'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'operator' => 'empty',
    'value' => ['type' => 'date', 'value' => '', 'min' => '', 'max' => ''], 'group' => 2, 'exposed' => FALSE,
    'plugin_id' => 'datetime',
  ],
];
if (!View::load('announcements')) {
  View::create([
    'id' => 'announcements', 'label' => 'Announcements', 'module' => 'views', 'base_table' => 'node_field_data', 'base_field' => 'nid',
    'display' => [
      'default' => [
        'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
        'display_options' => $base([
          'title' => 'Announcements',
          'row' => ['type' => 'entity:node', 'options' => ['view_mode' => 'teaser']],
          'style' => ['type' => 'default', 'options' => ['row_class' => 'wk-feed__item']],
          'pager' => ['type' => 'mini', 'options' => ['items_per_page' => 10, 'offset' => 0]],
          'sorts' => ['created' => $sort('created', 'node_field_data', 'DESC', 'date')],
          'filters' => [
            'status' => $status,
            'type' => $type('announcement'),
            'tid' => $tid_filter('tid', 'school', 'school', 'School'),
          ] + $expiry_ok,
          'filter_groups' => ['operator' => 'AND', 'groups' => [1 => 'AND', 2 => 'OR']],
          'access' => $role_access,
          'exposed_form' => $exposed_form,
          'empty' => ['area_text_custom' => ['id' => 'area_text_custom', 'table' => 'views', 'field' => 'area_text_custom', 'plugin_id' => 'text_custom', 'empty' => TRUE, 'content' => 'No current announcements.']],
        ]),
      ],
      'page_1' => [
        'id' => 'page_1', 'display_title' => 'Page', 'display_plugin' => 'page', 'position' => 1,
        'display_options' => ['path' => 'intranet/announcements', 'menu' => ['type' => 'none'], 'display_extenders' => []],
      ],
      'block_1' => [
        'id' => 'block_1', 'display_title' => 'Block', 'display_plugin' => 'block', 'position' => 2,
        'display_options' => ['block_description' => 'Intranet announcements', 'block_category' => 'Lists (Views)', 'display_extenders' => []],
      ],
    ],
  ])->save();
  echo "Created view announcements\n";
}

// ---------------------------------------------------------------------------
// 2. Document library: exposed category filter.
// ---------------------------------------------------------------------------
if (!View::load('document_library')) {
  View::create([
    'id' => 'document_library', 'label' => 'Document library', 'module' => 'views', 'base_table' => 'node_field_data', 'base_field' => 'nid',
    'display' => [
      'default' => [
        'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
        'display_options' => $base([
          'title' => 'Document library',
          'fields' => [
            'title' => ['label' => 'Document'] + $title_field,
            'field_category' => $field('field_category', 'Category', 'entity_reference_label', ['link' => FALSE]),
            'field_access_level' => $field('field_access_level', 'Access', 'list_default'),
            'changed' => [
              'id' => 'changed', 'table' => 'node_field_data', 'field' => 'changed', 'relationship' => 'none', 'group_type' => 'group',
              'admin_label' => '', 'label' => 'Updated', 'plugin_id' => 'field', 'type' => 'timestamp',
              'settings' => ['date_format' => 'medium', 'custom_date_format' => '', 'timezone' => '', 'tooltip' => ['date_format' => '', 'custom_date_format' => ''], 'time_diff' => ['enabled' => FALSE, 'future_format' => '@interval hence', 'past_format' => '@interval ago', 'granularity' => 2, 'refresh' => 60]],
              'entity_type' => 'node', 'entity_field' => 'changed', 'element_label_colon' => FALSE,
            ],
          ],
          'style' => ['type' => 'table', 'options' => [
            'caption' => 'Documents', 'summary' => '', 'description' => '',
            'columns' => ['title' => 'title', 'field_category' => 'field_category', 'field_access_level' => 'field_access_level', 'changed' => 'changed'],
            'default' => 'title', 'empty_table' => TRUE, 'override' => TRUE, 'sticky' => FALSE,
            'info' => [
              'title' => ['sortable' => TRUE, 'default_sort_order' => 'asc', 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => ''],
              'field_category' => ['sortable' => FALSE, 'default_sort_order' => 'asc', 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => ''],
              'field_access_level' => ['sortable' => FALSE, 'default_sort_order' => 'asc', 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => 'priority-low'],
              'changed' => ['sortable' => TRUE, 'default_sort_order' => 'desc', 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => 'priority-low'],
            ],
          ]],
          'row' => ['type' => 'fields', 'options' => []],
          'pager' => ['type' => 'mini', 'options' => ['items_per_page' => 20, 'offset' => 0]],
          'sorts' => ['title' => $sort('title', 'node_field_data', 'ASC')],
          'filters' => [
            'status' => $status,
            'type' => $type('document'),
            'tid' => $tid_filter('tid', 'document_category', 'category', 'Category'),
          ],
          'access' => $role_access,
          'exposed_form' => $exposed_form,
          'empty' => ['area_text_custom' => ['id' => 'area_text_custom', 'table' => 'views', 'field' => 'area_text_custom', 'plugin_id' => 'text_custom', 'empty' => TRUE, 'content' => 'No documents found.']],
        ]),
      ],
      'page_1' => [
        'id' => 'page_1', 'display_title' => 'Page', 'display_plugin' => 'page', 'position' => 1,
        'display_options' => ['path' => 'intranet/documents', 'menu' => ['type' => 'none'], 'display_extenders' => []],
      ],
    ],
  ])->save();
  echo "Created view document_library\n";
}

// ---------------------------------------------------------------------------
// 3. Staff directory: search by name / job title, department, school.
//    page_1 = public directory (public profiles only),
//    page_2 = intranet directory (all profiles, staff roles only).
// ---------------------------------------------------------------------------
if (!View::load('staff_directory')) {
  $combine = [
    'id' => 'combine', 'table' => 'views', 'field' => 'combine', 'relationship' => 'none', 'group_type' => 'group',
    'admin_label' => '', 'operator' => 'contains', 'value' => '', 'group' => 1, 'exposed' => TRUE,
    'expose' => $expose('search', 'Name or job title'), 'plugin_id' => 'combine',
    'fields' => ['title' => 'title', 'field_job_title' => 'field_job_title'],
  ];
  $visibility_public = [
    'id' => 'field_visibility_value', 'table' => 'node__field_visibility', 'field' => 'field_visibility_value', 'relationship' => 'none',
    'group_type' => 'group', 'admin_label' => '', 'operator' => 'or', 'value' => ['public' => 'public'], 'group' => 1,
    'exposed' => FALSE, 'plugin_id' => 'list_field',
  ];
  $filters = [
    'status' => $status,
    'type' => $type('staff_profile'),
    'combine' => $combine,
    'tid' => $tid_filter('tid', 'department', 'department', 'Department'),
    'tid_1' => $tid_filter('tid_1', 'school', 'school', 'School'),
  ];
  View::create([
    'id' => 'staff_directory', 'label' => 'Staff directory', 'module' => 'views', 'base_table' => 'node_field_data', 'base_field' => 'nid',
    'display' => [
      'default' => [
        'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
        'display_options' => $base([
          'title' => 'Staff directory',
          'fields' => [
            'title' => $title_field,
            'field_job_title' => $field('field_job_title', 'Job title', 'string'),
            'field_department' => $field('field_department', 'Department', 'entity_reference_label', ['link' => FALSE]),
            'field_school' => $field('field_school', 'School', 'entity_reference_label', ['link' => FALSE]),
            'field_email' => $field('field_email', 'Email', 'email_mailto'),
            'field_phone' => $field('field_phone', 'Phone', 'telephone_link'),
          ],
          'style' => ['type' => 'table', 'options' => [
            'caption' => 'Staff', 'summary' => '', 'description' => '',
            'columns' => ['title' => 'title', 'field_job_title' => 'field_job_title', 'field_department' => 'field_department', 'field_school' => 'field_school', 'field_email' => 'field_email', 'field_phone' => 'field_phone'],
            'default' => 'title', 'empty_table' => TRUE, 'override' => TRUE, 'sticky' => FALSE,
            'info' => array_map(fn($sortable) => ['sortable' => $sortable, 'default_sort_order' => 'asc', 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => ''],
              ['title' => TRUE, 'field_job_title' => TRUE, 'field_department' => FALSE, 'field_school' => FALSE, 'field_email' => FALSE, 'field_phone' => FALSE]),
          ]],
          'row' => ['type' => 'fields', 'options' => []],
          'pager' => ['type' => 'mini', 'options' => ['items_per_page' => 25, 'offset' => 0]],
          'sorts' => ['title' => $sort('title', 'node_field_data', 'ASC')],
          'filters' => $filters,
          'access' => $role_access,
          'exposed_form' => $exposed_form,
          'empty' => ['area_text_custom' => ['id' => 'area_text_custom', 'table' => 'views', 'field' => 'area_text_custom', 'plugin_id' => 'text_custom', 'empty' => TRUE, 'content' => 'No matching staff.']],
        ]),
      ],
      'page_1' => [
        'id' => 'page_1', 'display_title' => 'Public directory', 'display_plugin' => 'page', 'position' => 1,
        'display_options' => [
          'path' => 'staff-directory', 'menu' => ['type' => 'none'],
          'defaults' => ['access' => FALSE, 'filters' => FALSE, 'filter_groups' => FALSE],
          'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
          'filters' => $filters + ['field_visibility_value' => $visibility_public],
          'filter_groups' => ['operator' => 'AND', 'groups' => [1 => 'AND']],
          'display_extenders' => [],
        ],
      ],
      'page_2' => [
        'id' => 'page_2', 'display_title' => 'Intranet directory', 'display_plugin' => 'page', 'position' => 2,
        'display_options' => ['path' => 'intranet/staff-directory', 'menu' => ['type' => 'none'], 'display_extenders' => []],
      ],
    ],
  ])->save();
  echo "Created view staff_directory\n";
}

// ---------------------------------------------------------------------------
// 4. App launcher block (block type + one reusable block).
// ---------------------------------------------------------------------------
if (!BlockContentType::load('app_launcher')) {
  BlockContentType::create(['id' => 'app_launcher', 'label' => 'App launcher', 'description' => 'Grid of links to staff applications.', 'revision' => FALSE])->save();
  echo "Created block type app_launcher\n";
}
if (!FieldStorageConfig::loadByName('block_content', 'field_links')) {
  FieldStorageConfig::create(['field_name' => 'field_links', 'entity_type' => 'block_content', 'type' => 'link', 'cardinality' => -1])->save();
}
if (!FieldConfig::loadByName('block_content', 'app_launcher', 'field_links')) {
  FieldConfig::create(['field_name' => 'field_links', 'entity_type' => 'block_content', 'bundle' => 'app_launcher', 'label' => 'Apps (title + link)', 'settings' => ['link_type' => 17, 'title' => 2]])->save();
}
$f = EntityFormDisplay::load('block_content.app_launcher.default') ?: EntityFormDisplay::create(['targetEntityType' => 'block_content', 'bundle' => 'app_launcher', 'mode' => 'default', 'status' => TRUE]);
$f->setComponent('field_links', ['type' => 'link_default', 'weight' => 1])->save();
$v = EntityViewDisplay::load('block_content.app_launcher.default') ?: EntityViewDisplay::create(['targetEntityType' => 'block_content', 'bundle' => 'app_launcher', 'mode' => 'default', 'status' => TRUE]);
$v->setComponent('field_links', ['type' => 'link', 'label' => 'hidden', 'weight' => 1, 'settings' => ['trim_length' => 80, 'target' => '_blank', 'rel' => 'noopener']])->save();

$launcher = \Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties(['info' => 'Intranet app launcher']);
if (!$launcher) {
  $launcher = BlockContent::create([
    'type' => 'app_launcher', 'info' => 'Intranet app launcher', 'reusable' => TRUE,
    'field_links' => [
      ['uri' => 'https://example.com/email', 'title' => 'Sample email'],
      ['uri' => 'https://example.com/hr', 'title' => 'Sample HR portal'],
      ['uri' => 'https://example.com/sis', 'title' => 'Sample student information'],
      ['uri' => 'https://example.com/helpdesk', 'title' => 'Sample help desk'],
      ['uri' => 'https://example.com/calendar', 'title' => 'Sample staff calendar'],
      ['uri' => 'https://example.com/forms', 'title' => 'Sample forms library'],
    ],
  ]);
  $launcher->save();
  echo "Created app launcher block content\n";
}
else {
  $launcher = reset($launcher);
}

// ---------------------------------------------------------------------------
// 5. Intranet menu (links resolve to role-protected routes, so they disappear
//    for anonymous users) + role-restricted block placement.
// ---------------------------------------------------------------------------
if (!Menu::load('intranet')) {
  Menu::create(['id' => 'intranet', 'label' => 'Intranet', 'description' => 'Staff intranet navigation. Only shown to staff roles.'])->save();
  echo "Created menu intranet\n";
}
$weight = 0;
foreach (['Announcements' => '/intranet/announcements', 'Document library' => '/intranet/documents', 'Staff directory' => '/intranet/staff-directory'] as $title => $path) {
  if (!\Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['menu_name' => 'intranet', 'title' => $title])) {
    MenuLinkContent::create(['menu_name' => 'intranet', 'title' => $title, 'link' => ['uri' => 'internal:' . $path], 'weight' => $weight])->save();
    echo "  intranet link: $title\n";
  }
  $weight++;
}

$role_visibility = ['user_role' => ['id' => 'user_role', 'negate' => FALSE, 'context_mapping' => ['user' => '@user.current_user_context:current_user'], 'roles' => $roles_map]];
$blocks = [
  'waukee_theme_intranet_menu' => [
    'plugin' => 'system_menu_block:intranet', 'region' => 'secondary_menu', 'weight' => -5,
    'label' => 'Intranet', 'label_display' => FALSE, 'visibility' => $role_visibility,
    'settings' => ['level' => 1, 'depth' => 1, 'expand_all_items' => FALSE, 'provider' => 'system'],
  ],
  'waukee_theme_intranet_app_launcher' => [
    'plugin' => 'block_content:' . $launcher->uuid(), 'region' => 'sidebar', 'weight' => 0,
    'label' => 'Apps', 'label_display' => 'visible',
    'visibility' => $role_visibility + ['request_path' => ['id' => 'request_path', 'negate' => FALSE, 'pages' => "/intranet\n/intranet/*"]],
    'settings' => ['provider' => 'block_content', 'status' => TRUE, 'info' => '', 'view_mode' => 'default'],
  ],
];
foreach ($blocks as $id => $b) {
  if (Block::load($id)) {
    continue;
  }
  Block::create([
    'id' => $id, 'theme' => 'waukee_theme', 'region' => $b['region'], 'plugin' => $b['plugin'], 'weight' => $b['weight'],
    'status' => TRUE, 'visibility' => $b['visibility'],
    'settings' => ['id' => $b['plugin'], 'label' => $b['label'], 'label_display' => $b['label_display']] + $b['settings'],
  ])->save();
  echo "  block $id ({$b['region']})\n";
}

// Teaser display for announcements (used by the announcements view).
$d = EntityViewDisplay::load('node.announcement.teaser') ?: EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => 'announcement', 'mode' => 'teaser', 'status' => TRUE]);
foreach (array_keys($d->getComponents()) as $c) {
  if ($c !== 'links') {
    $d->removeComponent($c);
  }
}
$d->setComponent('field_target_school', ['type' => 'entity_reference_label', 'label' => 'inline', 'settings' => ['link' => FALSE], 'weight' => 0]);
$d->setComponent('body', ['type' => 'text_summary_or_trimmed', 'label' => 'hidden', 'settings' => ['trim_length' => 300], 'weight' => 1]);
$d->setComponent('field_expiry_date', ['type' => 'datetime_default', 'label' => 'inline', 'settings' => ['format_type' => 'medium', 'timezone_override' => ''], 'weight' => 2]);
$d->save();

echo "Phase 7 script complete.\n";

// ---------------------------------------------------------------------------
// Migration: "q" is a reserved Views exposed-filter name (it is silently
// ignored on the URL). Rename the staff directory keyword filter to "search".
// ---------------------------------------------------------------------------
if ($view = View::load('staff_directory')) {
  $changed = FALSE;
  $displays = $view->get('display');
  foreach ($displays as $id => &$display) {
    if (isset($display['display_options']['filters']['combine']['expose']['identifier']) && $display['display_options']['filters']['combine']['expose']['identifier'] === 'q') {
      $display['display_options']['filters']['combine']['expose']['identifier'] = 'search';
      $changed = TRUE;
    }
  }
  unset($display);
  if ($changed) {
    $view->set('display', $displays);
    $view->save();
    echo "staff_directory: keyword filter renamed q -> search\n";
  }
}
