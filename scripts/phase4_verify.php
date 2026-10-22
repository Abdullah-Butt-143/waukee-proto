<?php

/**
 * @file
 * Phase 4 verification: which workflow transitions each role may use.
 *
 * Creates a temporary draft news node and deletes it afterwards.
 * Run: ddev drush php:script scripts/phase4_verify.php
 */

use Drupal\node\Entity\Node;

$node = Node::create(['type' => 'news', 'title' => 'TEMP workflow test', 'uid' => 1,
  'field_summary' => ['value' => 'x', 'format' => 'plain_text'], 'field_school' => [['target_id' => reset(Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'school']))->id()]], 'moderation_state' => 'draft']);
$node->save();
$validation = \Drupal::service('content_moderation.state_transition_validation');

foreach (['school.editor', 'comms.publisher', 'admin.demo', 'teacher.staff'] as $name) {
  $u = user_load_by_name($name);
  $names = array_map(fn($t) => $t->label(), $validation->getValidTransitions($node, $u));
  printf("%-16s from Draft: %s\n", $name, $names ? implode(', ', $names) : '(none)');
}

// Walk the full path as the right people.
$switcher = \Drupal::service('account_switcher');
$steps = [['school.editor', 'review'], ['comms.publisher', 'published']];
foreach ($steps as [$who, $state]) {
  $switcher->switchTo(user_load_by_name($who));
  $n = Node::load($node->id());
  $n->set('moderation_state', $state);
  $violations = $n->validate();
  if ($violations->count()) {
    echo "$who -> $state: BLOCKED (" . $violations[0]->getMessage() . ")\n";
  }
  else {
    $n->save();
    echo "$who -> $state: ok, published=" . (int) Node::load($node->id())->isPublished() . "\n";
  }
  $switcher->switchBack();
}

// school.editor must not be able to publish.
$n = Node::load($node->id());
$n->set('moderation_state', 'draft');
$n->save();
$switcher->switchTo(user_load_by_name('school.editor'));
$n = Node::load($node->id());
$n->set('moderation_state', 'published');
echo "school.editor -> published: " . ($n->validate()->count() ? 'BLOCKED (correct)' : 'ALLOWED (wrong)') . "\n";
$switcher->switchBack();

Node::load($node->id())->delete();
echo "Temp node removed.\n";
