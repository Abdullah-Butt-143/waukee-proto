<?php

/**
 * @file
 * Phase 16: homepage redesign. Adds the component types needed for the new
 * sections (stat strip, "why choose us" split, photo card grid, portrait of
 * a graduate) and the sample content/pages that back them, then inserts the
 * new sections into the existing Home page layout around the current
 * hero/news/events/quick-links sections (left untouched).
 *
 * Re-runnable. Run: ddev drush php:script scripts/phase16_homepage.php
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

$etm = \Drupal::entityTypeManager();
$uuid = \Drupal::service('uuid');
$find = function (string $type, string $title) use ($etm) {
  $n = $etm->getStorage('node')->loadByProperties(['type' => $type, 'title' => $title]);
  return $n ? reset($n) : NULL;
};

// ---------------------------------------------------------------------------
// Field helpers (same pattern as scripts/phase6_layout_builder.php).
// ---------------------------------------------------------------------------
function wk16_field(string $bundle, string $name, string $type, string $label, array $o = []): void {
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
function wk16_heading(string $bundle, int $weight = 0): void {
  wk16_field($bundle, 'heading', 'string', 'Heading', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => $weight]);
}
function wk16_link(string $bundle, string $name, string $label, int $weight): void {
  wk16_field($bundle, $name, 'link', $label, [
    'storage_settings' => ['link_type' => 17, 'title' => 2],
    'widget' => 'link_default',
    'formatter' => 'link',
    'weight' => $weight,
  ]);
}

// ---------------------------------------------------------------------------
// 1. Block content types for the new sections.
// ---------------------------------------------------------------------------
$block_types = [
  'stat_strip' => 'Stat strip',
  'photo_card_grid' => 'Photo card grid',
  'competency_grid' => 'Competency grid',
];
foreach ($block_types as $id => $label) {
  if (!BlockContentType::load($id)) {
    BlockContentType::create(['id' => $id, 'label' => $label, 'description' => "$label component.", 'revision' => FALSE])->save();
    echo "Created block type $id\n";
  }
}

// Stat strip: summary = the number (e.g. "98%"), body = the label.
wk16_field('stat_strip', 'items', 'text_with_summary', 'Stats (summary = number, body = label)', [
  'cardinality' => -1,
  'widget' => 'text_textarea_with_summary',
  'formatter' => 'text_default',
  'weight' => 0,
]);

// Photo card grid: heading + cards (shared field_cards storage, page bundle
// only) rendered with the card_photo view mode, plus an optional "view all" link.
wk16_heading('photo_card_grid', 0);
wk16_field('photo_card_grid', 'cards', 'entity_reference', 'Cards (schools & programs)', [
  'cardinality' => -1,
  'storage_settings' => ['target_type' => 'node'],
  'settings' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['page' => 'page']]],
  'widget' => 'entity_reference_autocomplete',
  'formatter' => 'entity_reference_entity_view',
  'formatter_settings' => ['view_mode' => 'card_photo'],
  'weight' => 1,
]);
wk16_link('photo_card_grid', 'link', 'View all link', 2);

// Competency grid: heading + items (summary = title, body = description).
wk16_heading('competency_grid', 0);
wk16_field('competency_grid', 'items', 'text_with_summary', 'Competencies (summary = title, body = description)', [
  'cardinality' => -1,
  'widget' => 'text_textarea_with_summary',
  'formatter' => 'text_default',
  'weight' => 1,
]);

// ---------------------------------------------------------------------------
// 2. field_image on the page content type (reuses the shared storage already
//    used by news), plus a "card_photo" view mode for photo-overlay cards.
// ---------------------------------------------------------------------------
if (!FieldConfig::loadByName('node', 'page', 'field_image')) {
  FieldConfig::create([
    'field_name' => 'field_image',
    'entity_type' => 'node',
    'bundle' => 'page',
    'label' => 'Image',
    'settings' => ['handler' => 'default:media', 'handler_settings' => ['target_bundles' => ['image' => 'image']]],
  ])->save();
  echo "Added field_image to page\n";
}
\Drupal::service('entity_display.repository')->getFormDisplay('node', 'page')
  ->setComponent('field_image', ['type' => 'media_library_widget', 'weight' => -5])
  ->save();

if (!\Drupal::entityTypeManager()->getStorage('entity_view_mode')->load('node.card_photo')) {
  \Drupal\Core\Entity\Entity\EntityViewMode::create(['id' => 'node.card_photo', 'targetEntityType' => 'node', 'label' => 'Card (photo)'])->save();
  echo "Created node.card_photo view mode\n";
}
$card_photo = EntityViewDisplay::load('node.page.card_photo') ?: EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => 'page', 'mode' => 'card_photo', 'status' => TRUE]);
$card_photo->setComponent('field_image', ['type' => 'entity_reference_entity_view', 'label' => 'hidden', 'settings' => ['view_mode' => 'default', 'link' => FALSE], 'weight' => 0]);
$card_photo->removeComponent('body');
$card_photo->save();

// ---------------------------------------------------------------------------
// 3. Sample artwork for the pathway cards (flat GD illustrations, same style
//    as scripts/phase14_images.php; no third-party images).
// ---------------------------------------------------------------------------
function wk16_art(string $accent, string $base_from, string $base_to): string {
  $w = 1000;
  $h = 700;
  $im = imagecreatetruecolor($w, $h);
  for ($y = 0; $y < $h; $y++) {
    [$r1, $g1, $b1] = sscanf(ltrim($base_from, '#'), '%02x%02x%02x');
    [$r2, $g2, $b2] = sscanf(ltrim($base_to, '#'), '%02x%02x%02x');
    $t = $y / $h;
    imageline($im, 0, $y, $w, $y, imagecolorallocate($im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
  }
  [$ar, $ag, $ab] = sscanf(ltrim($accent, '#'), '%02x%02x%02x');
  $acc = imagecolorallocatealpha($im, $ar, $ag, $ab, 0);
  $white = imagecolorallocatealpha($im, 255, 255, 255, 100);
  imagefilledellipse($im, (int) ($w * 0.8), (int) ($h * 0.25), 360, 360, $acc);
  imagefilledellipse($im, (int) ($w * 0.15), (int) ($h * 0.85), 300, 300, $white);
  ob_start();
  imagepng($im, NULL, 8);
  return ob_get_clean();
}
function wk16_media(string $name, string $alt, string $png): int {
  $etm = \Drupal::entityTypeManager();
  $dir = 'public://sample';
  \Drupal::service('file_system')->prepareDirectory($dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
  $uri = "$dir/$name.png";
  $file = \Drupal::service('file.repository')->writeData($png, $uri, \Drupal\Core\File\FileExists::Replace);
  $file->setPermanent();
  $file->save();
  $found = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $name]);
  if ($found) {
    $media = reset($found);
    $media->set('field_media_image', ['target_id' => $file->id(), 'alt' => $alt, 'width' => 1000, 'height' => 700]);
  }
  else {
    $media = Media::create(['bundle' => 'image', 'name' => $name, 'status' => 1, 'uid' => 1,
      'field_media_image' => ['target_id' => $file->id(), 'alt' => $alt, 'width' => 1000, 'height' => 700]]);
  }
  $media->save();
  return (int) $media->id();
}

// ---------------------------------------------------------------------------
// 4. Pathway pages (photo card grid) and support resource pages (card grid).
// ---------------------------------------------------------------------------
$make_page = function (string $title, string $alias, string $body, ?int $media_id = NULL) use ($find) {
  if ($node = $find('page', $title)) {
    return $node;
  }
  $values = [
    'type' => 'page', 'title' => $title, 'uid' => 1, 'status' => 1, 'moderation_state' => 'published',
    'body' => ['value' => $body, 'format' => 'basic_html'],
    'path' => ['alias' => $alias, 'pathauto' => 0],
  ];
  if ($media_id) {
    $values['field_image'] = ['target_id' => $media_id];
  }
  $node = Node::create($values);
  $node->save();
  echo "  page: $title ($alias)\n";
  return $node;
};

function wk16_slug(string $s): string {
  return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $s), '-'));
}

$pathways = [
  ['Elementary Schools (K-5)', '/pathways/elementary', '10 neighborhood schools fostering literacy and joy.', '#F2A900', '#5b2c83', '#7a4aa3'],
  ['Middle Schools (6-8)', '/pathways/middle', '4 supportive campuses fostering growth and identity.', '#F1EAF8', '#3c1a5b', '#5b2c83'],
  ['9th Grade Campuses', '/pathways/ninth-grade', 'Specialized transition centers for incoming freshmen.', '#F2A900', '#4a2273', '#7a4aa3'],
  ['Comprehensive High Schools', '/pathways/high-school', 'Advanced coursework, athletics and clubs district-wide.', '#F1EAF8', '#2a1240', '#5b2c83'],
  ['Waukee APEX & Innovation', '/pathways/apex', 'Nationally-recognized work-based career learning.', '#F2A900', '#3c1a5b', '#4a2273'],
  ['Athletics & Performing Arts', '/pathways/athletics-arts', 'Championship sports, bands, choir and theatre.', '#F1EAF8', '#5b2c83', '#2a1240'],
];
$pathway_nodes = [];
foreach ($pathways as [$title, $alias, $summary, $accent, $from, $to]) {
  $media_id = wk16_media('sample-pathway-' . wk16_slug($title), $title . ' (sample artwork)', wk16_art($accent, $from, $to));
  $pathway_nodes[] = $make_page($title, $alias, "<p>$summary</p>", $media_id);
}

$why_choose_media = wk16_media('sample-why-choose', 'Students collaborating in a classroom (sample artwork)', wk16_art('#F2A900', '#3c1a5b', '#7a4aa3'));

$support_pages = [
  ['Special Education & Accommodations', '/resources/special-education', 'Individualized education plans (IEPs), 504 plans, and tailored accommodations designed for every child\'s growth.'],
  ['Counseling & Student Wellness', '/resources/counseling-wellness', 'Dedicated social-emotional learning specialists, professional school counselors, and comprehensive family support.'],
  ['Nutrition & School Menus', '/resources/nutrition', 'Chef-crafted healthy daily breakfasts and hot lunches accommodating all allergies, vegetarian diets, and dietary requirements.'],
  ['Transportation & Bus Routing', '/resources/transportation', 'Safe, GPS-tracked modern fleet transporting students daily across all district neighborhood routes.'],
];
$support_nodes = [];
foreach ($support_pages as [$title, $alias, $summary]) {
  $support_nodes[] = $make_page($title, $alias, "<p>$summary</p>");
}

// ---------------------------------------------------------------------------
// 5. Insert the new sections into the Home page layout, around the existing
//    hero/news-events/quick-links sections (left untouched).
// ---------------------------------------------------------------------------
$home = $find('page', 'Home');
if (!$home) {
  echo "Home page not found; run scripts/phase13a_pages.php first.\n";
  return;
}

$inline = function (string $bundle, array $values, int $weight, string $region = 'content') use ($uuid) {
  $block = BlockContent::create(['type' => $bundle, 'reusable' => FALSE, 'info' => $bundle . ' ' . $uuid->generate()] + $values);
  $block->save();
  return new SectionComponent($uuid->generate(), $region, [
    'id' => "inline_block:$bundle", 'label' => 'x', 'label_display' => FALSE, 'provider' => 'layout_builder',
    'view_mode' => 'full', 'block_revision_id' => $block->getRevisionId(), 'block_serialized' => NULL,
  ], ['weight' => $weight]);
};

$existing_sections = array_map(fn($item) => $item->section, iterator_to_array($home->get('layout_builder__layout')));

// ---------------------------------------------------------------------------
// 5a. Re-brand the existing hero copy (it still had the generic "Sample
//     Community Schools" placeholder text from the earlier prototype phase).
// ---------------------------------------------------------------------------
$hero_values = [
  'field_heading' => 'Empowering Futures, One Step at a Time',
  'field_subheading' => 'Inspiring over 13,000 learners across 19 world-class facilities who feel valued, challenged, and prepared to embrace tomorrow\'s opportunities.',
  'field_cta' => ['uri' => 'internal:/enrollment', 'title' => 'Enroll Now'],
];
$block_content_storage = \Drupal::entityTypeManager()->getStorage('block_content');
foreach ($existing_sections[0]->getComponents() as $component) {
  $config = $component->get('configuration');
  if (($config['id'] ?? '') === 'inline_block:hero' && !empty($config['block_revision_id'])) {
    $hero_block = $block_content_storage->loadRevision($config['block_revision_id']);
    if ($hero_block) {
      foreach ($hero_values as $field => $value) {
        $hero_block->set($field, $value);
      }
      $hero_block->setNewRevision(FALSE);
      $hero_block->save();
      echo "Hero re-branded to Waukee copy\n";
    }
  }
}

$already_done = FALSE;
foreach ($existing_sections as $section) {
  foreach ($section->getComponents() as $component) {
    if (($component->get('configuration')['id'] ?? '') === 'inline_block:stat_strip') {
      $already_done = TRUE;
      break 2;
    }
  }
}

if ($already_done) {
  echo "Homepage sections already present; skipping layout insert.\n";
}
elseif (count($existing_sections) < 3) {
  echo "Home page layout has an unexpected shape (" . count($existing_sections) . " sections); skipping layout insert.\n";
}
else {
  $items = fn(array $pairs) => array_map(fn($value, $label) => ['value' => $label, 'summary' => $value, 'format' => 'basic_html'], array_keys($pairs), array_values($pairs));

  $stat_section = (new Section('layout_onecol'))->appendComponent($inline('stat_strip', [
    'field_items' => $items(['98%' => 'Graduation Rate', '23.5' => 'Average ACT Score', '19' => 'Modern Facilities', '50+' => 'National Merit Finalists']),
  ], 0));

  $why_section = (new Section('layout_onecol'))->appendComponent($inline('image_text', [
    'field_heading' => 'Why Choose Waukee Community Schools',
    'field_body_text' => ['format' => 'basic_html', 'value' =>
      '<p>At Waukee CSD, we feel like a tight-knit community with the rich opportunities, facilities, and academic depth of one of Iowa\'s premier, fastest-growing school districts. From our innovative APEX work-based learning to dedicated faculty and comprehensive arts and athletic programs, every student is known, challenged, and supported.</p>'
      . '<ul><li>Top-tier academic excellence (23.5 avg ACT, 98% grad rate)</li><li>Nationally recognized work-based learning (Waukee APEX)</li><li>Comprehensive athletics & activities</li></ul>'],
    'field_image' => $why_choose_media ? ['target_id' => $why_choose_media] : [],
    'field_image_position' => 'left',
  ], 0));

  $schools_cards = array_map(fn($n) => ['target_id' => $n->id()], $pathway_nodes);
  $schools_section = (new Section('layout_onecol'))->appendComponent($inline('photo_card_grid', [
    'field_heading' => 'Explore Our Schools & Programs',
    'field_cards' => $schools_cards,
    'field_link' => ['uri' => 'internal:/schools', 'title' => 'View All Buildings'],
  ], 0));

  $support_cards = array_map(fn($n) => ['target_id' => $n->id()], $support_nodes);
  $support_section = (new Section('layout_onecol'))->appendComponent($inline('card_grid', [
    'field_heading' => 'Support Every Step of the Way',
    'field_cards' => $support_cards,
  ], 0));

  $portrait_section = (new Section('layout_onecol'))->appendComponent($inline('competency_grid', [
    'field_heading' => 'Portrait of a Waukee Graduate',
    'field_items' => $items([
      'Academics' => 'Knowledge & mastery',
      'Future-Ready' => 'Adaptability & tech',
      'Inquiry' => 'Critical reasoning',
      'Wellness' => 'Lifelong health',
      'Connection' => 'Community bonding',
      'Beyond High School' => 'Clear roadmap',
    ]),
  ], 0));

  $new_sections = [
    $existing_sections[0],
    $stat_section,
    $why_section,
    $schools_section,
    $support_section,
    $existing_sections[1],
    $portrait_section,
    ...array_slice($existing_sections, 2),
  ];
  $home->set('layout_builder__layout', $new_sections);
  $home->save();
  echo "Homepage sections inserted.\n";
}

drupal_flush_all_caches();
echo "Phase 16 complete.\n";
