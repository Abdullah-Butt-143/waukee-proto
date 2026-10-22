<?php

/**
 * @file
 * Phase 19: repair page layouts whose inline blocks were deleted.
 *
 * Inline (non-reusable) blocks live only in the database. If they are deleted
 * (scripts/phase6_verify.php used to delete ALL of them), every layout that
 * referenced them renders empty. This script finds each component whose block
 * revision is missing, creates a replacement block with the original sample
 * content, and points the component at it. Layout structure is kept.
 * Re-runnable: pages with nothing missing are left alone.
 *
 * Run: ddev drush php:script scripts/phase19_repair_layouts.php
 * (About the District has its own script: scripts/phase16_about.php.)
 */

use Drupal\block_content\Entity\BlockContent;

$etm = \Drupal::entityTypeManager();
$bc = $etm->getStorage('block_content');
$usage = \Drupal::service('inline_block.usage');
$sample = ' This is sample content for the prototype; no real District data.';

$find = function (string $title) use ($etm) {
  $n = $etm->getStorage('node')->loadByProperties(['type' => 'page', 'title' => $title]);
  return $n ? reset($n) : NULL;
};
$media = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => 'sample-hero']);
$hero_img = $media ? ['target_id' => reset($media)->id()] : [];
$links = fn(array $pairs) => array_map(fn($t, $u) => ['title' => $t, 'uri' => $u], array_keys($pairs), array_values($pairs));
$cards = fn(array $titles) => array_values(array_filter(array_map(fn($t) => ($n = $find($t)) ? ['target_id' => $n->id()] : NULL, $titles)));

$sections = [
  'Families' => ['Everything current families need: enrolling, getting to school, meals, health and closings.', ['Enrollment', 'Transportation', 'Nutrition', 'Health Services', 'School closings']],
  'Prospective Families' => ['Thinking about joining us? Learn why families choose our schools and how to enroll.', ['Why Sample Community Schools', 'Our schools', 'Enroll in our district']],
  'Students' => ['Activities, service hours and resources for students.', ['Activities', 'Volunteer hours', 'Student resources']],
  'Careers' => ['Join our team. Explore open positions and benefits.', ['Benefits', 'Job listings']],
  'Community' => ['Facility rentals, Board of Education information and district news for neighbors.', ['Facility rentals', 'Board of Education', 'Community news']],
];

/** Original sample content for a block, by page title and block bundle. */
$content = function (string $title, string $bundle) use ($sections, $cards, $links, $hero_img, $sample) {
  if (isset($sections[$title])) {
    [$intro, $children] = $sections[$title];
    return match ($bundle) {
      'callout' => ['field_heading' => 'Overview', 'field_body_text' => ['value' => "<p>$intro</p>", 'format' => 'basic_html'], 'field_style' => 'info'],
      'card_grid' => ['field_heading' => 'In this section', 'field_cards' => $cards($children)],
      default => NULL,
    };
  }
  $form_intro = [
    'Facility rentals' => 'Request a gym, auditorium, cafeteria, classroom or athletic field. Extra questions appear depending on the room you choose.',
    'Volunteer hours' => 'Log your community service hours. Choose "Other" to describe a different activity.',
  ];
  if (isset($form_intro[$title]) && $bundle === 'callout') {
    return ['field_heading' => 'Before you start', 'field_body_text' => ['value' => '<p>' . $form_intro[$title] . '</p>', 'format' => 'basic_html'], 'field_style' => 'info'];
  }
  $blurbs = ['Sample Elementary' => 'A welcoming neighborhood school for grades K-5.', 'Sample High School' => 'Preparing students for college, careers and life.'];
  if (isset($blurbs[$title]) && $bundle === 'hero') {
    return ['field_heading' => $title, 'field_subheading' => $blurbs[$title], 'field_image' => $hero_img];
  }
  if ($title === 'Home') {
    return match ($bundle) {
      'hero' => ['field_heading' => 'Empowering Futures, One Step at a Time', 'field_subheading' => "Inspiring over 13,000 learners across 19 world-class facilities who feel valued, challenged, and prepared to embrace tomorrow's opportunities.", 'field_image' => $hero_img, 'field_cta' => ['uri' => 'internal:/enrollment', 'title' => 'Enroll Now']],
      'audience_pathways' => ['field_heading' => 'I am a...', 'field_links' => $links(['Current family' => 'internal:/families', 'Prospective family' => 'internal:/prospective-families', 'Student' => 'internal:/students', 'Community member' => 'internal:/community', 'Job seeker' => 'internal:/careers', 'Staff member' => 'internal:/user/login'])],
      'quick_links' => ['field_heading' => 'Quick links', 'field_links' => $links(['Calendar' => 'internal:/calendar', 'Lunch menus' => 'internal:/lunch-menus', 'Enrollment' => 'internal:/enrollment', 'Jobs' => 'internal:/careers/jobs', 'Transportation' => 'internal:/transportation'])],
      default => NULL,
    };
  }
  return NULL;
};

$repaired = 0;
$skipped = [];
foreach ($etm->getStorage('node')->loadMultiple() as $node) {
  if (!$node->hasField('layout_builder__layout') || $node->get('layout_builder__layout')->isEmpty()) {
    continue;
  }
  $title = $node->label();
  $sections_list = array_map(fn($item) => $item->section, iterator_to_array($node->get('layout_builder__layout')));
  $changed = FALSE;
  foreach ($sections_list as $section) {
    foreach ($section->getComponents() as $component) {
      $conf = $component->get('configuration');
      $rev = $conf['block_revision_id'] ?? NULL;
      if (!$rev || $bc->loadRevision($rev)) {
        continue;
      }
      $bundle = str_replace('inline_block:', '', $conf['id']);
      $values = $content($title, $bundle);
      if ($values === NULL) {
        $skipped[] = "$title ($bundle)";
        continue;
      }
      $block = BlockContent::create(['type' => $bundle, 'reusable' => FALSE, 'info' => "$bundle " . \Drupal::service('uuid')->generate()] + $values);
      $block->save();
      $usage->addUsage($block->id(), $node);
      $conf['block_revision_id'] = $block->getRevisionId();
      $component->setConfiguration($conf);
      $changed = TRUE;
      $repaired++;
    }
  }
  if ($changed) {
    $node->set('layout_builder__layout', $sections_list);
    $node->setNewRevision(FALSE);
    $node->save();
    echo "  repaired: $title\n";
  }
}
echo "Components repaired: $repaired\n";
if ($skipped) {
  echo "No definition for: " . implode(', ', $skipped) . "\n";
}
drupal_flush_all_caches();
echo "Done. Now run scripts/phase16_about.php for the About page.\n";
