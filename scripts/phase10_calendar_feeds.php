<?php

/**
 * @file
 * Phase 10: calendar + event list with school filter, district items on every
 * school site, per-school feed blocks, lunch menu view and sample menus.
 *
 * Needs the waukee_core module (school filter plugin). Re-runnable.
 * Run: ddev drush php:script scripts/phase10_calendar_feeds.php
 */

use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\node\Entity\Node;
use Drupal\views\Entity\View;

\Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();
\Drupal::service('views.views_data')->clear();
if (!\Drupal::service('plugin.manager.views.filter')->hasDefinition('waukee_school')) {
  throw new \RuntimeException('Filter plugin waukee_school not found. Run `ddev drush cr` and retry.');
}

$etm = \Drupal::entityTypeManager();
$term_id = function (string $name) use ($etm) {
  $t = $etm->getStorage('taxonomy_term')->loadByProperties(['vid' => 'school', 'name' => $name]);
  return $t ? reset($t)->id() : NULL;
};
$elem = $term_id('Sample Elementary');
$high = $term_id('Sample High School');

$status = ['id' => 'status', 'table' => 'node_field_data', 'field' => 'status', 'value' => '1', 'group' => 1, 'plugin_id' => 'boolean', 'entity_type' => 'node', 'entity_field' => 'status'];
$type = fn(string $bundle) => ['id' => 'type', 'table' => 'node_field_data', 'field' => 'type', 'value' => [$bundle => $bundle], 'group' => 1, 'plugin_id' => 'bundle', 'entity_type' => 'node', 'entity_field' => 'type'];
$school_filter = function ($value, bool $exposed) {
  return [
    'id' => 'waukee_school', 'table' => 'node_field_data', 'field' => 'waukee_school', 'relationship' => 'none',
    'group_type' => 'group', 'admin_label' => '', 'operator' => '=', 'value' => $exposed ? 'All' : (string) $value,
    'group' => 1, 'exposed' => $exposed, 'plugin_id' => 'waukee_school', 'include_district' => TRUE,
    'expose' => $exposed ? [
      'operator_id' => '', 'label' => 'School', 'description' => '', 'use_operator' => FALSE, 'operator' => 'waukee_school_op',
      'operator_limit_selection' => FALSE, 'operator_list' => [], 'identifier' => 'school', 'required' => FALSE,
      'remember' => FALSE, 'multiple' => FALSE, 'remember_roles' => ['authenticated' => 'authenticated'],
    ] : ['operator_id' => '', 'label' => '', 'description' => '', 'use_operator' => FALSE, 'operator' => '', 'identifier' => '', 'required' => FALSE, 'remember' => FALSE, 'multiple' => FALSE, 'remember_roles' => ['authenticated' => 'authenticated']],
    'is_grouped' => FALSE,
    'group_info' => ['label' => '', 'description' => '', 'identifier' => '', 'optional' => TRUE, 'widget' => 'select', 'multiple' => FALSE, 'remember' => FALSE, 'default_group' => 'All', 'default_group_multiple' => [], 'group_items' => []],
  ];
};
$exposed_form = ['type' => 'basic', 'options' => ['submit_button' => 'Apply', 'reset_button' => TRUE, 'reset_button_label' => 'Reset', 'exposed_sorts_label' => 'Sort by', 'expose_sort_order' => FALSE, 'sort_asc_label' => 'Asc', 'sort_desc_label' => 'Desc']];

// ---------------------------------------------------------------------------
// 1. Per-school feed blocks on the news and events views (district items
//    included), so each school site shows its own news + district news.
// ---------------------------------------------------------------------------
$add_school_blocks = function (string $view_id, string $label_prefix) use ($school_filter, $elem, $high) {
  $view = View::load($view_id);
  foreach (['elementary' => [$elem, 'Sample Elementary'], 'high' => [$high, 'Sample High School']] as $key => [$tid, $school]) {
    $display_id = "block_$key";
    if ($view->getDisplay($display_id)) {
      continue;
    }
    $displays = $view->get('display');
    $filters = $displays['default']['display_options']['filters'];
    $filters['waukee_school'] = $school_filter($tid, FALSE);
    $displays[$display_id] = [
      'id' => $display_id, 'display_title' => "Block: $school", 'display_plugin' => 'block', 'position' => count($displays),
      'display_options' => [
        'block_description' => "$label_prefix: $school (+ district)",
        'block_category' => 'Lists (Views)',
        'defaults' => ['filters' => FALSE, 'filter_groups' => FALSE],
        'filters' => $filters,
        'filter_groups' => ['operator' => 'AND', 'groups' => [1 => 'AND']],
        'display_extenders' => [],
      ],
    ];
    $view->set('display', $displays);
    echo "  $view_id: added $display_id\n";
  }
  $view->save();
};
echo "School feed blocks:\n";
$add_school_blocks('news_feed', 'News');
$add_school_blocks('events', 'Events');

// ---------------------------------------------------------------------------
// 2. Events list page and calendar page.
// ---------------------------------------------------------------------------
$events = View::load('events');
$displays = $events->get('display');
$base_filters = ['status' => $status, 'type' => $type('event')];

if (!isset($displays['page_list'])) {
  $displays['page_list'] = [
    'id' => 'page_list', 'display_title' => 'Events list', 'display_plugin' => 'page', 'position' => count($displays),
    'display_options' => [
      'path' => 'events',
      'menu' => ['type' => 'none'],
      'title' => 'Events',
      'defaults' => ['filters' => FALSE, 'filter_groups' => FALSE, 'pager' => FALSE, 'exposed_form' => FALSE],
      'filters' => $displays['default']['display_options']['filters'] + ['waukee_school' => $school_filter('', TRUE)],
      'filter_groups' => ['operator' => 'AND', 'groups' => [1 => 'AND']],
      'pager' => ['type' => 'mini', 'options' => ['items_per_page' => 10, 'offset' => 0]],
      'exposed_form' => $exposed_form,
      'display_extenders' => [],
    ],
  ];
  echo "  events: added page_list (/events)\n";
}

// Always (re)written so style option fixes apply on re-run.
{
  $date_field = [
    'id' => 'field_when', 'table' => 'node__field_when', 'field' => 'field_when', 'relationship' => 'none', 'group_type' => 'group',
    'admin_label' => '', 'label' => 'When', 'plugin_id' => 'field', 'type' => 'smartdate_default',
    'settings' => ['timezone_override' => '', 'format' => 'default', 'force_chronological' => FALSE, 'add_classes' => FALSE, 'time_wrapper' => TRUE, 'localize' => FALSE, 'parts' => ['start', 'end'], 'join' => ['separator' => 'to']],
    'entity_type' => 'node', 'entity_field' => 'field_when', 'group_rows' => TRUE, 'multi_type' => 'separator', 'delta_limit' => 0, 'delta_offset' => 0,
    'element_label_colon' => FALSE,
  ];
  $title_field = [
    'id' => 'title', 'table' => 'node_field_data', 'field' => 'title', 'relationship' => 'none', 'group_type' => 'group',
    'admin_label' => '', 'label' => '', 'plugin_id' => 'field', 'type' => 'string', 'settings' => ['link_to_entity' => FALSE],
    'entity_type' => 'node', 'entity_field' => 'title', 'element_label_colon' => FALSE,
  ];
  $displays['page_calendar'] = [
    'id' => 'page_calendar', 'display_title' => 'Calendar', 'display_plugin' => 'page', 'position' => count($displays),
    'display_options' => [
      'path' => 'calendar',
      'menu' => ['type' => 'none'],
      'title' => 'Calendar',
      'defaults' => ['style' => FALSE, 'row' => FALSE, 'fields' => FALSE, 'filters' => FALSE, 'filter_groups' => FALSE, 'pager' => FALSE, 'sorts' => FALSE, 'exposed_form' => FALSE, 'empty' => FALSE],
      'style' => ['type' => 'fullcalendar_view_display', 'options' => [
        'start' => 'field_when', 'end' => '', 'title' => 'title', 'default_view' => 'dayGridMonth', 'default_mobile_view' => 'listMonth',
        // Must be strings (the module's own array default breaks rendering).
        'left_buttons' => 'prev,next today', 'right_buttons' => 'dayGridMonth,timeGridWeek,listMonth',
        'default_date_source' => 'now', 'nav_links' => 1, 'updateAllowed' => 0, 'updateConfirm' => 0, 'createEventLink' => 0,
        'openEntityInNewTab' => 0, 'dialogWindow' => 0, 'dialogModal' => FALSE, 'dialogCanvas' => FALSE,
      ]],
      'row' => ['type' => 'fields', 'options' => []],
      'fields' => ['title' => $title_field, 'field_when' => $date_field],
      'filters' => $base_filters + ['waukee_school' => $school_filter('', TRUE)],
      'filter_groups' => ['operator' => 'AND', 'groups' => [1 => 'AND']],
      'pager' => ['type' => 'none', 'options' => ['offset' => 0]],
      'sorts' => [],
      'exposed_form' => $exposed_form,
      'empty' => [],
      'display_extenders' => [],
    ],
  ];
  echo "  events: added page_calendar (/calendar)\n";
}
$events->set('display', $displays);
$events->save();

// ---------------------------------------------------------------------------
// 3. Lunch menu view + sample menus.
// ---------------------------------------------------------------------------
$field = fn(string $name, string $label, string $formatter, array $settings = []) => [
  'id' => $name, 'table' => "node__$name", 'field' => $name, 'relationship' => 'none', 'group_type' => 'group',
  'admin_label' => '', 'label' => $label, 'plugin_id' => 'field', 'type' => $formatter, 'settings' => $settings,
  'entity_type' => 'node', 'entity_field' => $name, 'element_label_colon' => FALSE,
];
if (!View::load('lunch_menus')) {
  View::create([
    'id' => 'lunch_menus', 'label' => 'Lunch menus', 'module' => 'views', 'base_table' => 'node_field_data', 'base_field' => 'nid',
    'display' => [
      'default' => [
        'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
        'display_options' => [
          'title' => 'Lunch menus',
          'fields' => [
            'field_menu_date' => $field('field_menu_date', 'Date', 'datetime_custom', ['timezone_override' => '', 'date_format' => 'l, M j']),
            'field_school' => $field('field_school', 'School', 'entity_reference_label', ['link' => FALSE]),
            'field_items' => $field('field_items', 'Menu', 'text_default'),
          ],
          'style' => ['type' => 'table', 'options' => [
            'caption' => 'Upcoming lunch menus', 'summary' => '', 'description' => '',
            'columns' => ['field_menu_date' => 'field_menu_date', 'field_school' => 'field_school', 'field_items' => 'field_items'],
            'default' => '-1', 'empty_table' => TRUE, 'override' => TRUE, 'sticky' => FALSE,
            'info' => array_fill_keys(['field_menu_date', 'field_school', 'field_items'], ['sortable' => FALSE, 'default_sort_order' => 'asc', 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => '']),
          ]],
          'row' => ['type' => 'fields', 'options' => []],
          'pager' => ['type' => 'mini', 'options' => ['items_per_page' => 20, 'offset' => 0]],
          'sorts' => [
            'field_menu_date_value' => ['id' => 'field_menu_date_value', 'table' => 'node__field_menu_date', 'field' => 'field_menu_date_value', 'order' => 'ASC', 'plugin_id' => 'datetime', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'exposed' => FALSE, 'expose' => ['label' => ''], 'granularity' => 'day'],
          ],
          'filters' => [
            'status' => $status,
            'type' => $type('lunch_menu'),
            'field_menu_date_value' => [
              'id' => 'field_menu_date_value', 'table' => 'node__field_menu_date', 'field' => 'field_menu_date_value', 'relationship' => 'none',
              'group_type' => 'group', 'admin_label' => '', 'operator' => '>=', 'value' => ['type' => 'offset', 'value' => 'today', 'min' => '', 'max' => ''],
              'group' => 1, 'exposed' => FALSE, 'plugin_id' => 'datetime',
            ],
            'tid' => [
              'id' => 'tid', 'table' => 'taxonomy_index', 'field' => 'tid', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '',
              'operator' => 'or', 'value' => [], 'group' => 1, 'exposed' => TRUE,
              'expose' => ['operator_id' => 'tid_op', 'label' => 'School', 'description' => '', 'use_operator' => FALSE, 'operator' => 'tid_op', 'operator_limit_selection' => FALSE, 'operator_list' => [], 'identifier' => 'school', 'required' => FALSE, 'remember' => FALSE, 'multiple' => FALSE, 'remember_roles' => ['authenticated' => 'authenticated'], 'reduce' => FALSE],
              'is_grouped' => FALSE, 'group_info' => ['label' => '', 'description' => '', 'identifier' => '', 'optional' => TRUE, 'widget' => 'select', 'multiple' => FALSE, 'remember' => FALSE, 'default_group' => 'All', 'default_group_multiple' => [], 'group_items' => []],
              'plugin_id' => 'taxonomy_index_tid', 'type' => 'select', 'limit' => TRUE, 'vid' => 'school', 'hierarchy' => FALSE, 'error_message' => TRUE, 'reduce_duplicates' => FALSE,
            ],
          ],
          'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
          'exposed_form' => $exposed_form,
          'cache' => ['type' => 'tag', 'options' => []],
          'query' => ['type' => 'views_query', 'options' => []],
          'empty' => ['area_text_custom' => ['id' => 'area_text_custom', 'table' => 'views', 'field' => 'area_text_custom', 'plugin_id' => 'text_custom', 'empty' => TRUE, 'content' => 'No upcoming menus.']],
          'use_ajax' => FALSE,
          'display_extenders' => [],
        ],
      ],
      'page_1' => [
        'id' => 'page_1', 'display_title' => 'Page', 'display_plugin' => 'page', 'position' => 1,
        'display_options' => ['path' => 'lunch-menus', 'menu' => ['type' => 'none'], 'display_extenders' => []],
      ],
    ],
  ])->save();
  echo "Created view lunch_menus (/lunch-menus)\n";
}

echo "Lunch menus (sample):\n";
$items = [
  ['Cheese pizza, garden salad, fruit cup, milk', 'Chicken wrap, carrots, apple slices, milk'],
  ['Turkey sandwich, pretzels, orange wedges, milk', 'Pasta with marinara, side salad, pear, milk'],
  ['Bean burrito, corn, melon, milk', 'Beef taco bowl, rice, fruit mix, milk'],
  ['Chicken nuggets, green beans, peaches, milk', 'Baked potato bar, broccoli, banana, milk'],
  ['Fish sticks, coleslaw, applesauce, milk', 'Veggie stir-fry, rice, pineapple, milk'],
];
$day = new DateTimeImmutable('tomorrow');
$n = 0;
while ($n < 5) {
  if ((int) $day->format('N') <= 5) {
    foreach (['Sample Elementary' => [$elem, 0], 'Sample High School' => [$high, 1]] as $school => [$tid, $col]) {
      $title = "Lunch menu {$day->format('Y-m-d')} $school";
      if (!$etm->getStorage('node')->loadByProperties(['type' => 'lunch_menu', 'title' => $title])) {
        Node::create([
          'type' => 'lunch_menu', 'title' => $title, 'uid' => 1, 'status' => 1, 'moderation_state' => 'published',
          'field_menu_date' => $day->format('Y-m-d'), 'field_school' => $tid,
          'field_items' => ['value' => '<p>' . $items[$n][$col] . '</p>', 'format' => 'basic_html'],
        ])->save();
      }
    }
    $n++;
  }
  $day = $day->modify('+1 day');
}
echo "  5 weekdays x 2 schools\n";

// ---------------------------------------------------------------------------
// 4. Allow the new blocks in Layout Builder and use them on school pages.
// ---------------------------------------------------------------------------
$display = LayoutBuilderEntityViewDisplay::load('node.page.default');
$restriction = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction');
$list = $restriction['allowlisted_blocks']['Lists (Views)'] ?? [];
foreach (['views_block:news_feed-block_elementary', 'views_block:news_feed-block_high', 'views_block:events-block_elementary', 'views_block:events-block_high'] as $id) {
  if (!in_array($id, $list, TRUE)) {
    $list[] = $id;
  }
}
$restriction['allowlisted_blocks']['Lists (Views)'] = $list;
$display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', $restriction);
$display->save();

$swap = ['Sample Elementary' => 'elementary', 'Sample High School' => 'high'];
foreach ($swap as $title => $key) {
  $node = $etm->getStorage('node')->loadByProperties(['type' => 'page', 'title' => $title]);
  if (!$node) {
    continue;
  }
  $node = reset($node);
  $sections = $node->get('layout_builder__layout')->getValue();
  $changed = FALSE;
  foreach ($node->get('layout_builder__layout') as $item) {
    /** @var \Drupal\layout_builder\Section $section */
    $section = $item->section;
    foreach ($section->getComponents() as $component) {
      $config = $component->get('configuration');
      foreach (['news_feed', 'events'] as $v) {
        if (($config['id'] ?? '') === "views_block:$v-block_1") {
          $config['id'] = "views_block:$v-block_$key";
          $component->setConfiguration($config);
          $changed = TRUE;
        }
      }
    }
  }
  if ($changed) {
    $node->save();
    echo "  $title page now uses its own school feeds\n";
  }
}

echo "Phase 10 script complete.\n";
