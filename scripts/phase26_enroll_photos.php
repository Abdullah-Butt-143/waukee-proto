<?php

/**
 * @file
 * Phase 26: replace the Enrollment page's placeholder artwork with real,
 * openly-licensed photos (scripts/assets/enroll-hero.jpg, enroll-art.jpg).
 * Re-runnable. Updates the hero and "Enrollment Timeline" image_text blocks
 * of /enrollment in place; phase22_enroll.php would restore the placeholder
 * artwork if it is run again (same pattern as phase23_about_hero.php).
 *
 * Both photos are CC BY 2.0 (Flickr, via Openverse) and need attribution:
 * - enroll-hero.jpg: "student_ipad_school - 166" by flickingerbrad
 *   https://www.flickr.com/photos/56155476@N08/6660091767
 * - enroll-art.jpg: "Kids painting" by BarelyFitz
 *   https://www.flickr.com/photos/43935390809@N01/16154197
 * Both licensed under CC BY 2.0: https://creativecommons.org/licenses/by/2.0/
 *
 * Run: ddev drush php:script scripts/phase26_enroll_photos.php
 */

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;

$etm = \Drupal::entityTypeManager();

$photo = function (string $name, string $asset, string $alt) use ($etm) {
  $found = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $name]);
  if ($found) {
    return reset($found);
  }
  $dir = 'public://sample';
  \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
  $data = file_get_contents(DRUPAL_ROOT . "/../scripts/assets/$asset");
  $file = \Drupal::service('file.repository')->writeData($data, "$dir/$name.jpg", FileExists::Replace);
  $file->setPermanent();
  $file->save();
  $media = Media::create(['bundle' => 'image', 'name' => $name, 'status' => 1, 'uid' => 1,
    'field_media_image' => ['target_id' => $file->id(), 'alt' => $alt]]);
  $media->save();
  echo "Created media: $name\n";
  return $media;
};

$hero_media = $photo('enroll-hero', 'enroll-hero.jpg', 'Students with tablets in a classroom');
$art_media = $photo('enroll-art', 'enroll-art.jpg', 'Kids painting in an art class');

$nodes = $etm->getStorage('node')->loadByProperties(['type' => 'page', 'title' => 'Enrollment']);
if (!$nodes) {
  echo "Enrollment page not found.\n";
  return;
}
$node = reset($nodes);
$done = [];
foreach ($node->get('layout_builder__layout') as $delta) {
  foreach ($delta->section->getComponents() as $component) {
    $conf = $component->get('configuration');
    $id = $conf['id'] ?? '';
    $media = $id === 'inline_block:hero' ? $hero_media : ($id === 'inline_block:image_text' ? $art_media : NULL);
    if (!$media) {
      continue;
    }
    $block = $etm->getStorage('block_content')->loadRevision($conf['block_revision_id']);
    if ($block) {
      $block->set('field_image', ['target_id' => $media->id()]);
      $block->setNewRevision(FALSE);
      $block->save();
      $done[] = $id;
    }
  }
}
echo $done ? 'Enrollment photos updated: ' . implode(', ', $done) . "\n" : "No matching blocks found on Enrollment.\n";
drupal_flush_all_caches();
echo "Done.\n";
