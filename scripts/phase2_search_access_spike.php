<?php

/**
 * @file
 * Phase 2: search access spike.
 *
 * Creates a minimal "intranet" group type + "Staff Intranet" group, a staff-only
 * Document inside it, a public Document outside it, a Search API DB server and
 * content index (with the "Content access" processor), then reports whether
 * anonymous / member / admin queries for "handbook" return the staff document.
 *
 * Re-runnable. Run with: ddev drush php:script scripts/phase2_search_access_spike.php
 */

use Drupal\file\Entity\File;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupRelationshipType;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupType;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\Core\Session\AnonymousUserSession;

$etm = \Drupal::entityTypeManager();

// ---------------------------------------------------------------------------
// 1. Group type "intranet", relation plugin for Document, group.
// ---------------------------------------------------------------------------
if (!GroupType::load('intranet')) {
  GroupType::create([
    'id' => 'intranet',
    'label' => 'Intranet',
    'description' => 'Staff-only space. Content is visible to members only.',
    'creator_membership' => FALSE,
  ])->save();
  echo "Created group type intranet\n";
}
$group_type = GroupType::load('intranet');

$plugin_id = 'group_node:document';
if (!GroupRelationshipType::load($etm->getStorage('group_relationship_type')->getRelationshipTypeId('intranet', $plugin_id))) {
  $etm->getStorage('group_relationship_type')->save(
    $etm->getStorage('group_relationship_type')->createFromPlugin($group_type, $plugin_id)
  );
  echo "Enabled $plugin_id on intranet\n";
}

// Members may view group documents; outsiders / anonymous get nothing.
$member_role = GroupRole::load('intranet-member');
if ($member_role) {
  $member_role->grantPermissions(['view group_node:document entity', 'view group_node:document relationship'])->save();
}
echo "Group roles on intranet: " . implode(', ', array_keys(GroupRole::loadMultiple())) . "\n";

$groups = $etm->getStorage('group')->loadByProperties(['label' => 'Staff Intranet']);
if (!$groups) {
  $group = Group::create(['type' => 'intranet', 'label' => 'Staff Intranet', 'uid' => 1]);
  $group->save();
  echo "Created group Staff Intranet\n";
}
else {
  $group = reset($groups);
}

// ---------------------------------------------------------------------------
// 2. Sample documents (file media + node).
// ---------------------------------------------------------------------------
function wk_doc_media(string $name, string $text) {
  $found = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['bundle' => 'document', 'name' => $name]);
  if ($found) {
    return reset($found);
  }
  $dir = 'public://sample';
  \Drupal::service('file_system')->prepareDirectory($dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
  $file = \Drupal::service('file.repository')->writeData($text, $dir . '/' . preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) . '.txt', \Drupal\Core\File\FileExists::Replace);
  $file->setPermanent();
  $file->save();
  $media = Media::create(['bundle' => 'document', 'name' => $name, 'status' => 1, 'field_media_document' => ['target_id' => $file->id()]]);
  $media->save();
  return $media;
}

function wk_term_id(string $vid, string $name) {
  $t = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $name]);
  return $t ? reset($t)->id() : NULL;
}

function wk_doc_node(string $title, string $level, string $body) {
  $found = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'document', 'title' => $title]);
  if ($found) {
    return reset($found);
  }
  $media = wk_doc_media($title, $body);
  $node = Node::create([
    'type' => 'document',
    'title' => $title,
    'status' => 1,
    'uid' => 1,
    'field_file' => ['target_id' => $media->id()],
    'field_category' => ['target_id' => wk_term_id('document_category', 'Handbooks')],
    'field_audience' => [['target_id' => wk_term_id('audience', $level === 'public' ? 'Current families' : 'Staff')]],
    'field_access_level' => $level,
  ]);
  $node->save();
  echo "Created document: $title ($level)\n";
  return $node;
}

$staff_doc = wk_doc_node('Staff Handbook (sample)', 'staff', 'Sample staff-only handbook content.');
$public_doc = wk_doc_node('Family Handbook (sample)', 'public', 'Sample public family handbook content.');

// Put the staff doc in the Staff Intranet group.
$rel_storage = $etm->getStorage('group_relationship');
if (!$group->getRelationshipsByEntity($staff_doc)) {
  $group->addRelationship($staff_doc, $plugin_id);
  echo "Added staff doc to Staff Intranet group\n";
}

// teacher.staff as member, to test positive access.
$teacher = user_load_by_name('teacher.staff');
if ($teacher && !$group->getMember($teacher)) {
  $group->addMember($teacher);
  echo "Added teacher.staff to Staff Intranet\n";
}

// Rebuild node grants so the group grants are written.
node_access_rebuild();

// ---------------------------------------------------------------------------
// 3. Search API server + index.
// ---------------------------------------------------------------------------
if (!Server::load('db')) {
  Server::create([
    'id' => 'db',
    'name' => 'Database server',
    'backend' => 'search_api_db',
    'backend_config' => [
      'database' => 'default:default',
      'min_chars' => 3,
      'matching' => 'words',
      'autocomplete' => ['suggest_suffix' => TRUE, 'suggest_words' => TRUE],
    ],
  ])->save();
  echo "Created Search API server db\n";
}
if (!Index::load('content')) {
  Index::create([
    'id' => 'content',
    'name' => 'Content',
    'server' => 'db',
    'status' => TRUE,
    'datasource_settings' => [
      'entity:node' => [
        'bundles' => ['default' => TRUE, 'selected' => []],
        'languages' => ['default' => TRUE, 'selected' => []],
      ],
    ],
    'field_settings' => [
      'title' => ['label' => 'Title', 'datasource_id' => 'entity:node', 'property_path' => 'title', 'type' => 'text', 'boost' => 5.0],
      'body' => ['label' => 'Body', 'datasource_id' => 'entity:node', 'property_path' => 'body', 'type' => 'text'],
      'type' => ['label' => 'Content type', 'datasource_id' => 'entity:node', 'property_path' => 'type', 'type' => 'string'],
      'field_access_level' => ['label' => 'Access level', 'datasource_id' => 'entity:node', 'property_path' => 'field_access_level', 'type' => 'string'],
    ],
    'processor_settings' => [
      'content_access' => ['weights' => ['preprocess_query' => -30]],
      'entity_status' => [],
      'ignorecase' => ['all_fields' => TRUE, 'fields' => ['title', 'body'], 'weights' => ['preprocess_index' => -20, 'preprocess_query' => -20]],
      'tokenizer' => ['all_fields' => TRUE, 'fields' => ['title', 'body'], 'spaces' => '', 'ignored' => '._-', 'overlap_cjk' => TRUE, 'minimum_word_size' => 3, 'weights' => ['preprocess_index' => -6, 'preprocess_query' => -6]],
      'add_url' => [],
      'aggregated_field' => [],
    ],
    'tracker_settings' => ['default' => ['indexing_order' => 'fifo']],
    'options' => ['index_directly' => TRUE, 'cron_limit' => 50],
  ])->save();
  echo "Created Search API index content\n";
}
$index = Index::load('content');
$index->reindex();
$index->indexItems(-1);
echo "Indexed: " . $index->getTrackerInstance()->getIndexedItemsCount() . " / " . $index->getTrackerInstance()->getTotalItemsCount() . "\n";

// ---------------------------------------------------------------------------
// 4. The test: who sees what for "handbook"?
// ---------------------------------------------------------------------------
function wk_search(Index $index, $account, string $label) {
  $query = $index->query();
  $query->keys('handbook');
  $query->setOption('search_api_access_account', $account);
  $results = $query->execute();
  $titles = [];
  foreach ($results->getResultItems() as $item) {
    $titles[] = $item->getOriginalObject()->getValue()->label();
  }
  sort($titles);
  echo str_pad($label, 14) . ': ' . ($titles ? implode(' | ', $titles) : '(no results)') . "\n";
  return $titles;
}

echo "\n=== Search 'handbook' ===\n";
$anon = wk_search($index, new AnonymousUserSession(), 'anonymous');
wk_search($index, $teacher, 'teacher.staff');
wk_search($index, user_load_by_name('school.editor'), 'school.editor');
wk_search($index, user_load_by_name('admin.demo'), 'admin.demo');
wk_search($index, \Drupal\user\Entity\User::load(1), 'admin (uid 1)');

echo "\n=== Direct node access (view) ===\n";
foreach (['anonymous' => new AnonymousUserSession(), 'teacher.staff' => $teacher, 'school.editor' => user_load_by_name('school.editor')] as $l => $acct) {
  echo str_pad($l, 14) . ': staff doc=' . ($staff_doc->access('view', $acct) ? 'ALLOWED' : 'denied') . ', public doc=' . ($public_doc->access('view', $acct) ? 'ALLOWED' : 'denied') . "\n";
}

$leak = in_array('Staff Handbook (sample)', $anon, TRUE);
echo "\nRESULT: " . ($leak ? 'LEAK - anonymous search returns the staff document' : 'OK - anonymous search does not return the staff document') . "\n";
