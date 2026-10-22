<?php

/**
 * @file
 * Phase 6 verification. Builds a temporary Basic page whose layout holds a
 * hero, accordion, callout and the two Views blocks; reports what an
 * anonymous request returns; checks the Layout Builder restrictions; deletes it.
 *
 * Run: ddev drush php:script scripts/phase6_verify.php
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;

// Remove leftovers from an earlier aborted run.
foreach (\Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['title' => 'TEMP layout page']) as $old) {
  $old->delete();
}
foreach (\Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties(['reusable' => 0]) as $old) {
  $old->delete();
}

$component = function (string $uuid, string $plugin, array $config, int $weight = 0) {
  return new SectionComponent($uuid, 'content', ['id' => $plugin, 'label_display' => FALSE, 'provider' => 'layout_builder'] + $config, ['weight' => $weight]);
};
$inline = function (string $bundle, array $values, int $weight) use ($component) {
  $block = BlockContent::create(['type' => $bundle, 'reusable' => FALSE, 'info' => "temp $bundle"] + $values);
  $block->save();
  return $component(\Drupal::service('uuid')->generate(), "inline_block:$bundle", [
    'block_revision_id' => $block->getRevisionId(),
    'block_serialized' => NULL,
  ], $weight);
};

$section = new Section('layout_onecol');
$section->appendComponent($inline('hero', ['field_heading' => 'TEMPHERO Welcome to Sample District', 'field_subheading' => 'Subheading text'], 0));
$section->appendComponent($inline('accordion', ['field_heading' => 'FAQ', 'field_items' => [['value' => '<p>Answer body</p>', 'summary' => 'TEMPQUESTION', 'format' => 'basic_html']]], 1));
$section->appendComponent($inline('callout', ['field_heading' => 'Heads up', 'field_body_text' => ['value' => '<p>TEMPCALLOUT</p>', 'format' => 'basic_html'], 'field_style' => 'info'], 2));
$section->appendComponent($component(\Drupal::service('uuid')->generate(), 'views_block:news_feed-block_1', ['views_label' => '', 'items_per_page' => 'none'], 3));
$section->appendComponent($component(\Drupal::service('uuid')->generate(), 'views_block:events-block_1', ['views_label' => '', 'items_per_page' => 'none'], 4));

$node = Node::create(['type' => 'page', 'title' => 'TEMP layout page', 'uid' => 1, 'moderation_state' => 'published', 'status' => 1]);
$node->set('layout_builder__layout', [$section]);
$node->save();
echo "Temp page: /node/" . $node->id() . "\n";

// Anonymous HTTP request.
$client = \Drupal::httpClient();
try {
  $res = $client->get(\Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $node->id()], ['absolute' => TRUE])->toString(), ['http_errors' => FALSE, 'verify' => FALSE]);
  $html = (string) $res->getBody();
  echo "HTTP " . $res->getStatusCode() . "\n";
  foreach (['TEMPHERO' => 'hero', 'TEMPQUESTION' => 'accordion summary', '<details class="wk-accordion"' => 'accordion <details>', 'TEMPCALLOUT' => 'callout', 'No news yet.' => 'news feed block', 'No upcoming events.' => 'events block'] as $needle => $label) {
    printf("  %-22s %s\n", $label, str_contains($html, $needle) ? 'present' : 'MISSING');
  }
}
catch (\Throwable $e) {
  echo "HTTP check failed: " . $e->getMessage() . "\n";
}

// Restrictions: which blocks may an editor add?
$allowed = [];
$denied = [];
$section_storage = \Drupal::service('plugin.manager.layout_builder.section_storage')->load('overrides', ['entity' => \Drupal\Core\Plugin\Context\EntityContext::fromEntity($node)]);
$restriction_manager = \Drupal::service('plugin.manager.layout_builder_restriction');
$definitions = \Drupal::service('plugin.manager.block')->getDefinitions();
foreach ($restriction_manager->getDefinitions() as $pid => $def) {
  $definitions = $restriction_manager->createInstance($pid)->alterBlockDefinitions($definitions, ['section_storage' => $section_storage, 'delta' => 0, 'region' => 'content']);
}
$offered = array_keys($definitions);
echo "Blocks an editor can add (" . count($offered) . "):\n";
foreach ($offered as $id) {
  // Layout Builder's own entity-field blocks are internal to the page.
  if (!str_starts_with($id, 'field_block:') && !str_starts_with($id, 'extra_field_block:')) {
    echo "  $id\n";
  }
}
foreach (['system_branding_block', 'system_powered_by_block', 'views_block:content_recent-block_1', 'inline_block:basic'] as $id) {
  echo sprintf("  %-38s %s\n", $id, in_array($id, $offered, TRUE) ? 'OFFERED (unexpected)' : 'hidden (correct)');
}

// Clean up.
foreach (\Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties(['reusable' => 0]) as $b) {
  $b->delete();
}
$node->delete();
echo "Temp content removed.\n";
