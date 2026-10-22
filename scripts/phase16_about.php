<?php

/**
 * @file
 * Phase 16: "About the District" page (/district) with sub-pages, built from
 * Layout Builder components, plus a header menu link. Sample content only.
 * Re-runnable (pages are found by title).
 *
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
$media = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => 'sample-hero']);
$img = $media ? reset($media)->id() : NULL;

$inline = function (string $bundle, array $values, int $weight) use ($uuid) {
  $block = BlockContent::create(['type' => $bundle, 'reusable' => FALSE, 'info' => $bundle . ' ' . $uuid->generate()] + $values);
  $block->save();
  return new SectionComponent($uuid->generate(), 'content', [
    'id' => "inline_block:$bundle", 'label' => 'x', 'label_display' => FALSE, 'provider' => 'layout_builder',
    'view_mode' => 'full', 'block_revision_id' => $block->getRevisionId(), 'block_serialized' => NULL,
  ], ['weight' => $weight]);
};
$make_page = function (string $title, string $alias, string $body, ?callable $layout = NULL) use ($find, $publisher, $review_date) {
  if ($node = $find($title)) {
    return $node;
  }
  $node = Node::create([
    'type' => 'page', 'title' => $title, 'uid' => 1, 'status' => 1, 'moderation_state' => 'published',
    'body' => ['value' => $body, 'format' => 'basic_html'],
    'field_owner' => ['target_id' => $publisher->id()],
    'field_review_date' => $review_date,
    'path' => ['alias' => $alias, 'pathauto' => 0],
  ]);
  if ($layout) {
    $node->set('layout_builder__layout', $layout());
  }
  $node->save();
  echo "  page: $title ($alias)\n";
  return $node;
};
$p = fn(string $t) => "<p>$t</p>";

// Sub-pages.
$subs = [
  ['Mission and vision', '/district/mission-vision', $p('Our mission is to ensure every student is known, challenged and supported. Our vision is a community where every graduate is ready for college, career and life.' . $sample) . '<h2>Core values</h2><ul><li>Students first</li><li>Respect and belonging</li><li>Excellence in teaching and learning</li><li>Partnership with families and community</li></ul>'],
  ['Superintendent', '/district/superintendent', $p('A message from the Superintendent about the year ahead, our priorities and how families can get involved.' . $sample)],
  ['Strategic plan', '/district/strategic-plan', $p('Our five-year strategic plan sets goals for student learning, staff support, facilities and community engagement.' . $sample) . '<h2>Goals</h2><ol><li>Strong foundations in reading and math</li><li>Pathways for every student after graduation</li><li>Great people in every role</li><li>Safe, modern learning spaces</li></ol>'],
  ['District facts', '/district/facts', $p('A quick look at the district by the numbers.' . $sample) . '<ul><li>2 schools in this prototype</li><li>Grades PK-12</li><li>Serving families across the community</li></ul>'],
];
echo "About sub-pages:\n";
$nodes = [];
foreach ($subs as [$title, $alias, $body]) {
  $nodes[$title] = $make_page($title, $alias, $body);
}
$cards = [];
foreach (['Mission and vision', 'Superintendent', 'Strategic plan', 'District facts', 'Board of Education', 'Our schools', 'Careers', 'Enrollment'] as $t) {
  $n = $nodes[$t] ?? $find($t);
  if ($n) {
    $cards[] = ['target_id' => $n->id()];
  }
}

$intro = 'Learn who we are, what we believe and how we serve students, families and the community.';
$about = $make_page('About the District', '/district', $p($intro), function () use ($inline, $img, $intro, $cards, $sample) {
  return [(new Section('layout_onecol'))
    ->appendComponent($inline('hero', [
      'field_heading' => 'About the District',
      'field_subheading' => $intro,
      'field_image' => $img ? ['target_id' => $img] : [],
      'field_cta' => ['uri' => 'internal:/district/mission-vision', 'title' => 'Our mission'],
    ], 0))
    ->appendComponent($inline('callout', [
      'field_heading' => 'Our mission',
      'field_body_text' => ['value' => '<p>To ensure every student is known, challenged and supported, and graduates ready for college, career and life.</p>', 'format' => 'basic_html'],
      'field_style' => 'info',
    ], 1))
    ->appendComponent($inline('image_text', [
      'field_heading' => 'Our story',
      'field_body_text' => ['value' => '<p>Sample Community Schools serve students from preschool through grade 12 across two schools. Strong teaching, caring staff and close partnerships with families define who we are.' . $sample . '</p>', 'format' => 'basic_html'],
      'field_image' => $img ? ['target_id' => $img] : [],
      'field_image_position' => 'right',
    ], 2))
    ->appendComponent($inline('card_grid', ['field_heading' => 'Explore the district', 'field_cards' => $cards], 3))];
});

// Header menu link (audience menu), after Schools.
$menu = $etm->getStorage('menu_link_content');
if (!$menu->loadByProperties(['menu_name' => 'audience', 'link__uri' => 'internal:/district'])) {
  MenuLinkContent::create(['title' => 'About', 'menu_name' => 'audience', 'link' => ['uri' => 'internal:/district'], 'weight' => -19])->save();
  echo "Added About menu link\n";
}
drupal_flush_all_caches();
echo "Done. About page: /district (node {$about->id()})\n";
