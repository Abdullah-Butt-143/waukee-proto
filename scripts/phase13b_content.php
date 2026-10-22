<?php

/**
 * @file
 * Sample content, part B: news and events (to 10 each), 6 intranet
 * announcements, 15 staff profiles, 10 documents. All fictional.
 *
 * Access model used here:
 * - Staff-only and restricted documents, staff-only profiles and announcements
 *   live in the Staff Intranet group (members only).
 * - Documents also carry an Audience term: "Staff" (staff roles) or
 *   "Restricted" (publishers and admins only), enforced by permissions_by_term.
 * - Public documents / profiles are not in any group.
 *
 * Re-runnable. Run: ddev drush php:script scripts/phase13b_content.php
 */

use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\Role;

$etm = \Drupal::entityTypeManager();
$central = new \DateTimeZone('America/Chicago');
$sample = ' (Sample content.)';

$tid = function (string $vid, string $name) use ($etm) {
  $t = $etm->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $name]);
  return $t ? (int) reset($t)->id() : NULL;
};
$find = function (string $type, string $title) use ($etm) {
  $n = $etm->getStorage('node')->loadByProperties(['type' => $type, 'title' => $title]);
  return $n ? reset($n) : NULL;
};
$group = fn(string $label) => reset($etm->getStorage('group')->loadByProperties(['label' => $label]));
$intranet = $group('Staff Intranet');
$in_group = function ($node, $plugin) use ($intranet) {
  if ($intranet && !$intranet->getRelationshipsByEntity($node)) {
    $intranet->addRelationship($node, $plugin);
  }
};

$district = $tid('school', 'District (all schools)');
$elem = $tid('school', 'Sample Elementary');
$high = $tid('school', 'Sample High School');
$dept = fn(string $n) => $tid('department', $n);

// ---------------------------------------------------------------------------
// 1. More news (to 10) and events (to 10).
// ---------------------------------------------------------------------------
echo "News and events:\n";
$img = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => 'sample-news']);
$img = $img ? reset($img)->id() : NULL;
$aud = fn(string $n) => $tid('audience', $n);
$news = [
  ['Lunch program adds fresh salad bars', [$district], 'Current families', 'Salad bars are now offered at both schools, with locally sourced produce when in season.', 4],
  ['Sample Elementary welcomes a new reading specialist', [$elem], 'Current families', 'Our new reading specialist joins the literacy team to support early readers.', 5],
  ['Sample High School band earns top ratings', [$high], 'Community', 'The marching band received superior ratings at the regional festival.', 6],
  ['Bus route changes start Monday', [$district], 'Current families', 'A few routes have new pick-up times. Check the transportation page for details.', 7],
  ['Prospective family open house announced', [$district], 'Prospective families', 'Tour classrooms and meet teachers at our fall open house.', 8],
  ['Career fair connects students with local employers', [$high], 'Students', 'Over thirty local employers met students at this year\'s career fair.', 9],
];
foreach ($news as [$title, $schools, $audience, $summary, $age]) {
  if ($find('news', $title)) {
    continue;
  }
  Node::create([
    'type' => 'news', 'title' => $title, 'uid' => 1, 'status' => 1, 'moderation_state' => 'published', 'created' => strtotime("-$age days"),
    'field_summary' => ['value' => "<p>$summary</p>", 'format' => 'basic_html'],
    'body' => ['value' => "<p>$summary$sample</p>", 'format' => 'basic_html'],
    'field_image' => $img ? ['target_id' => $img] : [],
    'field_school' => array_map(fn($t) => ['target_id' => $t], $schools),
    'field_audience' => [['target_id' => $aud($audience)]],
  ])->save();
  echo "  news: $title\n";
}
$events = [
  ['Kindergarten round-up', 5, 18, [$elem], 'Sample Elementary Library'],
  ['Homecoming parade', 11, 17, [$high], 'Downtown route'],
  ['Winter choir concert', 33, 19, [$high], 'Sample High School Auditorium'],
  ['Open house for prospective families', 16, 18, [$district], 'District Auditorium'],
  ['Spirit week kickoff', 8, 8, [$district], 'Both schools'],
];
foreach ($events as [$title, $days, $hour, $schools, $loc]) {
  if ($find('event', $title)) {
    continue;
  }
  $start = (new \DateTimeImmutable('today', $central))->modify("+$days days")->setTime($hour, 0)->getTimestamp();
  Node::create([
    'type' => 'event', 'title' => $title, 'uid' => 1, 'status' => 1, 'moderation_state' => 'published',
    'field_when' => [['value' => $start, 'end_value' => $start + 5400, 'duration' => 90, 'timezone' => 'America/Chicago']],
    'field_location' => $loc,
    'field_school' => array_map(fn($t) => ['target_id' => $t], $schools),
    'field_audience' => [['target_id' => $aud('Current families')]],
    'body' => ['value' => '<p>Sample event.</p>', 'format' => 'basic_html'],
  ])->save();
  echo "  event: $title\n";
}

// ---------------------------------------------------------------------------
// 2. Intranet announcements (members of Staff Intranet only).
// ---------------------------------------------------------------------------
echo "Announcements:\n";
$today = new \DateTimeImmutable('today');
$announcements = [
  ['Staff meeting moved to Thursday', $district, '+14 days', 'The monthly all-staff meeting moves to Thursday at 3:30 pm in the auditorium.'],
  ['Open enrollment for benefits begins', $district, '+30 days', 'Benefits open enrollment runs for the next month. See HR resources for the guide.'],
  ['Elementary: picture day schedule', $elem, '+10 days', 'Picture day is next week. Classroom schedules are posted in the main office.'],
  ['Elementary: fire drill this Friday', $elem, '+3 days', 'A fire drill will be held Friday morning. Please review procedures with your class.'],
  ['High School: testing window opens', $high, '+20 days', 'The testing window opens soon. Proctor assignments are in the shared calendar.'],
  ['Parking lot repaving (completed)', $high, '-3 days', 'The staff lot repaving is complete. (This announcement is expired and should be hidden.)'],
];
foreach ($announcements as [$title, $school, $expiry, $body]) {
  if ($node = $find('announcement', $title)) {
    $in_group($node, 'group_node:announcement');
    continue;
  }
  $node = Node::create([
    'type' => 'announcement', 'title' => $title, 'uid' => 1, 'status' => 1, 'moderation_state' => 'published',
    'body' => ['value' => "<p>$body</p>", 'format' => 'basic_html'],
    'field_target_school' => $school,
    'field_expiry_date' => $today->modify($expiry)->format('Y-m-d'),
  ]);
  $node->save();
  $in_group($node, 'group_node:announcement');
  echo "  announcement: $title\n";
}

// ---------------------------------------------------------------------------
// 3. Staff profiles (15). Staff-only ones live in the Staff Intranet group.
// ---------------------------------------------------------------------------
echo "Staff profiles:\n";
$teacher = user_load_by_name('teacher.staff');
$staff = [
  // name, job title, department, school, visibility, owner
  ['Taylor Teacher', 'Fourth grade teacher', 'Curriculum & Instruction', $elem, 'public', $teacher],
  ['Jordan Ellis', 'Principal', 'Administration', $elem, 'public', NULL],
  ['Casey Morgan', 'Kindergarten teacher', 'Curriculum & Instruction', $elem, 'public', NULL],
  ['Riley Chen', 'School nurse', 'Administration', $elem, 'public', NULL],
  ['Sam Okafor', 'Librarian', 'Curriculum & Instruction', $elem, 'public', NULL],
  ['Morgan Patel', 'Principal', 'Administration', $high, 'public', NULL],
  ['Avery Brooks', 'Band director', 'Curriculum & Instruction', $high, 'public', NULL],
  ['Quinn Rivera', 'Science teacher', 'Curriculum & Instruction', $high, 'public', NULL],
  ['Drew Hamilton', 'Athletic director', 'Administration', $high, 'public', NULL],
  ['Jamie Nguyen', 'Superintendent', 'Administration', $district, 'public', NULL],
  ['Cameron Lopez', 'Director of Nutrition Services', 'Nutrition Services', $district, 'public', NULL],
  ['Skyler Bennett', 'Transportation coordinator', 'Transportation', $district, 'public', NULL],
  ['Reese Carter', 'IT help desk technician', 'Technology', $district, 'staff', NULL],
  ['Dana Kim', 'HR generalist', 'Human Resources', $district, 'staff', NULL],
  ['Parker Wallace', 'Network administrator', 'Technology', $district, 'staff', NULL],
];
$n = 0;
foreach ($staff as [$name, $job, $department, $school, $visibility, $owner]) {
  $n++;
  if ($node = $find('staff_profile', $name)) {
    continue;
  }
  $slug = strtolower(str_replace(' ', '.', $name));
  $node = Node::create([
    'type' => 'staff_profile', 'title' => $name, 'uid' => $owner ? $owner->id() : 1, 'status' => 1, 'moderation_state' => 'published',
    'field_job_title' => $job, 'field_department' => $dept($department), 'field_school' => $school,
    'field_email' => "$slug@example.test", 'field_phone' => sprintf('515-555-01%02d', $n), 'field_visibility' => $visibility,
  ]);
  $node->save();
  if ($visibility === 'staff') {
    $in_group($node, 'group_node:staff_profile');
  }
  echo "  staff: $name ($visibility)\n";
}
// Staff may edit their own profile.
$role = Role::load('staff');
$role->grantPermission('edit own staff_profile content')->save();

// ---------------------------------------------------------------------------
// 4. Documents (10): public, staff, restricted.
// ---------------------------------------------------------------------------
echo "Documents:\n";
if (!$tid('audience', 'Restricted')) {
  Term::create(['vid' => 'audience', 'name' => 'Restricted', 'weight' => 10])->save();
  echo "  audience term: Restricted\n";
}
$storage = \Drupal::service('permissions_by_term.access_storage');
$restricted_tid = $tid('audience', 'Restricted');
$have = $storage->getRoleTermPermissionsByTid($restricted_tid, 'en');
$need = array_diff(['publisher', 'district_admin', 'administrator'], $have);
if ($need) {
  $storage->addTermPermissionsByRoleIds($need, $restricted_tid, 'en');
  echo "  Restricted term limited to publisher, district_admin, administrator\n";
}

$doc_media = function (string $name, string $text) use ($etm) {
  $found = $etm->getStorage('media')->loadByProperties(['bundle' => 'document', 'name' => $name]);
  if ($found) {
    return reset($found)->id();
  }
  $dir = 'public://sample';
  \Drupal::service('file_system')->prepareDirectory($dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
  $file = \Drupal::service('file.repository')->writeData($text, $dir . '/' . preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) . '.txt', \Drupal\Core\File\FileExists::Replace);
  $file->setPermanent();
  $file->save();
  $m = Media::create(['bundle' => 'document', 'name' => $name, 'status' => 1, 'uid' => 1, 'field_media_document' => ['target_id' => $file->id()]]);
  $m->save();
  return $m->id();
};
$documents = [
  // title, category, access level, audience term
  ['Student and Family Handbook (sample)', 'Handbooks', 'public', 'Current families'],
  ['Board Policy Manual (sample)', 'Policies', 'public', 'Community'],
  ['Enrollment Form (sample)', 'Forms', 'public', 'Prospective families'],
  ['School Calendar (sample)', 'Newsletters', 'public', 'Current families'],
  ['Board Meeting Agenda (sample)', 'Board materials', 'public', 'Community'],
  ['Employee Handbook (sample)', 'Handbooks', 'staff', 'Staff'],
  ['Leave Request Form (sample)', 'Forms', 'staff', 'Staff'],
  ['Technology Acceptable Use Policy (sample)', 'Policies', 'staff', 'Staff'],
  ['Salary Schedule (sample)', 'Board materials', 'restricted', 'Restricted'],
  ['Crisis Response Plan (sample)', 'Policies', 'restricted', 'Restricted'],
];
foreach ($documents as [$title, $category, $level, $audience]) {
  if ($node = $find('document', $title)) {
    if ($level !== 'public') {
      $in_group($node, 'group_node:document');
    }
    continue;
  }
  $node = Node::create([
    'type' => 'document', 'title' => $title, 'uid' => 1, 'status' => 1, 'moderation_state' => 'published',
    'field_file' => ['target_id' => $doc_media($title, "$title\nSample document for the prototype. Not a real policy.")],
    'field_category' => $tid('document_category', $category),
    'field_audience' => [['target_id' => $tid('audience', $audience)]],
    'field_access_level' => $level,
  ]);
  $node->save();
  if ($level !== 'public') {
    $in_group($node, 'group_node:document');
  }
  echo "  document: $title ($level)\n";
}

node_access_rebuild();
echo "Done.\n";
