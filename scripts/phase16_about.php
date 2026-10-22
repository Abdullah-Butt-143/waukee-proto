<?php

/**
 * @file
 * About the District (/district), built from the same components as the
 * redesigned home page: hero, stat strip, split "our story", photo card grid,
 * competency grid ("what we value"). Creates the sub-pages with sample art,
 * rebuilds the layout on every run, and adds the header menu link.
 * Sample content only. Re-runnable.
 *
 * Needs phase 16 (homepage) config imported: stat_strip, photo_card_grid,
 * competency_grid, node.page.card_photo.
 * Run: ddev drush php:script scripts/phase16_about.php
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\media\Entity\Media;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;

$etm = \Drupal::entityTypeManager();
$uuid = \Drupal::service('uuid');
$publisher = user_load_by_name('comms.publisher');
$review_date = date('Y-m-d', strtotime('+1 year'));
$sample = ' This is sample content for the prototype; no real District data.';

$find = function (string $title) use ($etm) {
  $n = $etm->getStorage('node')->loadByProperties(['type' => 'page', 'title' => $title]);
  return $n ? reset($n) : NULL;
};

// Sample artwork (flat GD illustration, same style as scripts/phase14_images.php).
function wk_about_media(string $name, string $alt, string $from, string $to, string $accent): int {
  $etm = \Drupal::entityTypeManager();
  $found = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $name]);
  if ($found) {
    return (int) reset($found)->id();
  }
  $w = 1000;
  $h = 700;
  $im = imagecreatetruecolor($w, $h);
  [$r1, $g1, $b1] = sscanf(ltrim($from, '#'), '%02x%02x%02x');
  [$r2, $g2, $b2] = sscanf(ltrim($to, '#'), '%02x%02x%02x');
  for ($y = 0; $y < $h; $y++) {
    $t = $y / $h;
    imageline($im, 0, $y, $w, $y, imagecolorallocate($im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
  }
  [$ar, $ag, $ab] = sscanf(ltrim($accent, '#'), '%02x%02x%02x');
  imagefilledellipse($im, (int) ($w * 0.8), (int) ($h * 0.25), 360, 360, imagecolorallocate($im, $ar, $ag, $ab));
  imagefilledellipse($im, (int) ($w * 0.15), (int) ($h * 0.85), 300, 300, imagecolorallocatealpha($im, 255, 255, 255, 100));
  ob_start();
  imagepng($im, NULL, 8);
  $png = ob_get_clean();
  $dir = 'public://sample';
  \Drupal::service('file_system')->prepareDirectory($dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
  $file = \Drupal::service('file.repository')->writeData($png, "$dir/$name.png", \Drupal\Core\File\FileExists::Replace);
  $file->setPermanent();
  $file->save();
  $media = Media::create(['bundle' => 'image', 'name' => $name, 'status' => 1, 'uid' => 1,
    'field_media_image' => ['target_id' => $file->id(), 'alt' => $alt, 'width' => $w, 'height' => $h]]);
  $media->save();
  return (int) $media->id();
}

$inline = function (string $bundle, array $values, int $weight) use ($uuid) {
  $block = BlockContent::create(['type' => $bundle, 'reusable' => FALSE, 'info' => $bundle . ' ' . $uuid->generate()] + $values);
  $block->save();
  return new SectionComponent($uuid->generate(), 'content', [
    'id' => "inline_block:$bundle", 'label' => 'x', 'label_display' => FALSE, 'provider' => 'layout_builder',
    'view_mode' => 'full', 'block_revision_id' => $block->getRevisionId(), 'block_serialized' => NULL,
  ], ['weight' => $weight]);
};
$items = fn(array $pairs) => array_map(fn($value, $label) => ['value' => $label, 'summary' => $value, 'format' => 'basic_html'], array_keys($pairs), array_values($pairs));
$p = fn(string $t) => "<p>$t</p>";

// ---------------------------------------------------------------------------
// 1. Sub-pages (with card images).
// ---------------------------------------------------------------------------
$subs = [
  ['Mission and vision', '/district/mission-vision', $p('Our mission is to ensure every student is known, challenged and supported. Our vision is a community where every graduate is ready for college, career and life.' . $sample) . '<h2>Core values</h2><ul><li>Students first</li><li>Respect and belonging</li><li>Excellence in teaching and learning</li><li>Partnership with families and community</li></ul>', ['#F2A900', '#5b2c83', '#7a4aa3']],
  ['Superintendent', '/district/superintendent', $p('A message from the Superintendent about the year ahead, our priorities and how families can get involved.' . $sample), ['#F1EAF8', '#3c1a5b', '#5b2c83']],
  ['Strategic plan', '/district/strategic-plan', $p('Our five-year strategic plan sets goals for student learning, staff support, facilities and community engagement.' . $sample) . '<h2>Goals</h2><ol><li>Strong foundations in reading and math</li><li>Pathways for every student after graduation</li><li>Great people in every role</li><li>Safe, modern learning spaces</li></ol>', ['#F2A900', '#4a2273', '#7a4aa3']],
  ['District facts', '/district/facts', $p('A quick look at the district by the numbers.' . $sample) . '<ul><li>2 schools in this prototype</li><li>Grades PK-12</li><li>Serving families across the community</li></ul>', ['#F1EAF8', '#2a1240', '#5b2c83']],
];
echo "About sub-pages:\n";
foreach ($subs as [$title, $alias, $body, $colors]) {
  $media_id = wk_about_media('sample-about-' . strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-')), "$title (sample artwork)", $colors[1], $colors[2], $colors[0]);
  if (!($node = $find($title))) {
    $node = Node::create([
      'type' => 'page', 'title' => $title, 'uid' => 1, 'status' => 1, 'moderation_state' => 'published',
      'body' => ['value' => $body, 'format' => 'basic_html'],
      'field_owner' => ['target_id' => $publisher->id()],
      'field_review_date' => $review_date,
      'path' => ['alias' => $alias, 'pathauto' => 0],
    ]);
    echo "  page: $title ($alias)\n";
  }
  if ($node->get('field_image')->isEmpty()) {
    $node->set('field_image', ['target_id' => $media_id]);
  }
  $node->save();
}
// Existing pages used as cards need an image too.
foreach ([['Board of Education', ['#F1EAF8', '#3c1a5b', '#4a2273']], ['Our schools', ['#F2A900', '#5b2c83', '#2a1240']]] as [$title, $colors]) {
  if (($node = $find($title)) && $node->get('field_image')->isEmpty()) {
    $node->set('field_image', ['target_id' => wk_about_media('sample-about-' . strtolower(str_replace(' ', '-', $title)), "$title (sample artwork)", $colors[1], $colors[2], $colors[0])]);
    $node->save();
    echo "  image added: $title\n";
  }
}

$card_titles = ['Mission and vision', 'Superintendent', 'Strategic plan', 'District facts', 'Board of Education', 'Our schools'];
$cards = [];
foreach ($card_titles as $t) {
  if ($n = $find($t)) {
    $cards[] = ['target_id' => $n->id()];
  }
}

// ---------------------------------------------------------------------------
// 2. The About page itself (layout rebuilt on every run).
// ---------------------------------------------------------------------------
$hero_img = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => 'sample-hero']);
$hero_img = $hero_img ? reset($hero_img)->id() : NULL;
$story_img = wk_about_media('sample-why-choose', 'Students collaborating in a classroom (sample artwork)', '#3c1a5b', '#7a4aa3', '#F2A900');
$intro = 'Learn who we are, what we believe and how we serve students, families and the community.';

$about = $find('About the District');
if (!$about) {
  $about = Node::create([
    'type' => 'page', 'title' => 'About the District', 'uid' => 1, 'status' => 1, 'moderation_state' => 'published',
    'body' => ['value' => $p($intro), 'format' => 'basic_html'],
    'field_owner' => ['target_id' => $publisher->id()],
    'field_review_date' => $review_date,
    'path' => ['alias' => '/district', 'pathauto' => 0],
  ]);
}
// Remove the inline blocks of the previous layout so they do not pile up.
foreach ($about->get('layout_builder__layout') as $delta) {
  foreach ($delta->section->getComponents() as $component) {
    $rev = $component->get('configuration')['block_revision_id'] ?? NULL;
    if ($rev && ($old = $etm->getStorage('block_content')->loadRevision($rev))) {
      $old->delete();
    }
  }
}

$one = fn(string $bundle, array $values) => (new Section('layout_onecol'))->appendComponent($inline($bundle, $values, 0));
$about->set('layout_builder__layout', [
  $one('hero', [
    'field_heading' => 'About the District',
    'field_subheading' => $intro,
    'field_image' => $hero_img ? ['target_id' => $hero_img] : [],
    'field_cta' => ['uri' => 'internal:/district/mission-vision', 'title' => 'Our mission'],
  ]),
  $one('stat_strip', [
    'field_items' => $items(['2' => 'Schools', 'PK-12' => 'Grades Served', '1' => 'Strong Community', '100%' => 'Students Known by Name']),
  ]),
  $one('image_text', [
    'field_heading' => 'Our story',
    'field_body_text' => ['format' => 'basic_html', 'value' =>
      '<p>Sample Community Schools serve students from preschool through grade 12. Strong teaching, caring staff and close partnerships with families define who we are.' . $sample . '</p>'
      . '<ul><li>Our mission: every student known, challenged and supported</li><li>Our vision: graduates ready for college, career and life</li><li>Our promise: a school community where everyone belongs</li></ul>'],
    'field_image' => ['target_id' => $story_img],
    'field_image_position' => 'left',
  ]),
  $one('photo_card_grid', [
    'field_heading' => 'Explore the District',
    'field_cards' => $cards,
    'field_link' => ['uri' => 'internal:/schools', 'title' => 'View All Schools'],
  ]),
  $one('competency_grid', [
    'field_heading' => 'What We Value',
    'field_items' => $items([
      'Students First' => 'Every decision starts with learners',
      'Belonging' => 'Respect for every family',
      'Excellence' => 'High expectations in every class',
      'Partnership' => 'Families and community together',
      'Integrity' => 'Open, honest and accountable',
      'Growth' => 'Always learning, always improving',
    ]),
  ]),
]);
$about->save();
echo "  layout rebuilt: About the District (/district)\n";

// ---------------------------------------------------------------------------
// 3. Header menu link (audience menu), after Schools.
// ---------------------------------------------------------------------------
$menu = $etm->getStorage('menu_link_content');
if (!$menu->loadByProperties(['menu_name' => 'audience', 'link__uri' => 'internal:/district'])) {
  MenuLinkContent::create(['title' => 'About', 'menu_name' => 'audience', 'link' => ['uri' => 'internal:/district'], 'weight' => -19])->save();
  echo "Added About menu link\n";
}
drupal_flush_all_caches();
echo "Done.\n";
