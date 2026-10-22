<?php

/**
 * @file
 * Phase 12: SEO + accessibility.
 *
 * - Pathauto patterns per content type (+ aliases for existing content).
 * - Metatag defaults (title, description, canonical, Open Graph, Twitter).
 * - Schema.org JSON-LD: Organization (front page), Event, NewsArticle.
 * - simple_sitemap for public content (intranet types excluded) + custom links.
 * - editoria11y checker for editor roles.
 *
 * Enables sub-modules of projects already installed (no new downloads):
 * metatag_open_graph, metatag_twitter_cards, schema_organization,
 * schema_event, schema_article. Re-runnable.
 * Run: ddev drush php:script scripts/phase12_seo_a11y.php
 */

use Drupal\metatag\Entity\MetatagDefaults;
use Drupal\node\Entity\Node;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\simple_sitemap\Queue\QueueWorker;
use Drupal\user\Entity\Role;

// ---------------------------------------------------------------------------
// 0. Sub-modules.
// ---------------------------------------------------------------------------
$installer = \Drupal::service('module_installer');
$handler = \Drupal::moduleHandler();
foreach (['metatag_open_graph', 'metatag_twitter_cards', 'schema_organization', 'schema_event', 'schema_article'] as $m) {
  if (!$handler->moduleExists($m)) {
    $installer->install([$m]);
    echo "Enabled $m\n";
  }
}

// ---------------------------------------------------------------------------
// 1. Pathauto patterns.
//    Basic pages keep the hand-set aliases from the sample content script.
// ---------------------------------------------------------------------------
$patterns = [
  'news' => ['News', '/news/[node:title]'],
  'event' => ['Events', '/events/[node:title]'],
  'staff_profile' => ['Staff profiles', '/staff/[node:title]'],
  'document' => ['Documents', '/documents/[node:title]'],
  'job_posting' => ['Job postings', '/careers/jobs/[node:title]'],
  'announcement' => ['Announcements', '/intranet/announcements/[node:title]'],
  'lunch_menu' => ['Lunch menus', '/lunch-menus/[node:title]'],
  'page' => ['Basic pages (new pages without an alias)', '/[node:title]'],
];
$weight = -10;
foreach ($patterns as $bundle => [$label, $pattern]) {
  $id = "node_$bundle";
  if (!PathautoPattern::load($id)) {
    $uuid = \Drupal::service('uuid')->generate();
    PathautoPattern::create([
      'id' => $id, 'label' => $label, 'type' => 'canonical_entities:node', 'pattern' => $pattern, 'weight' => $weight++,
      'selection_criteria' => [$uuid => [
        'id' => 'entity_bundle:node', 'negate' => FALSE, 'uuid' => $uuid, 'context_mapping' => ['node' => 'node'],
        'bundles' => [$bundle => $bundle],
      ]],
      'selection_logic' => 'and', 'relationships' => [],
    ])->save();
    echo "Pathauto pattern: $bundle -> $pattern\n";
  }
}
// Aliases for existing content (not basic pages).
$generator = \Drupal::service('pathauto.generator');
$count = 0;
$nids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', array_diff(array_keys($patterns), ['page']), 'IN')->execute();
foreach (Node::loadMultiple($nids) as $node) {
  if ($generator->updateEntityAlias($node, 'bulkupdate')) {
    $count++;
  }
}
echo "Generated/updated $count aliases\n";

// ---------------------------------------------------------------------------
// 2. Metatag defaults + Schema.org.
// ---------------------------------------------------------------------------
$set_defaults = function (string $id, string $label, array $tags) {
  $d = MetatagDefaults::load($id) ?: MetatagDefaults::create(['id' => $id, 'label' => $label, 'tags' => []]);
  $d->set('tags', array_merge($d->get('tags') ?: [], $tags));
  $d->save();
  echo "Metatag defaults: $id\n";
};

$set_defaults('global', 'Global', [
  'title' => '[current-page:title] | [site:name]',
  'canonical_url' => '[current-page:url]',
  'og_site_name' => '[site:name]',
  'og_title' => '[current-page:title]',
  'og_type' => 'website',
  'og_url' => '[current-page:url]',
  'twitter_cards_type' => 'summary_large_image',
  'twitter_cards_title' => '[current-page:title]',
]);

$set_defaults('front', 'Front page', [
  'title' => '[site:name] | Schools, calendar, news and enrollment',
  'description' => 'Sample Community Schools: enrollment, calendars, news, lunch menus, careers and community information for families, students and neighbors. (Prototype with sample content.)',
  'schema_organization_type' => 'EducationalOrganization',
  'schema_organization_name' => '[site:name]',
  'schema_organization_url' => '[site:url]',
  'schema_organization_description' => 'A sample K-12 school district used to demonstrate a Drupal website and intranet.',
]);

$set_defaults('node', 'Content', [
  'title' => '[node:title] | [site:name]',
  'description' => '[node:summary]',
  'og_title' => '[node:title]',
  'og_description' => '[node:summary]',
  'twitter_cards_description' => '[node:summary]',
]);

$set_defaults('node__news', 'Content: News', [
  'description' => '[node:field_summary]',
  'og_type' => 'article',
  'og_description' => '[node:field_summary]',
  'og_image' => '[node:field_image:entity:field_media_image:entity:url]',
  'twitter_cards_image' => '[node:field_image:entity:field_media_image:entity:url]',
  'schema_article_type' => 'NewsArticle',
  'schema_article_headline' => '[node:title]',
  'schema_article_description' => '[node:field_summary]',
  'schema_article_date_published' => '[node:created:custom:c]',
  'schema_article_date_modified' => '[node:changed:custom:c]',
  'schema_article_publisher' => serialize_schema(['@type' => 'Organization', 'name' => '[site:name]', 'url' => '[site:url]']),
  'schema_article_author' => serialize_schema(['@type' => 'Organization', 'name' => '[site:name]']),
  'schema_article_image' => serialize_schema(['@type' => 'ImageObject', 'url' => '[node:field_image:entity:field_media_image:entity:url]']),
]);

$set_defaults('node__event', 'Content: Event', [
  'description' => '[node:field_location]',
  'og_type' => 'website',
  'schema_event_type' => 'EducationEvent',
  'schema_event_name' => '[node:title]',
  'schema_event_description' => '[node:title] at [node:field_location]',
  'schema_event_start_date' => '[node:waukee-event-start]',
  'schema_event_end_date' => '[node:waukee-event-end]',
  'schema_event_location' => serialize_schema(['@type' => 'Place', 'name' => '[node:field_location]']),
  'schema_event_organizer' => serialize_schema(['@type' => 'Organization', 'name' => '[site:name]', 'url' => '[site:url]']),
]);

// Staff-facing content should not be indexed by search engines.
foreach (['announcement' => 'Announcement', 'staff_profile' => 'Staff profile'] as $bundle => $label) {
  if ($bundle === 'staff_profile') {
    continue;
  }
  $set_defaults("node__$bundle", "Content: $label", ['robots' => 'noindex, nofollow']);
}

// ---------------------------------------------------------------------------
// 3. Sitemap: public content types only.
// ---------------------------------------------------------------------------
$generator_service = \Drupal::service('simple_sitemap.generator');
$em = $generator_service->entityManager();
$em->enableEntityType('node');
foreach (['page' => 0.7, 'news' => 0.6, 'event' => 0.6, 'job_posting' => 0.5] as $bundle => $priority) {
  $em->setBundleSettings('node', $bundle, ['index' => TRUE, 'priority' => $priority, 'changefreq' => 'weekly']);
}
foreach (['announcement', 'document', 'staff_profile', 'lunch_menu'] as $bundle) {
  $em->setBundleSettings('node', $bundle, ['index' => FALSE]);
}
$links = \Drupal::service('simple_sitemap.generator')->customLinkManager();
foreach (['/calendar' => 0.8, '/events' => 0.7, '/lunch-menus' => 0.6, '/staff-directory' => 0.4] as $path => $priority) {
  try {
    $links->add($path, ['priority' => $priority, 'changefreq' => 'daily']);
  }
  catch (\Throwable $e) {
    echo "  (custom link $path: {$e->getMessage()})\n";
  }
}
$generator_service->rebuildQueue()->generate(QueueWorker::GENERATE_TYPE_BACKEND);
echo "Sitemap generated\n";

// ---------------------------------------------------------------------------
// 4. Editoria11y for editors; anonymous visitors never get the checker.
// ---------------------------------------------------------------------------
$grant = function (string $rid, array $perms) {
  $role = Role::load($rid);
  $known = array_keys(\Drupal::service('user.permissions')->getPermissions());
  foreach ($perms as $p) {
    in_array($p, $known, TRUE) ? $role->grantPermission($p) : print("  (unknown permission $p)\n");
  }
  $role->save();
};
$grant('school_editor', ['view editoria11y checker']);
$grant('publisher', ['view editoria11y checker', 'mark as ok in editoria11y', 'mark as hidden in editoria11y', 'manage editoria11y results']);
$grant('district_admin', ['view editoria11y checker', 'mark as ok in editoria11y', 'mark as hidden in editoria11y', 'manage editoria11y results']);
echo "Editoria11y granted to school_editor, publisher, district_admin\n";

echo "Phase 12 script complete.\n";

/**
 * Schema.org complex values are stored as serialized arrays in metatag config.
 */
function serialize_schema(array $value): string {
  return serialize($value);
}
