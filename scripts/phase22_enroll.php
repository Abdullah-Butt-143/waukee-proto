<?php

/**
 * @file
 * Phase 22: designed Enrollment page (/enrollment), built from the same page
 * builder components as the About page: hero, steps band, "what you need"
 * split, FAQ accordion, helpful-pages cards, help callout. The layout is
 * rebuilt on every run. Sample content only.
 *
 * Needs: competency_grid block type (phase 16 homepage config), sample images
 * (phase16_about.php creates sample-why-choose).
 * Run: ddev drush php:script scripts/phase22_enroll.php
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

$etm = \Drupal::entityTypeManager();
$uuid = \Drupal::service('uuid');
$usage = \Drupal::service('inline_block.usage');
$sample = ' This is sample content for the prototype; no real District data.';

$find = function (string $title) use ($etm) {
  $n = $etm->getStorage('node')->loadByProperties(['type' => 'page', 'title' => $title]);
  return $n ? reset($n) : NULL;
};
$node = $find('Enrollment');
if (!$node) {
  throw new \RuntimeException('Enrollment page not found. Run scripts/phase13a_pages.php first.');
}
$media = function (string $name) use ($etm) {
  $m = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $name]);
  return $m ? ['target_id' => reset($m)->id()] : [];
};
$items = fn(array $pairs) => array_map(fn($value, $label) => ['value' => $label, 'summary' => $value, 'format' => 'basic_html'], array_keys($pairs), array_values($pairs));
$cards = fn(array $titles) => array_values(array_filter(array_map(fn($t) => ($n = $find($t)) ? ['target_id' => $n->id()] : NULL, $titles)));

// Remove the blocks of any previous layout so they do not pile up.
foreach ($node->get('layout_builder__layout') as $delta) {
  foreach ($delta->section->getComponents() as $component) {
    $rev = $component->get('configuration')['block_revision_id'] ?? NULL;
    if ($rev && ($old = $etm->getStorage('block_content')->loadRevision($rev))) {
      $old->delete();
    }
  }
}

$one = function (string $bundle, array $values) use ($uuid, $usage, $node) {
  $block = BlockContent::create(['type' => $bundle, 'reusable' => FALSE, 'info' => $bundle . ' ' . $uuid->generate()] + $values);
  $block->save();
  $usage->addUsage($block->id(), $node);
  return (new Section('layout_onecol'))->appendComponent(new SectionComponent($uuid->generate(), 'content', [
    'id' => "inline_block:$bundle", 'label' => 'x', 'label_display' => FALSE, 'provider' => 'layout_builder',
    'view_mode' => 'full', 'block_revision_id' => $block->getRevisionId(), 'block_serialized' => NULL,
  ], ['weight' => 0]));
};

$node->set('body', ['value' => '<p>How to enroll a student in Sample Community Schools: check your attendance area, gather your documents, submit the registration form and visit your school office.' . $sample . '</p>', 'format' => 'basic_html']);
$node->set('layout_builder__layout', [
  $one('hero', [
    'field_heading' => 'Enroll in Sample Community Schools',
    'field_subheading' => 'Four simple steps to get your student registered, from checking your school to your first visit.',
    'field_image' => $media('sample-hero'),
    'field_cta' => ['uri' => 'internal:/schools', 'title' => 'Find your school'],
  ]),
  $one('competency_grid', [
    'field_heading' => 'Enrollment in four steps',
    'field_items' => $items([
      '1. Check your area' => 'Find the school that serves your address.',
      '2. Gather documents' => 'Proof of residency and immunization records.',
      '3. Submit the form' => 'Complete the registration form online or on paper.',
      '4. Visit your school' => 'Meet the office team and schedule a tour.',
    ]),
  ]),
  $one('image_text', [
    'field_heading' => 'What you will need',
    'field_body_text' => ['format' => 'basic_html', 'value' =>
      '<p>Have these ready before you start. Your school office can help if something is missing.' . $sample . '</p>'
      . '<ul><li>Proof of residency (a recent utility bill or lease)</li><li>Student birth certificate or other proof of age</li><li>Immunization records</li><li>Records from the previous school, if any</li><li>Parent or guardian photo ID</li></ul>'
      . '<p><a href="/documents/enrollment-form-sample">Open the enrollment form (sample)</a></p>'],
    'field_image' => $media('sample-why-choose'),
    'field_image_position' => 'left',
  ]),
  $one('accordion', [
    'field_heading' => 'Common questions',
    'field_items' => $items([
      'Who can enroll?' => 'Any student who lives within the district boundaries can enroll. Families outside the boundaries should contact the district office about open enrollment.',
      'When can I enroll?' => 'Registration is open all year. Enroll early in the spring for the best chance of getting your first choice of start date and schedule.',
      'Does enrollment cost anything?' => 'Enrolling is free. Some activities, lunches and supplies have separate fees; free and reduced-price meal applications are available.',
      'My student has special needs. What should I do?' => 'Tell the school office when you enroll so the team can plan supports in advance. See Special Education and Health Services for more.',
      'How do I change schools later?' => 'Contact your current school office and the district office. They will guide you through the transfer steps.',
    ]),
  ]),
  $one('card_grid', [
    'field_heading' => 'Helpful next steps',
    'field_cards' => $cards(['Our schools', 'Transportation', 'Nutrition', 'Health Services']),
  ]),
  $one('callout', [
    'field_heading' => 'Need help enrolling?',
    'field_body_text' => ['format' => 'basic_html', 'value' => '<p>Call the District Administration Office at 515-987-5161 or visit 560 SE University Ave, Waukee, IA 50263. We are happy to walk you through every step.' . $sample . '</p>'],
    'field_style' => 'info',
  ]),
]);
$node->setNewRevision(FALSE);
$node->save();
echo "Enrollment page rebuilt (/enrollment)\n";
drupal_flush_all_caches();
echo "Done.\n";
