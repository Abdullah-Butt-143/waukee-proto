<?php

/**
 * @file
 * Phase 23: replace the About the District hero artwork with the students
 * photo (scripts/assets/students-hero.jpg). Re-runnable. Updates the hero
 * block of /district in place; phase16_about.php would restore the artwork if
 * it is run again.
 *
 * Run: ddev drush php:script scripts/phase23_about_hero.php
 */

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;

$etm = \Drupal::entityTypeManager();

// 1. Media item for the photo.
$name = 'students-hero';
$found = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $name]);
if ($found) {
  $media = reset($found);
}
else {
  $dir = 'public://sample';
  \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
  $data = file_get_contents(DRUPAL_ROOT . '/../scripts/assets/students-hero.jpg');
  $file = \Drupal::service('file.repository')->writeData($data, "$dir/$name.jpg", FileExists::Replace);
  $file->setPermanent();
  $file->save();
  $media = Media::create(['bundle' => 'image', 'name' => $name, 'status' => 1, 'uid' => 1,
    'field_media_image' => ['target_id' => $file->id(), 'alt' => 'Students collaborating in a Waukee classroom']]);
  $media->save();
  echo "Created media: $name\n";
}

// 2. Point the About page's hero block at it.
$nodes = $etm->getStorage('node')->loadByProperties(['type' => 'page', 'title' => 'About the District']);
if (!$nodes) {
  echo "About the District page not found.\n";
  return;
}
$about = reset($nodes);
$done = FALSE;
foreach ($about->get('layout_builder__layout') as $delta) {
  foreach ($delta->section->getComponents() as $component) {
    $conf = $component->get('configuration');
    if (($conf['id'] ?? '') !== 'inline_block:hero') {
      continue;
    }
    $block = $etm->getStorage('block_content')->loadRevision($conf['block_revision_id']);
    if ($block) {
      $block->set('field_image', ['target_id' => $media->id()]);
      $block->setNewRevision(FALSE);
      $block->save();
      $done = TRUE;
    }
  }
}
echo $done ? "About hero image updated.\n" : "No hero block found on About the District.\n";
drupal_flush_all_caches();
echo "Done.\n";
