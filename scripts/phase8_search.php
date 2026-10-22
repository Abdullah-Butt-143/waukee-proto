<?php

/**
 * @file
 * Phase 8: site search page at /search over the Search API index.
 *
 * - Index fields: title, body, summary, rendered page (so Layout Builder pages
 *   are searchable), content type, and "school" (any school field).
 * - Page /search: keyword box + "Content type" and "School" filters
 *   (drop-down filters; the Facets module is NOT installed).
 * - Permission-aware: the index's "Content access" processor applies, so
 *   anonymous visitors never see staff-only items (see Phase 2).
 *
 * Re-runnable. Run: ddev drush php:script scripts/phase8_search.php
 */

use Drupal\search_api\Entity\Index;
use Drupal\views\Entity\View;

$index = Index::load('content');
if (!$index) {
  throw new \RuntimeException('Search index "content" not found. Run phase 2 first.');
}

// ---------------------------------------------------------------------------
// 1. Extend the index.
// ---------------------------------------------------------------------------
$bundles = array_keys(\Drupal\node\Entity\NodeType::loadMultiple());
$fields = [
  'field_summary' => ['label' => 'Summary', 'datasource_id' => 'entity:node', 'property_path' => 'field_summary', 'type' => 'text'],
  'field_school' => ['label' => 'School', 'datasource_id' => 'entity:node', 'property_path' => 'field_school', 'type' => 'integer'],
  'field_target_school' => ['label' => 'Target school', 'datasource_id' => 'entity:node', 'property_path' => 'field_target_school', 'type' => 'integer'],
  'rendered_item' => ['label' => 'Rendered page', 'property_path' => 'rendered_item', 'type' => 'text', 'configuration' => [
    'roles' => ['anonymous'], 'view_mode' => ['entity:node' => array_fill_keys($bundles, 'default')],
  ]],
];
foreach ($fields as $id => $def) {
  if ($id === 'rendered_item' && $index->getField($id)) {
    // Keep the view-mode mapping current when content types change.
    $index->getField($id)->setConfiguration($def['configuration']);
  }
  if (!$index->getField($id)) {
    $field = \Drupal::getContainer()->get('search_api.fields_helper')->createField($index, $id, $def);
    $index->addField($field);
    echo "Index field: $id\n";
  }
}
// Strip markup (ids, classes) from the rendered pages before indexing.
$processors = $index->get('processor_settings');
$processors['html_filter'] = [
  'all_fields' => FALSE, 'fields' => ['body', 'field_summary', 'rendered_item'], 'title' => TRUE, 'alt' => TRUE,
  'tags' => ['h1' => 5, 'h2' => 3, 'h3' => 2, 'strong' => 2, 'b' => 2, 'em' => 1, 'u' => 1],
  'weights' => ['preprocess_index' => -15, 'preprocess_query' => -15],
];
$index->set('processor_settings', $processors);
$index->save();
$index->reindex();
$index->indexItems(-1);
echo "Indexed " . $index->getTrackerInstance()->getIndexedItemsCount() . " / " . $index->getTrackerInstance()->getTotalItemsCount() . " items\n";

// ---------------------------------------------------------------------------
// 2. The /search view.
// ---------------------------------------------------------------------------
$table = 'search_api_index_content';
$expose = fn(string $identifier, string $label) => [
  'operator_id' => $identifier . '_op', 'label' => $label, 'description' => '', 'use_operator' => FALSE,
  'operator' => $identifier . '_op', 'operator_limit_selection' => FALSE, 'operator_list' => [], 'identifier' => $identifier,
  'required' => FALSE, 'remember' => FALSE, 'multiple' => FALSE, 'remember_roles' => ['authenticated' => 'authenticated'], 'reduce' => FALSE,
];
$group_info = ['label' => '', 'description' => '', 'identifier' => '', 'optional' => TRUE, 'widget' => 'select', 'multiple' => FALSE, 'remember' => FALSE, 'default_group' => 'All', 'default_group_multiple' => [], 'group_items' => []];

if (View::load('search')) {
  View::load('search')->delete();
}
View::create([
  'id' => 'search', 'label' => 'Search', 'module' => 'search_api', 'base_table' => $table, 'base_field' => 'search_api_id',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => [
        'title' => 'Search',
        'fields' => [
          'title' => [
            'id' => 'title', 'table' => $table, 'field' => 'title', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '',
            'label' => '', 'plugin_id' => 'search_api', 'link_to_item' => TRUE, 'element_type' => 'h3', 'element_label_colon' => FALSE,
            'link_to_item' => TRUE, 'use_highlighting' => FALSE, 'multi_type' => 'separator', 'multi_separator' => ', ',
          ],
          'type' => [
            'id' => 'type', 'table' => $table, 'field' => 'type', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '',
            'label' => '', 'plugin_id' => 'search_api', 'element_label_colon' => FALSE, 'multi_type' => 'separator', 'multi_separator' => ', ',
          ],
          'search_api_excerpt' => [
            'id' => 'search_api_excerpt', 'table' => $table, 'field' => 'search_api_excerpt', 'relationship' => 'none', 'group_type' => 'group',
            'admin_label' => '', 'label' => '', 'plugin_id' => 'search_api_excerpt', 'element_label_colon' => FALSE,
          ],
        ],
        'style' => ['type' => 'default', 'options' => ['row_class' => 'wk-feed__item']],
        'row' => ['type' => 'fields', 'options' => []],
        'pager' => ['type' => 'mini', 'options' => ['items_per_page' => 10, 'offset' => 0]],
        'sorts' => [
          'search_api_relevance' => ['id' => 'search_api_relevance', 'table' => $table, 'field' => 'search_api_relevance', 'order' => 'DESC', 'plugin_id' => 'search_api', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'exposed' => FALSE, 'expose' => ['label' => '']],
        ],
        'filters' => [
          'search_api_fulltext' => [
            'id' => 'search_api_fulltext', 'table' => $table, 'field' => 'search_api_fulltext', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '',
            'operator' => 'and', 'value' => '', 'group' => 1, 'exposed' => TRUE, 'expose' => $expose('keys', 'Search'),
            'is_grouped' => FALSE, 'group_info' => $group_info, 'plugin_id' => 'search_api_fulltext', 'parse_mode' => 'terms', 'min_length' => NULL, 'fields' => [],
          ],
          'type' => [
            'id' => 'type', 'table' => $table, 'field' => 'type', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '',
            'operator' => 'or', 'value' => [], 'group' => 1, 'exposed' => TRUE, 'expose' => $expose('type', 'Content type'),
            'is_grouped' => FALSE, 'group_info' => $group_info, 'plugin_id' => 'search_api_options',
          ],
          'school' => [
            'id' => 'field_school', 'table' => $table, 'field' => 'field_school', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '',
            'operator' => 'or', 'value' => [], 'group' => 1, 'exposed' => TRUE, 'expose' => $expose('school', 'School'),
            'is_grouped' => FALSE, 'group_info' => $group_info, 'plugin_id' => 'search_api_term', 'type' => 'select', 'vid' => 'school',
            'limit' => TRUE, 'hierarchy' => FALSE, 'error_message' => TRUE,
          ],
        ],
        'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
        'exposed_form' => ['type' => 'basic', 'options' => ['submit_button' => 'Search', 'reset_button' => TRUE, 'reset_button_label' => 'Reset', 'exposed_sorts_label' => 'Sort by', 'expose_sort_order' => FALSE, 'sort_asc_label' => 'Asc', 'sort_desc_label' => 'Desc']],
        'cache' => ['type' => 'search_api_none', 'options' => []],
        'query' => ['type' => 'search_api_query', 'options' => ['bypass_access' => FALSE, 'skip_access' => FALSE, 'preserve_facet_query_args' => FALSE]],
        'empty' => ['area_text_custom' => ['id' => 'area_text_custom', 'table' => 'views', 'field' => 'area_text_custom', 'plugin_id' => 'text_custom', 'empty' => TRUE, 'content' => 'Enter words to search for, or choose a content type or school.']],
        'use_ajax' => FALSE,
        'display_extenders' => [],
      ],
    ],
    'page_1' => [
      'id' => 'page_1', 'display_title' => 'Page', 'display_plugin' => 'page', 'position' => 1,
      'display_options' => ['path' => 'search', 'menu' => ['type' => 'none'], 'display_extenders' => []],
    ],
  ],
])->save();
echo "Created view search (/search)\n";
echo "Phase 8 script complete.\n";

// A visible way in: "Search" in the quick links bar.
if (!\Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['menu_name' => 'quick-links', 'title' => 'Search'])) {
  \Drupal\menu_link_content\Entity\MenuLinkContent::create(['menu_name' => 'quick-links', 'title' => 'Search', 'link' => ['uri' => 'internal:/search'], 'weight' => 20])->save();
  echo "Added Search to the quick links bar\n";
}
