<?php

/**
 * @file
 * Phase 15b: put "Schools" in the header (audience menu), remove it from main.
 * Run: ddev drush php:script scripts/phase15b_menu.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
foreach ($storage->loadByProperties(['menu_name' => 'main', 'link__uri' => 'internal:/schools']) as $l) {
  $l->delete();
}
foreach ($storage->loadByProperties(['menu_name' => 'audience']) as $l) {
  echo $l->label() . ' w=' . $l->getWeight() . ' ' . $l->link->uri . "\n";
}
if (!$storage->loadByProperties(['menu_name' => 'audience', 'link__uri' => 'internal:/schools'])) {
  MenuLinkContent::create(['title' => 'Schools', 'menu_name' => 'audience', 'link' => ['uri' => 'internal:/schools'], 'weight' => -20])->save();
}
drupal_flush_all_caches();
