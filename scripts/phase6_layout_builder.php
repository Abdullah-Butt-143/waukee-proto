<?php

/**
 * @file
 * Phase 6: Layout Builder on Basic page, component block types, Views blocks
 * for the news and event feeds, and Layout Builder Restrictions.
 *
 * Re-runnable. Run: ddev drush php:script scripts/phase6_layout_builder.php
 */

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\user\Entity\Role;
use Drupal\views\Entity\View;

if (!\Drupal::moduleHandler()->moduleExists('waukee_core')) {
  \Drupal::service('module_installer')->install(['waukee_core']);
  echo "Enabled waukee_core\n";
}

// ---------------------------------------------------------------------------
// Field helpers for block_content bundles.
// ---------------------------------------------------------------------------
function wk6_field(string $bundle, string $name, string $type, string $label, array $o = []): void {
  $fname = 'field_' . $name;
  $storage = FieldStorageConfig::loadByName('block_content', $fname);
  if (!$storage) {
    FieldStorageConfig::create([
      'field_name' => $fname,
      'entity_type' => 'block_content',
      'type' => $type,
      'cardinality' => $o['cardinality'] ?? 1,
      'settings' => $o['storage_settings'] ?? [],
    ])->save();
  }
  if (!FieldConfig::loadByName('block_content', $bundle, $fname)) {
    FieldConfig::create([
      'field_name' => $fname,
      'entity_type' => 'block_content',
      'bundle' => $bundle,
      'label' => $label,
      'required' => $o['required'] ?? FALSE,
      'default_value' => $o['default'] ?? [],
      'settings' => $o['settings'] ?? [],
    ])->save();
  }
  $form = EntityFormDisplay::load("block_content.$bundle.default") ?: EntityFormDisplay::create(['targetEntityType' => 'block_content', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  $view = EntityViewDisplay::load("block_content.$bundle.default") ?: EntityViewDisplay::create(['targetEntityType' => 'block_content', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  $weight = $o['weight'] ?? 1;
  $form->setComponent($fname, ['type' => $o['widget'], 'weight' => $weight, 'settings' => $o['widget_settings'] ?? []])->save();
  $view->setComponent($fname, ['type' => $o['formatter'], 'label' => 'hidden', 'weight' => $weight, 'settings' => $o['formatter_settings'] ?? []])->save();
}
function wk6_heading(string $bundle, bool $required = FALSE, int $weight = 0): void {
  wk6_field($bundle, 'heading', 'string', 'Heading', ['required' => $required, 'widget' => 'string_textfield', 'formatter' => 'string', 'weight' => $weight]);
}
function wk6_text(string $bundle, string $name, string $label, string $type, int $weight, bool $required = FALSE): void {
  wk6_field($bundle, $name, $type, $label, [
    'required' => $required,
    'widget' => $type === 'string' ? 'string_textfield' : 'text_textarea',
    'formatter' => $type === 'string' ? 'string' : 'text_default',
    'weight' => $weight,
  ]);
}
function wk6_media(string $bundle, string $name, string $label, string $media_bundle, int $weight): void {
  wk6_field($bundle, $name, 'entity_reference', $label, [
    'storage_settings' => ['target_type' => 'media'],
    'settings' => ['handler' => 'default:media', 'handler_settings' => ['target_bundles' => [$media_bundle => $media_bundle]]],
    'widget' => 'media_library_widget',
    'formatter' => 'entity_reference_entity_view',
    'formatter_settings' => ['view_mode' => 'default'],
    'weight' => $weight,
  ]);
}
function wk6_links(string $bundle, string $name, string $label, int $weight, bool $multi = TRUE, bool $title_required = TRUE): void {
  wk6_field($bundle, $name, 'link', $label, [
    'cardinality' => $multi ? -1 : 1,
    'settings' => ['link_type' => 17, 'title' => $title_required ? 2 : 1],
    'widget' => 'link_default',
    'formatter' => 'link',
    'weight' => $weight,
  ]);
}

// ---------------------------------------------------------------------------
// 1. Block types (used as inline blocks inside layouts).
// ---------------------------------------------------------------------------
$block_types = [
  'hero' => 'Hero',
  'card_grid' => 'Card grid',
  'audience_pathways' => 'Audience pathways',
  'quick_links' => 'Quick links',
  'accordion' => 'Accordion',
  'callout' => 'Callout',
  'image_text' => 'Image + text',
  'video_embed' => 'Video embed',
  'emergency_alert' => 'Emergency alert',
];
foreach ($block_types as $id => $label) {
  if (!BlockContentType::load($id)) {
    BlockContentType::create(['id' => $id, 'label' => $label, 'description' => "$label component.", 'revision' => FALSE])->save();
    echo "Created block type $id\n";
  }
}

// Hero.
wk6_heading('hero', TRUE, 0);
wk6_text('hero', 'subheading', 'Subheading', 'string', 1);
wk6_media('hero', 'image', 'Background image', 'image', 2);
wk6_links('hero', 'cta', 'Call to action', 3, FALSE);

// Card grid: cards are other pieces of content shown as teasers.
wk6_heading('card_grid', FALSE, 0);
wk6_field('card_grid', 'cards', 'entity_reference', 'Cards (content to feature)', [
  'cardinality' => -1,
  'storage_settings' => ['target_type' => 'node'],
  'settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['page' => 'page', 'news' => 'news', 'event' => 'event']]],
  'widget' => 'entity_reference_autocomplete',
  'formatter' => 'entity_reference_entity_view',
  'formatter_settings' => ['view_mode' => 'teaser'],
  'weight' => 1,
]);

// Audience pathways and quick links.
wk6_heading('audience_pathways', FALSE, 0);
wk6_links('audience_pathways', 'links', 'Pathways (title + link)', 1);
wk6_heading('quick_links', FALSE, 0);
wk6_links('quick_links', 'links', 'Links (title + link)', 1);

// Accordion: summary = heading, text = panel body.
wk6_heading('accordion', FALSE, 0);
wk6_field('accordion', 'items', 'text_with_summary', 'Items (summary = question, body = answer)', [
  'cardinality' => -1,
  'widget' => 'text_textarea_with_summary',
  'formatter' => 'waukee_accordion',
  'weight' => 1,
]);

// Callout.
wk6_heading('callout', FALSE, 0);
wk6_text('callout', 'body_text', 'Text', 'text_long', 1, TRUE);
wk6_field('callout', 'style', 'list_string', 'Style', [
  'required' => TRUE,
  'storage_settings' => ['allowed_values' => ['info' => 'Information', 'warning' => 'Warning', 'success' => 'Success']],
  'default' => [['value' => 'info']],
  'widget' => 'options_select',
  'formatter' => 'list_key',
  'weight' => 2,
]);

// Image + text.
wk6_heading('image_text', FALSE, 0);
wk6_media('image_text', 'image', 'Image', 'image', 1);
wk6_text('image_text', 'body_text', 'Text', 'text_long', 2, TRUE);
wk6_field('image_text', 'image_position', 'list_string', 'Image position', [
  'required' => TRUE,
  'storage_settings' => ['allowed_values' => ['left' => 'Left', 'right' => 'Right']],
  'default' => [['value' => 'left']],
  'widget' => 'options_buttons',
  'formatter' => 'list_key',
  'weight' => 3,
]);

// Video embed (remote video media: YouTube / Vimeo).
wk6_heading('video_embed', FALSE, 0);
wk6_media('video_embed', 'video', 'Video', 'remote_video', 1);
wk6_text('video_embed', 'caption', 'Caption / transcript link', 'string', 2);

// Emergency alert (rendering rules arrive in Phase 11).
wk6_text('emergency_alert', 'message', 'Message', 'text_long', 0, TRUE);
wk6_field('emergency_alert', 'active', 'boolean', 'Active', [
  'storage_settings' => [],
  'settings' => ['on_label' => 'Active', 'off_label' => 'Inactive'],
  'default' => [['value' => 0]],
  'widget' => 'boolean_checkbox',
  'widget_settings' => ['display_label' => TRUE],
  'formatter' => 'boolean',
  'formatter_settings' => ['format' => 'default'],
  'weight' => 1,
]);
wk6_links('emergency_alert', 'link', 'More information link', 2, FALSE, FALSE);

// ---------------------------------------------------------------------------
// 2. Teaser displays used by the card grid and feeds.
// ---------------------------------------------------------------------------
$teasers = [
  'news' => ['field_image' => ['entity_reference_entity_view', ['view_mode' => 'default'], -2], 'field_summary' => ['text_default', [], 1]],
  'event' => ['field_when' => ['smartdate_default', [], 0], 'field_location' => ['string', [], 1]],
  'page' => ['body' => ['text_summary_or_trimmed', ['trim_length' => 200], 0]],
];
foreach ($teasers as $bundle => $components) {
  $d = EntityViewDisplay::load("node.$bundle.teaser") ?: EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => 'teaser', 'status' => TRUE]);
  foreach ($d->getComponents() as $name => $c) {
    if ($name !== 'links') {
      $d->removeComponent($name);
    }
  }
  foreach ($components as $field => [$formatter, $settings, $weight]) {
    $d->setComponent($field, ['type' => $formatter, 'label' => 'hidden', 'settings' => $settings, 'weight' => $weight]);
  }
  $d->save();
}

// ---------------------------------------------------------------------------
// 3. Views blocks: news feed and upcoming events.
// ---------------------------------------------------------------------------
$base_filter_status = ['id' => 'status', 'table' => 'node_field_data', 'field' => 'status', 'value' => '1', 'group' => 1, 'plugin_id' => 'boolean', 'entity_type' => 'node', 'entity_field' => 'status'];
$make_view = function (string $id, string $label, string $bundle, array $sorts, array $extra_filters, string $block_desc, string $title, string $empty) use ($base_filter_status) {
  if (View::load($id)) {
    return;
  }
  $filters = [
    'status' => $base_filter_status,
    'type' => ['id' => 'type', 'table' => 'node_field_data', 'field' => 'type', 'value' => [$bundle => $bundle], 'group' => 1, 'plugin_id' => 'bundle', 'entity_type' => 'node', 'entity_field' => 'type'],
  ] + $extra_filters;
  View::create([
    'id' => $id,
    'label' => $label,
    'module' => 'views',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'display' => [
      'default' => [
        'id' => 'default',
        'display_title' => 'Default',
        'display_plugin' => 'default',
        'position' => 0,
        'display_options' => [
          'title' => $title,
          'row' => ['type' => 'entity:node', 'options' => ['view_mode' => 'teaser']],
          'style' => ['type' => 'default', 'options' => ['row_class' => 'wk-feed__item']],
          'pager' => ['type' => 'some', 'options' => ['items_per_page' => 5, 'offset' => 0]],
          'sorts' => $sorts,
          'filters' => $filters,
          'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
          'cache' => ['type' => 'tag', 'options' => []],
          'query' => ['type' => 'views_query', 'options' => []],
          'empty' => ['area_text_custom' => ['id' => 'area_text_custom', 'table' => 'views', 'field' => 'area_text_custom', 'plugin_id' => 'text_custom', 'empty' => TRUE, 'content' => $empty]],
          'use_ajax' => FALSE,
          'display_extenders' => [],
        ],
      ],
      'block_1' => [
        'id' => 'block_1',
        'display_title' => 'Block',
        'display_plugin' => 'block',
        'position' => 1,
        'display_options' => ['block_description' => $block_desc, 'block_category' => 'Lists (Views)', 'allow' => ['items_per_page' => TRUE], 'display_extenders' => []],
      ],
    ],
  ])->save();
  echo "Created view $id\n";
};

$make_view('news_feed', 'News feed', 'news', [
  'created' => ['id' => 'created', 'table' => 'node_field_data', 'field' => 'created', 'order' => 'DESC', 'plugin_id' => 'date', 'entity_type' => 'node', 'entity_field' => 'created', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'exposed' => FALSE, 'expose' => ['label' => ''], 'granularity' => 'second'],
], [], 'News feed', 'Latest news', 'No news yet.');

$make_view('events', 'Events', 'event', [
  'field_when_value' => ['id' => 'field_when_value', 'table' => 'node__field_when', 'field' => 'field_when_value', 'order' => 'ASC', 'plugin_id' => 'standard', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'exposed' => FALSE, 'expose' => ['label' => '']],
], [
  'field_when_end_value' => ['id' => 'field_when_end_value', 'table' => 'node__field_when', 'field' => 'field_when_end_value', 'operator' => '>=', 'value' => ['type' => 'offset', 'value' => 'now', 'min' => '', 'max' => ''], 'group' => 1, 'plugin_id' => 'date', 'relationship' => 'none', 'exposed' => FALSE],
], 'Upcoming events', 'Upcoming events', 'No upcoming events.');

// ---------------------------------------------------------------------------
// 4. Layout Builder on Basic page, with per-node overrides.
// ---------------------------------------------------------------------------
$display = LayoutBuilderEntityViewDisplay::load('node.page.default');
$display->enableLayoutBuilder()->setOverridable(TRUE)->save();
echo "Layout Builder enabled on page (per-node layouts: yes)\n";

// ---------------------------------------------------------------------------
// 5. Layout Builder Restrictions: editors only see our components.
// ---------------------------------------------------------------------------
$block_manager = \Drupal::service('plugin.manager.block');
$our_categories = ['Inline blocks', 'Lists (Views)'];
$allow = [
  'Inline blocks' => array_map(fn($id) => "inline_block:$id", array_keys($block_types)),
  'Lists (Views)' => ['views_block:news_feed-block_1', 'views_block:events-block_1'],
];
$all_categories = [];
foreach ($block_manager->getDefinitions() as $def) {
  $all_categories[(string) $def['category']] = (string) $def['category'];
}
$restricted = array_values(array_diff(array_keys($all_categories), $our_categories));

$display = LayoutBuilderEntityViewDisplay::load('node.page.default');
$display->setThirdPartySetting('layout_builder_restrictions', 'allowed_block_categories', []);
$display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
  'allowed_layouts' => ['layout_onecol', 'layout_twocol_section', 'layout_threecol_section'],
  'allowlisted_blocks' => $allow,
  'restricted_categories' => $restricted,
]);
$display->save();
echo "Restrictions: " . count($restricted) . " categories blocked; allowed: " . implode(' + ', $our_categories) . "\n";

// ---------------------------------------------------------------------------
// 6. Permissions for editors.
// ---------------------------------------------------------------------------
$known = array_keys(\Drupal::service('user.permissions')->getPermissions());
foreach (['school_editor', 'publisher', 'district_admin'] as $rid) {
  $role = Role::load($rid);
  foreach (['configure editable page node layout overrides', 'create and edit custom blocks'] as $p) {
    if (in_array($p, $known, TRUE)) {
      $role->grantPermission($p);
    }
    else {
      echo "  (unknown permission $p)\n";
    }
  }
  $role->save();
}
echo "Phase 6 script complete.\n";
