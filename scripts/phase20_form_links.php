<?php

/**
 * @file
 * Phase 20: make the two webforms discoverable. Adds "Facility rental" and
 * "Volunteer hours" to the Quick links menu (chips under the header and the
 * footer Quick links column). Re-runnable.
 *
 * Run: ddev drush php:script scripts/phase20_form_links.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
foreach ([['Facility rental', '/community/facility-rental', 6], ['Volunteer hours', '/students/volunteer-hours', 7]] as [$title, $path, $weight]) {
  if ($storage->loadByProperties(['menu_name' => 'quick-links', 'link__uri' => "internal:$path"])) {
    continue;
  }
  MenuLinkContent::create(['title' => $title, 'menu_name' => 'quick-links', 'link' => ['uri' => "internal:$path"], 'weight' => $weight])->save();
  echo "Added quick link: $title\n";
}
drupal_flush_all_caches();
echo "Done.\n";
