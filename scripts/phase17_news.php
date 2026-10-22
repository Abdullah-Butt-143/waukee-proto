<?php

/**
 * @file
 * Phase 17: news listing page (/news) with a school filter, card grid and
 * pager, plus a "News" header link. Re-runnable.
 *
 * Run: ddev drush php:script scripts/phase17_news.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\views\Entity\View;

\Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();
\Drupal::service('views.views_data')->clear();

$view = View::load('news_feed');
$displays = $view->get('display');
$exposed_school = [
  'id' => 'waukee_school', 'table' => 'node_field_data', 'field' => 'waukee_school', 'relationship' => 'none',
  'group_type' => 'group', 'admin_label' => '', 'operator' => '=', 'value' => 'All',
  'group' => 1, 'exposed' => TRUE, 'plugin_id' => 'waukee_school', 'include_district' => TRUE,
  'expose' => [
    'operator_id' => '', 'label' => 'School', 'description' => '', 'use_operator' => FALSE, 'operator' => 'waukee_school_op',
    'operator_limit_selection' => FALSE, 'operator_list' => [], 'identifier' => 'school', 'required' => FALSE,
    'remember' => FALSE, 'multiple' => FALSE, 'remember_roles' => ['authenticated' => 'authenticated'],
  ],
  'is_grouped' => FALSE,
  'group_info' => ['label' => '', 'description' => '', 'identifier' => '', 'optional' => TRUE, 'widget' => 'select', 'multiple' => FALSE, 'remember' => FALSE, 'default_group' => 'All', 'default_group_multiple' => [], 'group_items' => []],
];
$displays['page_list'] = [
  'id' => 'page_list', 'display_title' => 'News list', 'display_plugin' => 'page', 'position' => count($displays),
  'display_options' => [
    'path' => 'news',
    'menu' => ['type' => 'none'],
    'title' => 'News',
    'defaults' => ['title' => FALSE, 'filters' => FALSE, 'filter_groups' => FALSE, 'pager' => FALSE, 'exposed_form' => FALSE, 'style' => FALSE, 'row' => FALSE],
    'filters' => $displays['default']['display_options']['filters'] + ['waukee_school' => $exposed_school],
    'filter_groups' => ['operator' => 'AND', 'groups' => [1 => 'AND']],
    'pager' => ['type' => 'mini', 'options' => ['items_per_page' => 9, 'offset' => 0]],
    'exposed_form' => ['type' => 'basic', 'options' => ['submit_button' => 'Apply', 'reset_button' => TRUE, 'reset_button_label' => 'Reset', 'exposed_sorts_label' => 'Sort by', 'expose_sort_order' => FALSE, 'sort_asc_label' => 'Asc', 'sort_desc_label' => 'Desc']],
    'style' => ['type' => 'default', 'options' => ['row_class' => 'wk-feed__item']],
    'row' => ['type' => 'entity:node', 'options' => ['view_mode' => 'teaser']],
    'display_extenders' => [],
  ],
];
$view->set('display', $displays);
$view->save();
echo "news_feed: page_list at /news\n";

$menu = \Drupal::entityTypeManager()->getStorage('menu_link_content');
if (!$menu->loadByProperties(['menu_name' => 'audience', 'link__uri' => 'internal:/news'])) {
  MenuLinkContent::create(['title' => 'News', 'menu_name' => 'audience', 'link' => ['uri' => 'internal:/news'], 'weight' => -18])->save();
  echo "Added News menu link\n";
}
drupal_flush_all_caches();
echo "Done.\n";
