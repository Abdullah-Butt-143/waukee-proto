<?php

/**
 * @file
 * Phase 7 verification with temporary content (removed at the end).
 * Run: ddev drush php:script scripts/phase7_verify.php
 */

use Drupal\node\Entity\Node;
use Drupal\views\Views;

$etm = \Drupal::entityTypeManager();
$term = function (string $vid, string $name) use ($etm) {
  return reset($etm->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $name]))->id();
};
$elem = $term('school', 'Sample Elementary');
$high = $term('school', 'Sample High School');
$tech = $term('department', 'Technology');
$admin = $term('department', 'Administration');

$cleanup = function () use ($etm) {
  foreach ($etm->getStorage('node')->loadMultiple() as $n) {
    if (str_starts_with($n->label(), 'TEMP ')) {
      $n->delete();
    }
  }
};
$cleanup();

$mk = function (array $v) {
  $n = Node::create($v + ['uid' => 1, 'status' => 1, 'moderation_state' => 'published']);
  $n->save();
  return $n;
};
$today = new DateTimeImmutable('today');
$mk(['type' => 'announcement', 'title' => 'TEMP Active Elementary', 'field_target_school' => $elem, 'body' => 'x']);
$mk(['type' => 'announcement', 'title' => 'TEMP Future High', 'field_target_school' => $high, 'field_expiry_date' => $today->modify('+7 days')->format('Y-m-d'), 'body' => 'x']);
$mk(['type' => 'announcement', 'title' => 'TEMP Expired Elementary', 'field_target_school' => $elem, 'field_expiry_date' => $today->modify('-2 days')->format('Y-m-d'), 'body' => 'x']);
$mk(['type' => 'staff_profile', 'title' => 'TEMP Jane Public', 'field_job_title' => 'Teacher', 'field_school' => $elem, 'field_department' => $admin, 'field_visibility' => 'public']);
$mk(['type' => 'staff_profile', 'title' => 'TEMP Sam Staffonly', 'field_job_title' => 'Technician', 'field_school' => $high, 'field_department' => $tech, 'field_visibility' => 'staff']);

$switcher = \Drupal::service('account_switcher');
$titles = function (string $view, string $display, array $input, string $as) use ($switcher) {
  $switcher->switchTo(user_load_by_name($as));
  $v = Views::getView($view);
  $v->setDisplay($display);
  $v->setExposedInput($input);
  $v->execute();
  $out = [];
  foreach ($v->result as $row) {
    $label = $row->_entity->label();
    if (str_starts_with($label, 'TEMP ')) {
      $out[] = substr($label, 5);
    }
  }
  $switcher->switchBack();
  sort($out);
  return $out ? implode(', ', $out) : '(none)';
};
$show = fn($label, $r) => printf("%-62s %s\n", $label, $r);

echo "== Announcements (as teacher.staff) ==\n";
$show('no filter (expired must be hidden)', $titles('announcements', 'page_1', [], 'teacher.staff'));
$show('school = Sample Elementary', $titles('announcements', 'page_1', ['school' => $elem], 'teacher.staff'));
$show('school = Sample High School', $titles('announcements', 'page_1', ['school' => $high], 'teacher.staff'));

echo "== Staff directory ==\n";
$show('intranet, as teacher.staff, no filter', $titles('staff_directory', 'page_2', [], 'teacher.staff'));
$show('intranet, q=jane', $titles('staff_directory', 'page_2', ['q' => 'jane'], 'teacher.staff'));
$show('intranet, q=technician (job title)', $titles('staff_directory', 'page_2', ['q' => 'technician'], 'teacher.staff'));
$show('intranet, department = Technology', $titles('staff_directory', 'page_2', ['department' => $tech], 'teacher.staff'));
$show('intranet, school = Sample Elementary', $titles('staff_directory', 'page_2', ['school' => $elem], 'teacher.staff'));
$show('intranet, department = Technology + school = Elementary', $titles('staff_directory', 'page_2', ['department' => $tech, 'school' => $elem], 'teacher.staff'));

// Anonymous HTTP.
$client = \Drupal::httpClient();
$get = function (string $path) use ($client) {
  $r = $client->get('https://waukee-proto.ddev.site' . $path, ['http_errors' => FALSE, 'verify' => FALSE, 'allow_redirects' => FALSE]);
  return [$r->getStatusCode(), (string) $r->getBody()];
};
echo "== Anonymous visitor ==\n";
foreach (['/intranet/announcements', '/intranet/documents', '/intranet/staff-directory'] as $p) {
  [$code] = $get($p);
  $show("GET $p", $code . ($code == 403 ? ' (blocked, correct)' : ' (UNEXPECTED)'));
}
[$code, $html] = $get('/staff-directory');
$show('GET /staff-directory (public directory)', $code . ' | Jane: ' . (str_contains($html, 'TEMP Jane Public') ? 'shown' : 'missing') . ' | Sam (staff-only): ' . (str_contains($html, 'TEMP Sam Staffonly') ? 'LEAK' : 'hidden'));
[, $home] = $get('/');
$show('Intranet menu links on homepage', str_contains($home, '/intranet/announcements') ? 'VISIBLE (unexpected)' : 'hidden (correct)');

$cleanup();
echo "Temp content removed.\n";
