<?php

/**
 * @file
 * Phase 9: webforms "Facility rental" (conditional fields by room) and
 * "Volunteer hours", each with an email notice to the office and a
 * confirmation email to the submitter. The forms are embedded on the existing
 * placeholder pages. Mail goes to DDEV's Mailpit (https://waukee-proto.ddev.site:8025).
 *
 * Re-runnable (webforms are re-written so edits here apply).
 * Run: ddev drush php:script scripts/phase9_webforms.php
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\user\Entity\Role;
use Drupal\webform\Entity\Webform;

$uuid = \Drupal::service('uuid');

// Webform's "default" From address is the site email; it must be valid.
$site = \Drupal::configFactory()->getEditable('system.site');
if (!\Drupal::service('email.validator')->isValid((string) $site->get('mail'))) {
  $site->set('mail', 'noreply@waukee-proto.example.test')->save();
  echo "Set site email to noreply@waukee-proto.example.test\n";
}

$handlers = function (string $office_mail, string $office_label, string $subject, string $confirm_subject, string $confirm_intro) {
  return [
    'email_office' => [
      'id' => 'email', 'label' => $office_label, 'handler_id' => 'email_office', 'status' => TRUE, 'weight' => 1,
      'settings' => [
        'states' => ['completed'], 'to_mail' => $office_mail, 'from_mail' => 'noreply@waukee-proto.test', 'from_name' => 'Waukee Prototype',
        'subject' => $subject, 'body' => "<p>A new request was submitted.</p>\n[webform_submission:values]", 'html' => TRUE,
        'reply_to' => '[webform_submission:values:email:raw]',
      ],
    ],
    'email_confirmation' => [
      'id' => 'email', 'label' => 'Confirmation to submitter', 'handler_id' => 'email_confirmation', 'status' => TRUE, 'weight' => 2,
      'settings' => [
        'states' => ['completed'], 'to_mail' => '[webform_submission:values:email:raw]', 'from_mail' => 'noreply@waukee-proto.test', 'from_name' => 'Waukee Prototype',
        'subject' => $confirm_subject, 'body' => "<p>$confirm_intro</p>\n[webform_submission:values]", 'html' => TRUE,
      ],
    ],
  ];
};

$open_to_all = ['create' => ['roles' => ['anonymous', 'authenticated'], 'users' => [], 'permissions' => []]];

// ---------------------------------------------------------------------------
// 1. Facility rental. Fields appear depending on the room chosen.
// ---------------------------------------------------------------------------
$facility_elements = <<<'YAML'
contact:
  '#type': fieldset
  '#title': 'Your contact details'
  name:
    '#type': textfield
    '#title': 'Full name'
    '#required': true
  organization:
    '#type': textfield
    '#title': Organization
  email:
    '#type': email
    '#title': Email
    '#required': true
  phone:
    '#type': tel
    '#title': Phone
event:
  '#type': fieldset
  '#title': 'Your event'
  school:
    '#type': select
    '#title': School
    '#options':
      elementary: 'Sample Elementary'
      high: 'Sample High School'
    '#empty_option': '- Select a school -'
    '#required': true
  room:
    '#type': select
    '#title': 'Room or facility'
    '#options':
      gym: Gymnasium
      auditorium: Auditorium
      cafeteria: Cafeteria
      classroom: Classroom
      field: 'Athletic field'
    '#empty_option': '- Select a room -'
    '#required': true
  event_date:
    '#type': date
    '#title': 'Event date'
    '#required': true
  start_time:
    '#type': webform_time
    '#title': 'Start time'
    '#required': true
  end_time:
    '#type': webform_time
    '#title': 'End time'
    '#required': true
  attendees:
    '#type': number
    '#title': 'Expected attendees'
    '#min': 1
    '#required': true
room_options:
  '#type': fieldset
  '#title': 'Room options'
  '#description': 'These questions depend on the room you picked.'
  auditorium_stage:
    '#type': checkbox
    '#title': 'We need stage and lighting setup'
    '#states':
      visible:
        ':input[name="room"]':
          value: auditorium
  auditorium_seating:
    '#type': radios
    '#title': 'Seating layout'
    '#options':
      theater: Theater
      banquet: Banquet
    '#states':
      visible:
        ':input[name="room"]':
          value: auditorium
      required:
        ':input[name="room"]':
          value: auditorium
  cafeteria_kitchen:
    '#type': checkbox
    '#title': 'We need kitchen access'
    '#states':
      visible:
        ':input[name="room"]':
          value: cafeteria
  cafeteria_tables:
    '#type': number
    '#title': 'Number of tables needed'
    '#min': 0
    '#states':
      visible:
        ':input[name="room"]':
          value: cafeteria
  gym_equipment:
    '#type': checkboxes
    '#title': 'Gym equipment'
    '#options':
      bleachers: 'Open bleachers'
      scoreboard: Scoreboard
      sound: 'Sound system'
    '#states':
      visible:
        ':input[name="room"]':
          value: gym
  classroom_av:
    '#type': checkbox
    '#title': 'We need a projector and screen'
    '#states':
      visible:
        ':input[name="room"]':
          value: classroom
  field_lights:
    '#type': checkbox
    '#title': 'We need field lights (evening event)'
    '#states':
      visible:
        ':input[name="room"]':
          value: field
notes:
  '#type': textarea
  '#title': 'Anything else we should know?'
agreement:
  '#type': checkbox
  '#title': 'I have read and agree to the facility use policy.'
  '#required': true
YAML;

$facility = Webform::load('facility_rental') ?: Webform::create(['id' => 'facility_rental']);
$facility->set('title', 'Facility rental request');
$facility->set('description', 'Request a school room or field for a community event. (Sample form for the prototype.)');
$facility->set('status', 'open');
$facility->set('elements', $facility_elements);
$facility->set('access', $open_to_all + ($facility->get('access') ?: []));
$facility->setSettings([
  'page' => TRUE,
  'confirmation_type' => 'message',
  'confirmation_message' => 'Thank you. Your facility request was received and the facilities office will contact you within two business days.',
  'form_required' => TRUE,
  'form_disable_autocomplete' => FALSE,
  'form_novalidate' => FALSE,
] + $facility->getSettings());
foreach ($handlers('facilities@example.test', 'Email to facilities office', 'Facility rental request: [webform_submission:values:room]', 'We received your facility request', 'Thank you for your facility rental request. A copy of your answers is below.') as $id => $h) {
  if ($facility->getHandlers()->has($id)) { $facility->deleteWebformHandler($facility->getHandler($id)); }
  $facility->addWebformHandler(Drupal::service("plugin.manager.webform.handler")->createInstance($h["id"], $h));
}
$facility->save();
echo "Webform facility_rental saved (/form/facility-rental)\n";

// ---------------------------------------------------------------------------
// 2. Volunteer hours. "Other" activity reveals a description box.
// ---------------------------------------------------------------------------
$volunteer_elements = <<<'YAML'
student:
  '#type': fieldset
  '#title': 'Student'
  student_name:
    '#type': textfield
    '#title': 'Student name'
    '#required': true
  email:
    '#type': email
    '#title': 'Student or parent email'
    '#required': true
  school:
    '#type': select
    '#title': School
    '#options':
      elementary: 'Sample Elementary'
      high: 'Sample High School'
    '#empty_option': '- Select a school -'
    '#required': true
service:
  '#type': fieldset
  '#title': 'Service'
  organization:
    '#type': textfield
    '#title': 'Organization served'
    '#required': true
  activity_type:
    '#type': select
    '#title': 'Type of activity'
    '#options':
      tutoring: Tutoring
      cleanup: 'Community cleanup'
      food_drive: 'Food drive'
      other: Other
    '#empty_option': '- Select an activity -'
    '#required': true
  activity_other:
    '#type': textfield
    '#title': 'Please describe the activity'
    '#states':
      visible:
        ':input[name="activity_type"]':
          value: other
      required:
        ':input[name="activity_type"]':
          value: other
  service_date:
    '#type': date
    '#title': 'Date of service'
    '#required': true
  hours:
    '#type': number
    '#title': 'Hours served'
    '#min': 0.25
    '#max': 24
    '#step': 0.25
    '#required': true
supervisor:
  '#type': fieldset
  '#title': 'Supervisor who can verify these hours'
  supervisor_name:
    '#type': textfield
    '#title': 'Supervisor name'
    '#required': true
  supervisor_email:
    '#type': email
    '#title': 'Supervisor email'
    '#required': true
YAML;

$volunteer = Webform::load('volunteer_hours') ?: Webform::create(['id' => 'volunteer_hours']);
$volunteer->set('title', 'Volunteer hours');
$volunteer->set('description', 'Log community service hours for verification. (Sample form for the prototype.)');
$volunteer->set('status', 'open');
$volunteer->set('elements', $volunteer_elements);
$volunteer->set('access', $open_to_all + ($volunteer->get('access') ?: []));
$volunteer->setSettings([
  'page' => TRUE,
  'confirmation_type' => 'message',
  'confirmation_message' => 'Thank you. Your volunteer hours were submitted for verification.',
  'form_required' => TRUE,
] + $volunteer->getSettings());
foreach ($handlers('service-hours@example.test', 'Email to service-hours coordinator', 'Volunteer hours: [webform_submission:values:student_name]', 'We received your volunteer hours', 'Thank you. Your volunteer hours were submitted. A copy is below.') as $id => $h) {
  if ($volunteer->getHandlers()->has($id)) { $volunteer->deleteWebformHandler($volunteer->getHandler($id)); }
  $volunteer->addWebformHandler(Drupal::service("plugin.manager.webform.handler")->createInstance($h["id"], $h));
}
$volunteer->save();
echo "Webform volunteer_hours saved (/form/volunteer-hours)\n";

// ---------------------------------------------------------------------------
// 3. Embed the forms on the existing pages (replacing the "added later" text).
// ---------------------------------------------------------------------------
$embed = function (string $page_title, string $webform_id, string $intro) use ($uuid) {
  $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'page', 'title' => $page_title]);
  if (!$nodes) {
    echo "  (page '$page_title' not found; run phase13a_pages.php first)\n";
    return;
  }
  $node = reset($nodes);
  $block = BlockContent::create(['type' => 'callout', 'reusable' => FALSE, 'info' => "callout $webform_id",
    'field_heading' => 'Before you start', 'field_body_text' => ['value' => "<p>$intro</p>", 'format' => 'basic_html'], 'field_style' => 'info']);
  $block->save();
  $section = new Section('layout_onecol');
  $section->appendComponent(new SectionComponent($uuid->generate(), 'content', [
    'id' => 'inline_block:callout', 'label' => 'x', 'label_display' => FALSE, 'provider' => 'layout_builder',
    'view_mode' => 'full', 'block_revision_id' => $block->getRevisionId(), 'block_serialized' => NULL,
  ], ['weight' => 0]));
  $section->appendComponent(new SectionComponent($uuid->generate(), 'content', [
    'id' => 'webform_block', 'label' => 'Form', 'label_display' => FALSE, 'provider' => 'webform',
    'webform_id' => $webform_id, 'default_data' => '', 'redirect' => FALSE, 'lazy' => FALSE,
  ], ['weight' => 1]));
  $node->set('layout_builder__layout', [$section]);
  $node->save();
  echo "  embedded $webform_id on '$page_title'\n";
};
$embed('Facility rentals', 'facility_rental', 'Request a gym, auditorium, cafeteria, classroom or athletic field. Extra questions appear depending on the room you choose.');
$embed('Volunteer hours', 'volunteer_hours', 'Log your community service hours. Choose "Other" to describe a different activity.');

// ---------------------------------------------------------------------------
// 4. Staff who handle submissions.
// ---------------------------------------------------------------------------
$known = array_keys(\Drupal::service('user.permissions')->getPermissions());
foreach (['publisher', 'district_admin'] as $rid) {
  $role = Role::load($rid);
  foreach (['access webform overview', 'access any webform submission', 'view any webform submission', 'edit any webform submission'] as $p) {
    if (in_array($p, $known, TRUE)) {
      $role->grantPermission($p);
    }
    else {
      echo "  (unknown permission $p)\n";
    }
  }
  $role->save();
}
echo "Phase 9 script complete.\n";
