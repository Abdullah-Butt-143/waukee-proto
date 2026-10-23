<?php

/**
 * @file
 * Phase 24: replace the sample artwork on the "Explore the District" cards
 * (/district) with photos from scripts/assets/district/. Re-runnable.
 * phase16_about.php only fills empty card images, so it will not undo this.
 *
 * Run: ddev drush php:script scripts/phase24_about_cards.php
 */

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;

$etm = \Drupal::entityTypeManager();
$cards = [
  'Mission and vision' => ['mission-vision', 'Waukee Community School campus'],
  'Superintendent' => ['superintendent', 'Waukee CSD staff and administrators'],
  'Strategic plan' => ['strategic-plan', 'Students presenting a prototype at the Innovation and Learning Center'],
  'District facts' => ['district-facts', 'Waukee High School graduates celebrating'],
  'Board of Education' => ['board-of-education', 'Students recognized in the school library'],
  'Our schools' => ['our-schools', 'Elementary students learning together'],
];

$dir = 'public://sample';
\Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);

foreach ($cards as $title => [$slug, $alt]) {
  $name = "district-card-$slug";
  $found = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $name]);
  if ($found) {
    $media = reset($found);
  }
  else {
    $data = file_get_contents(DRUPAL_ROOT . "/../scripts/assets/district/$slug.jpg");
    $file = \Drupal::service('file.repository')->writeData($data, "$dir/$name.jpg", FileExists::Replace);
    $file->setPermanent();
    $file->save();
    $media = Media::create(['bundle' => 'image', 'name' => $name, 'status' => 1, 'uid' => 1,
      'field_media_image' => ['target_id' => $file->id(), 'alt' => $alt]]);
    $media->save();
  }
  $nodes = $etm->getStorage('node')->loadByProperties(['type' => 'page', 'title' => $title]);
  if (!$nodes) {
    echo "  not found: $title\n";
    continue;
  }
  $node = reset($nodes);
  $node->set('field_image', ['target_id' => $media->id()]);
  $node->setNewRevision(FALSE);
  $node->save();
  echo "  image set: $title\n";
}
drupal_flush_all_caches();
echo "Done.\n";
