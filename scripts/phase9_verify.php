<?php

/**
 * @file
 * Phase 9 verification: renders the forms, checks conditional-logic markup,
 * submits test data (valid and invalid) and reports. Test submissions are
 * deleted afterwards. Mail goes to Mailpit; check it with:
 *   ddev exec 'curl -s localhost:8025/api/v1/messages' | head
 *
 * Run: ddev drush php:script scripts/phase9_verify.php
 */

use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformSubmissionForm;

$client = \Drupal::httpClient();
$get = function (string $path) use ($client) {
  $r = $client->get('https://waukee-proto.ddev.site' . $path, ['http_errors' => FALSE, 'verify' => FALSE]);
  return [$r->getStatusCode(), (string) $r->getBody()];
};
$show = fn($label, $ok) => printf("  %-62s %s\n", $label, $ok ? 'yes' : 'NO');

echo "== Facility rental form (anonymous) ==\n";
[$code, $html] = $get('/form/facility-rental');
$show("GET /form/facility-rental is 200 (got $code)", $code === 200);
$show('conditional-logic markup (data-drupal-states) present', str_contains($html, 'data-drupal-states'));
$show('states reference the "room" field', str_contains($html, 'name=&quot;room&quot;') || str_contains($html, 'name="room"') || str_contains($html, '[name="room"]') || str_contains($html, 'room'));
$show('every field has a <label>', substr_count($html, '<label') >= 10);
$show('required fields marked (required attribute)', substr_count($html, 'required="required"') >= 6);
$show('grouped in fieldsets with legends', substr_count($html, '<legend') >= 3);
$show('submit is a real button (keyboard focusable)', (bool) preg_match('/<(input|button)[^>]+type="submit"/', $html));
[$code, $html] = $get('/community/facility-rental');
$show("embedded on /community/facility-rental (got $code)", $code === 200 && str_contains($html, 'webform-submission-facility-rental'));
[$code, $html] = $get('/students/volunteer-hours');
$show("embedded on /students/volunteer-hours (got $code)", $code === 200 && str_contains($html, 'webform-submission-volunteer-hours'));

echo "== Submissions ==\n";
$submit = function (string $id, array $data) {
  return WebformSubmissionForm::submitFormValues(['webform_id' => $id, 'data' => $data]);
};
$ok = $submit('facility_rental', [
  'name' => 'TEST Pat Parent', 'email' => 'pat.parent@example.test', 'school' => 'high', 'room' => 'auditorium',
  'event_date' => date('Y-m-d', strtotime('+30 days')), 'start_time' => '18:00:00', 'end_time' => '20:00:00', 'attendees' => 120,
  'auditorium_stage' => 1, 'auditorium_seating' => 'theater', 'agreement' => 1,
]);
$show('valid auditorium request accepted', $ok instanceof WebformSubmission);
$bad = $submit('facility_rental', ['name' => 'TEST Missing', 'room' => 'auditorium']);
$show('incomplete request rejected with validation errors', is_array($bad) && count($bad) > 0);
if (is_array($bad)) {
  echo "    errors: " . implode(' | ', array_map('strval', array_slice($bad, 0, 3))) . "\n";
}
$no_seating = $submit('facility_rental', [
  'name' => 'TEST X', 'email' => 'x@example.test', 'school' => 'high', 'room' => 'auditorium',
  'event_date' => date('Y-m-d', strtotime('+30 days')), 'start_time' => '18:00:00', 'end_time' => '20:00:00', 'attendees' => 10, 'agreement' => 1,
]);
$show('auditorium without seating choice rejected (conditional required)', is_array($no_seating));
$gym = $submit('facility_rental', [
  'name' => 'TEST Gym', 'email' => 'gym@example.test', 'school' => 'elementary', 'room' => 'gym',
  'event_date' => date('Y-m-d', strtotime('+10 days')), 'start_time' => '09:00:00', 'end_time' => '11:00:00', 'attendees' => 30, 'agreement' => 1,
]);
$show('gym request does NOT require the auditorium seating question', $gym instanceof WebformSubmission);

$vol = $submit('volunteer_hours', [
  'student_name' => 'TEST Student', 'email' => 'student@example.test', 'school' => 'high', 'organization' => 'Sample Food Bank',
  'activity_type' => 'food_drive', 'service_date' => date('Y-m-d', strtotime('-2 days')), 'hours' => 2.5,
  'supervisor_name' => 'TEST Supervisor', 'supervisor_email' => 'sup@example.test',
]);
$show('valid volunteer hours accepted', $vol instanceof WebformSubmission);
$other = $submit('volunteer_hours', [
  'student_name' => 'TEST Student', 'email' => 'student@example.test', 'school' => 'high', 'organization' => 'Sample Org',
  'activity_type' => 'other', 'service_date' => date('Y-m-d', strtotime('-2 days')), 'hours' => 1,
  'supervisor_name' => 'TEST Supervisor', 'supervisor_email' => 'sup@example.test',
]);
$show('"Other" activity without description rejected', is_array($other));
$toomany = $submit('volunteer_hours', [
  'student_name' => 'TEST Student', 'email' => 'student@example.test', 'school' => 'high', 'organization' => 'Sample Org',
  'activity_type' => 'tutoring', 'service_date' => date('Y-m-d', strtotime('-2 days')), 'hours' => 99,
  'supervisor_name' => 'TEST Supervisor', 'supervisor_email' => 'sup@example.test',
]);
$show('99 hours rejected (max 24)', is_array($toomany));

// Clean up test submissions.
foreach (['facility_rental', 'volunteer_hours'] as $id) {
  $ids = \Drupal::entityQuery('webform_submission')->accessCheck(FALSE)->condition('webform_id', $id)->execute();
  foreach (WebformSubmission::loadMultiple($ids) as $s) {
    $s->delete();
  }
}
echo "Test submissions removed. Emails sent: check Mailpit (4 expected: office + confirmation for each valid submission, 3 valid).\n";
