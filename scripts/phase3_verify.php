<?php

/**
 * @file
 * Phase 3 verification: temporary nodes are created, checked, then deleted.
 *
 * Run: ddev drush php:script scripts/phase3_verify.php
 */

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\Entity\Node;

$etm = \Drupal::entityTypeManager();
$grp = function ($label) use ($etm) {
  return reset($etm->getStorage('group')->loadByProperties(['label' => $label]));
};
$elem = $grp('Sample Elementary');
$high = $grp('Sample High School');
$intranet = $grp('Staff Intranet');

$mk = function ($group, $title) {
  $n = Node::create(['type' => 'news', 'title' => $title, 'status' => 1, 'uid' => 1,
    'field_summary' => ['value' => 'x', 'format' => 'plain_text']]);
  $n->save();
  $group->addRelationship($n, 'group_node:news');
  return $n;
};
$news_e = $mk($elem, 'TEMP news Elementary');
$news_h = $mk($high, 'TEMP news High');

$users = [
  'anonymous' => new AnonymousUserSession(),
  'school.editor' => user_load_by_name('school.editor'),
  'teacher.staff' => user_load_by_name('teacher.staff'),
  'comms.publisher' => user_load_by_name('comms.publisher'),
];
$staff_doc = reset($etm->getStorage('node')->loadByProperties(['title' => 'Staff Handbook (sample)']));
if ($staff_doc && !$intranet->getRelationshipsByEntity($staff_doc)) {
  $intranet->addRelationship($staff_doc, 'group_node:document');
}

$yn = fn($b) => $b ? 'yes' : 'no ';
printf("%-16s %-14s %-14s %-14s %-14s %s\n", 'user', 'view Elem news', 'edit Elem news', 'view High news', 'edit High news', 'view staff doc');
$switcher = \Drupal::service('account_switcher');
foreach ($users as $name => $u) {
  // permissions_by_term checks the current user, so switch for real.
  $switcher->switchTo($u);
  $etm->getAccessControlHandler('node')->resetCache();
  printf("%-16s %-14s %-14s %-14s %-14s %s\n", $name,
    $yn($news_e->access('view', $u)), $yn($news_e->access('update', $u)),
    $yn($news_h->access('view', $u)), $yn($news_h->access('update', $u)),
    $staff_doc ? $yn($staff_doc->access('view', $u)) : 'n/a');
  $switcher->switchBack();
}
echo "\nExpected: school.editor edits Elementary only; anonymous sees news but not the staff doc;\nteacher.staff and school.editor see the staff doc; nobody except editor/publisher can edit.\n";

$news_e->delete();
$news_h->delete();
echo "Temp nodes removed.\n";
