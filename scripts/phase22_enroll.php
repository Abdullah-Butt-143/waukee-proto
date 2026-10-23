<?php

/**
 * @file
 * Phase 22: designed Enrollment page (/enrollment), built from the same page
 * builder components as the About page: hero, steps band, required-documents
 * accordion, timeline split, helpful-pages cards, help callout. The layout is
 * rebuilt on every run. Wording is taken verbatim from the real
 * waukeeschools.org/enroll page (scraped 2026-10-23); the Required Documents
 * accordion only exposed its header labels on that page (bodies were
 * collapsed), so those descriptions are a generic placeholder, not real copy.
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

$node->set('body', ['value' => '<p>Welcome to Waukee Community School District! Our students have all the opportunities of a large district and the small-town feel of a close, supportive community. We\'re excited to welcome your family and help your child succeed. Find everything you need below to get started.</p>', 'format' => 'basic_html']);
$node->set('layout_builder__layout', [
  $one('hero', [
    'field_heading' => 'Enroll Now',
    'field_subheading' => 'Now enrolling for the 2026-27 school year! Enrollment for 2027-28 school year begins in January 2027.',
    'field_image' => $media('sample-hero'),
    'field_cta' => ['uri' => 'internal:/schools', 'title' => 'Find my school'],
  ]),
  $one('competency_grid', [
    'field_heading' => 'Ready to Enroll?',
    'field_items' => $items([
      'Step 1: Gather Your Documents' => 'Before you begin, gather the required documents listed below. You will be asked to submit these documents as part of the pre-enrollment application.',
      'Step 2: Pre-Enroll Your Student' => 'Complete the online pre-enrollment application and upload your required documents.',
      'Step 3: Complete Enrollment' => 'After our team reviews your pre-enrollment form and verifies your student\'s age and residency, you will receive an email (typically within 3-5 business days) with a link to complete enrollment. The enrollment form will ask for emergency contacts, allergy and health information, and other important details that will help us support your student.',
    ]),
  ]),
  $one('accordion', [
    'field_heading' => 'Required Documents',
    'field_items' => $items([
      'Proof of Residency (Required)' => 'Details available during pre-enrollment.',
      'Immunization Record (Required)' => 'Details available during pre-enrollment.',
      'Open Enrollment Application (If Applicable)' => 'Details available during pre-enrollment.',
      'Health Screening Documents (Requested)' => 'Details available during pre-enrollment.',
    ]),
  ]),
  $one('image_text', [
    'field_heading' => 'Enrollment Timeline',
    'field_body_text' => ['format' => 'basic_html', 'value' =>
      '<p>Here are important dates and next steps as your family prepares for the school year.</p>'
      . '<ul>'
      . '<li>January: New student enrollment opens</li>'
      . '<li>January: Kindergarten Signing Day</li>'
      . '<li>March 1: Open enrollment deadline for incoming grades 1-12</li>'
      . '<li>Spring: Registration opens for Beyond the Bell child care</li>'
      . '<li>April: Preschool Parent Information Night</li>'
      . '<li>May or August: Intro to Kindergarten Night &mdash; Dates vary by school</li>'
      . '<li>July: Applications open for free or reduced-price meals</li>'
      . '<li>August: Preschool home visits</li>'
      . '<li>Shortly before school begins: Middle school/high school orientations (for 6th graders, 10th graders, and students who are new to Waukee CSD)</li>'
      . '<li>Shortly before school begins: Back-to-School Open House (all schools)</li>'
      . '<li>Late August: First day of school (preschool starts a week later than TK-12)</li>'
      . '<li>Sept. 1: Open enrollment deadline for incoming TK and kindergarten students</li>'
      . '</ul>'],
    'field_image' => $media('sample-why-choose'),
    'field_image_position' => 'left',
  ]),
  $one('card_grid', [
    'field_heading' => 'Explore Waukee CSD',
    'field_cards' => $cards(['Our schools', 'Transportation', 'Nutrition', 'Health Services']),
  ]),
  $one('callout', [
    'field_heading' => "We're Here to Help",
    'field_body_text' => ['format' => 'basic_html', 'value' =>
      '<p>We know enrollment can come with questions, and our team is here to make the process as easy as possible. Please contact us or stop by the District Office. We look forward to welcoming your family to Waukee CSD!</p>'
      . '<p><strong>Enrollment Services Department</strong><br>'
      . '<a href="mailto:enrollment@waukeeschools.org">enrollment@waukeeschools.org</a><br>'
      . '515-987-2706</p>'
      . '<p>Visit us Monday&ndash;Friday from 7:30 a.m.&ndash;4:30 p.m.</p>'
      . '<p>Waukee CSD District Office<br>560 SE University Avenue<br>Waukee, Iowa</p>'],
    'field_style' => 'info',
  ]),
]);
$node->setNewRevision(FALSE);
$node->save();
echo "Enrollment page rebuilt (/enrollment)\n";
drupal_flush_all_caches();
echo "Done.\n";
