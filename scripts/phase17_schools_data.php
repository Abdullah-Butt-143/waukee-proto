<?php

/**
 * @file
 * Phase 17: makes the /schools directory and /schools/{school} pages
 * data-driven. Adds fields to the "school" group bundle and creates/updates
 * one group per school with the content that was previously hardcoded in
 * web/themes/custom/waukee_theme/templates/layout/page--schools.html.twig
 * and page--schools--waukee-high-school.html.twig.
 *
 * Re-runnable. Run: ddev drush php:script scripts/phase17_schools_data.php
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\Group;

$bundle = 'school';

// ---------------------------------------------------------------------------
// Field helper (same pattern as scripts/phase6_layout_builder.php, targeting
// the "group" entity type instead of "block_content").
// ---------------------------------------------------------------------------
function wk17_field(string $name, string $type, string $label, array $o = []): void {
  $bundle = 'school';
  $fname = 'field_' . $name;
  if (!FieldStorageConfig::loadByName('group', $fname)) {
    FieldStorageConfig::create([
      'field_name' => $fname,
      'entity_type' => 'group',
      'type' => $type,
      'cardinality' => $o['cardinality'] ?? 1,
      'settings' => $o['storage_settings'] ?? [],
    ])->save();
  }
  if (!FieldConfig::loadByName('group', $bundle, $fname)) {
    FieldConfig::create([
      'field_name' => $fname,
      'entity_type' => 'group',
      'bundle' => $bundle,
      'label' => $label,
      'required' => $o['required'] ?? FALSE,
      'default_value' => $o['default'] ?? [],
      'settings' => $o['settings'] ?? [],
    ])->save();
  }
  $form = EntityFormDisplay::load("group.$bundle.default") ?: EntityFormDisplay::create(['targetEntityType' => 'group', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  $view = EntityViewDisplay::load("group.$bundle.default") ?: EntityViewDisplay::create(['targetEntityType' => 'group', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  $weight = $o['weight'] ?? 10;
  $form->setComponent($fname, ['type' => $o['widget'], 'weight' => $weight, 'settings' => $o['widget_settings'] ?? []])->save();
  $view->setComponent($fname, ['type' => $o['formatter'], 'label' => 'inline', 'weight' => $weight, 'settings' => $o['formatter_settings'] ?? []])->save();
}

echo "Fields:\n";
wk17_field('slug', 'string', 'URL slug', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 1]);
wk17_field('tier', 'list_string', 'Tier', [
  'storage_settings' => ['allowed_values' => ['elementary' => 'Elementary', 'middle' => 'Middle', 'transition' => '9th Grade Campus', 'high' => 'High School', 'specialized' => 'Specialized & Innovation']],
  'widget' => 'options_select', 'formatter' => 'list_key', 'weight' => 2,
]);
wk17_field('feeder', 'list_string', 'Feeder zone', [
  'storage_settings' => ['allowed_values' => ['waukee' => 'Waukee High Feeder', 'northwest' => 'Northwest Feeder', 'district-wide' => 'District-Wide']],
  'widget' => 'options_select', 'formatter' => 'list_key', 'weight' => 3,
]);
wk17_field('mascot', 'string', 'Mascot', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 4]);
wk17_field('badge', 'string', 'Badge (e.g. "Home of the Warriors")', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 5]);
wk17_field('enrollment', 'string', 'Enrollment', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 6]);
wk17_field('photo_url', 'string_long', 'Photo URL', ['widget' => 'string_textarea', 'formatter' => 'basic_string', 'weight' => 7]);
wk17_field('attendance_phone', 'string', 'Attendance line', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 8]);
wk17_field('principal_email', 'string', 'Principal email', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 9]);
wk17_field('website', 'link', 'External website (athletics, news, etc.)', ['widget' => 'link_default', 'formatter' => 'link', 'weight' => 10]);
wk17_field('csd_unit', 'string', 'CSD unit number', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 11]);
wk17_field('bell_hours', 'string', 'Daily bell hours', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 12]);
wk17_field('early_dismissal', 'string', 'Early dismissal time', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 13]);
wk17_field('hero_tagline', 'string', 'Hero tagline', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 14]);
wk17_field('link_label', 'string', 'Directory card link label (default "Explore")', ['widget' => 'string_textfield', 'formatter' => 'string', 'weight' => 15]);
wk17_field('full_profile', 'boolean', 'Show the full profile page (principal message, staff, schedule)', [
  'settings' => ['on_label' => 'Yes', 'off_label' => 'No'], 'default' => [['value' => 0]],
  'widget' => 'boolean_checkbox', 'widget_settings' => ['display_label' => TRUE], 'formatter' => 'boolean', 'weight' => 16,
]);
wk17_field('principal_message', 'text_long', 'Principal message (full profile only)', ['widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 17]);
wk17_field('leadership_team', 'text_long', 'Leadership team (full profile only)', ['widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 18]);
wk17_field('news_highlights', 'text_long', 'News highlights (full profile only)', ['widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 19]);
wk17_field('family_resources', 'text_long', 'Family resources (full profile only)', ['widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 20]);

// ---------------------------------------------------------------------------
// School data (from the design files this replaces).
// ---------------------------------------------------------------------------
$schools = [
  // -- High schools --------------------------------------------------------
  'waukee-high-school' => [
    'name' => 'Waukee High School', 'tier' => 'high', 'feeder' => 'waukee',
    'grades' => '10–12', 'mascot' => 'Warriors', 'badge' => 'Home of the Warriors',
    'address' => '555 SE University Ave, Waukee, IA 50263', 'phone' => '515-987-5163',
    'attendance_phone' => '515-987-2781', 'principal' => 'Judi Luther-Roland',
    'principal_email' => 'jlutherroland@waukeeschools.org',
    'summary' => 'Nurturing inquisitive minds, competitive athletics, and collaborative leadership across grades 10 through 12.',
    'enrollment' => '~1,850 Students', 'csd_unit' => 'CSD Unit #102',
    'bell_hours' => '7:50 AM – 2:50 PM', 'early_dismissal' => '12:50 PM',
    'hero_tagline' => 'Pride • Excellence • Community',
    'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuB-g0yhVTwhBrzyfY6ys8a1LZyM-QH9T9MLYXEdulqSe7EoO52a1v0cWJBOgCKjLnqbJe1ABccHezhJm8S7YAYXNvp2puvi-1AInfZwW2oP0zl7kSbij48YKbV01fn8NeLb-hkRq_wacjzJrbWsJAi5eYRADPk_Tboj_o8xIRYVyvbkCwB1vLJGHGhsnAu-pgv_lZf9Ctj-h9hmSAFgiHA9xBCITaPDRIkE035a9-q7-DehI27of6hc',
    'website' => 'https://warriors.waukeeschools.org/',
    'full_profile' => TRUE,
    'principal_message' => '<p><strong>Greetings from the Waukee High School Principal!</strong> My husband, Todd, and I love being a part of this community and chose to raise our family here. Besides working at Waukee CSD, I’ve enjoyed teaching and administrative positions in other schools, including Des Moines East High School, Casady Alternative School, and Abraham Lincoln High School. I have also taught at Drake and Viterbo University.</p><p>Throughout my career in education, I have consistently championed the importance of <strong>relationship-building</strong>. I firmly believe that strong, trusting relationships between administrators, teachers, staff, students, parents, and community members are essential for creating a positive and supportive school environment. We can achieve remarkable outcomes by fostering a culture where ideas are freely exchanged, feedback is welcomed, and teamwork is celebrated.</p><p><strong>Waukee High School continues to excel both inside and outside the classroom. We have opportunities for all our students to grow, learn, and develop the skills to be leaders for our world.</strong></p><p><em>We are Waukee! Go, Warriors!</em></p>',
    'leadership_team' => '<ul>'
      . '<li><strong>Johanna Russell</strong> — Associate Principal. Curriculum oversight, academic pathways, and department coordinator support. <a href="mailto:jrussell2@waukeeschools.org">jrussell2@waukeeschools.org</a></li>'
      . '<li><strong>Kevin Atterberg</strong> — Assistant Principal. Student affairs, discipline standards, attendance accountability, and 10th grade cohort. <a href="mailto:katterberg@waukeeschools.org">katterberg@waukeeschools.org</a></li>'
      . '<li><strong>Mark Weber</strong> — Assistant Principal. Campus safety, facility operations, co-curricular coordination, and senior cohort. <a href="mailto:mweber@waukeeschools.org">mweber@waukeeschools.org</a></li>'
      . '<li><strong>Stacy Tharp</strong> — Building Secretary. Main desk administration, general visitor registration, and building support. <a href="mailto:stharp@waukeeschools.org">stharp@waukeeschools.org</a></li>'
      . '<li><strong>Lisa Eischen</strong> — Student Services Secretary. Enrollment verifications, student records, counseling appointments, and transcripts. <a href="mailto:leischen@waukeeschools.org">leischen@waukeeschools.org</a></li>'
      . '<li><strong>Beth Collins</strong> — Activities Secretary. Athletic physicals, club rosters, ticketing assistance, and eligibility. <a href="mailto:bcollins@waukeeschools.org">bcollins@waukeeschools.org</a></li>'
      . '<li><strong>Maggie Murillo</strong> — School Nurse (RN). Daily medical accommodations, medication administration, and student health plans. <a href="mailto:mmurillo@waukeeschools.org">mmurillo@waukeeschools.org</a></li>'
      . '</ul>',
    'news_highlights' => '<ul>'
      . '<li><strong>Meet Your September 2026 Making a Difference Nominees</strong> (October 2026 • Waukee CSD Staff &amp; Students) — September Making a Difference Recognitions: We’re excited to celebrate our first round of 2026–27 Making a Difference nominees! These outstanding staff and students exemplify the Waukee CSD core values of commitment, integrity, and daily optimism.</li>'
      . '<li><strong>WHS Alum Brings His Talents to Banana Ball</strong> (September 2026 • Alumni Feature) — When Waukee High School alum Drew Irvine dreamed about a career in baseball, he didn’t picture dancing and juggling as part of the job. But that’s exactly what he does on nationwide tour stops, combining athletic precision with community cheer.</li>'
      . '</ul>',
    'family_resources' => '<ul>'
      . '<li>Checking student schedules, block assignments, and graduation credit progress</li>'
      . '<li>Paying class fees, AP exam registrations, and parking permit renewals securely</li>'
      . '<li>Receiving direct announcements from teachers, department leads, and district safety</li>'
      . '<li>Adding money to nutrition accounts and applying for free/reduced meal status</li>'
      . '<li>Monitoring student 1:1 school-issued device safety filters and tech support</li>'
      . '</ul>',
  ],
  'northwest-high-school' => [
    'name' => 'Northwest High School', 'tier' => 'high', 'feeder' => 'northwest',
    'grades' => '10–12', 'mascot' => 'Wolves', 'badge' => 'Home of the Wolves',
    'address' => '655 N 10th St, Waukee, IA 50263', 'phone' => '',
    'principal' => 'Dr. Douglas Barry',
    'summary' => 'A comprehensive 10–12 high school serving the Northwest Feeder Zone.',
    'enrollment' => '~1,780 Students',
    'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAmShLiftYJc3ZGWpSi5Pax3Jtxz9X_mDjcm8xEMXrEBWH6d8GC86DW7eFC0A1myE9fj1iDYuo-GaO1PylznLnndPm5sY9Sl_AzT-339PVYB0VwHy_pU7KlD60SzoTBPCHlYpvHccE06IaAKX3pt-6TvhhfRxE4gn7L8W9VzQcREqRf4mv8inkRBWGUsZ6qYeSelWBf7BHeNDSNa7N53SaccoWGAuztfX5Rf-NUYvHJVMFxR30TzQHd',
  ],
  // -- 9th grade campuses ---------------------------------------------------
  'prairieview-school' => [
    'name' => 'Prairieview School', 'tier' => 'transition', 'feeder' => 'waukee',
    'grades' => '8–9', 'address' => '655 SE University Ave, Waukee, IA 50263', 'phone' => '515-987-2770',
    'summary' => 'Focusing on collegiate habits, leadership pathways, and seamless academic transition for incoming Waukee Warriors.',
    'enrollment' => '~920', 'link_label' => 'View Prairieview',
  ],
  'trailridge-school' => [
    'name' => 'Trailridge School', 'tier' => 'transition', 'feeder' => 'northwest',
    'grades' => '8–9', 'address' => '1451 NW Douglas Pkwy, Waukee, IA 50263', 'phone' => '515-987-8597',
    'summary' => 'State-of-the-art facility engineered for collaborative project learning, preparing students directly for Northwest High School.',
    'enrollment' => '~890', 'link_label' => 'View Trailridge',
  ],
  // -- Middle schools --------------------------------------------------------
  'waukee-middle-school' => ['name' => 'Waukee Middle School', 'tier' => 'middle', 'feeder' => 'waukee', 'grades' => '6–7', 'address' => '905 Warrior Ln, Waukee, IA', 'mascot' => 'Warriors', 'enrollment' => '650 Learners'],
  'south-middle-school' => ['name' => 'South Middle School', 'tier' => 'middle', 'feeder' => 'waukee', 'grades' => '6–7', 'address' => '2350 SE LA Grant Pkwy, Waukee', 'mascot' => 'Redhawks', 'enrollment' => '680 Learners'],
  'north-middle-school' => ['name' => 'North Middle School', 'tier' => 'middle', 'feeder' => 'northwest', 'grades' => '6–7', 'address' => '1215 N 10th St, Waukee, IA', 'mascot' => 'Huskies', 'enrollment' => '710 Learners'],
  'timberline-middle' => ['name' => 'Timberline Middle', 'tier' => 'middle', 'feeder' => 'northwest', 'grades' => '6–7', 'address' => '2605 S 88th St, Waukee, IA', 'mascot' => 'Timberwolves', 'enrollment' => '695 Learners'],
  // -- Elementary schools ----------------------------------------------------
  'brookview-elementary' => ['name' => 'Brookview Elementary', 'tier' => 'elementary', 'feeder' => 'waukee', 'grades' => 'K–5', 'address' => '8000 EP True Pkwy, West Des Moines', 'mascot' => 'Bobcats', 'principal' => 'Terry Hurlburt'],
  'eason-elementary' => ['name' => 'Eason Elementary', 'tier' => 'elementary', 'feeder' => 'waukee', 'grades' => 'K–5', 'address' => '605 SE Boone Dr, Waukee, IA', 'mascot' => 'Eagles', 'principal' => 'Clint Prohaska'],
  'grant-ragan-elementary' => ['name' => 'Grant Ragan Elementary', 'tier' => 'elementary', 'feeder' => 'northwest', 'grades' => 'K–5', 'address' => '645 NE Dartmoor Dr, Waukee, IA', 'mascot' => 'Huskies', 'principal' => 'Matt Dahl'],
  'maple-grove-elementary' => ['name' => 'Maple Grove Elementary', 'tier' => 'elementary', 'feeder' => 'waukee', 'grades' => 'K–5', 'address' => '1455 98th St, West Des Moines', 'mascot' => 'Grizzlies', 'principal' => 'Kim Tierney'],
  'radiant-elementary' => ['name' => 'Radiant Elementary', 'tier' => 'elementary', 'feeder' => 'northwest', 'grades' => 'K–5', 'address' => '5050 170th St, Urbandale, IA', 'mascot' => 'Suns', 'principal' => 'Dr. Brady Fleming'],
  'shuler-elementary' => ['name' => 'Shuler Elementary', 'tier' => 'elementary', 'feeder' => 'waukee', 'grades' => 'K–5', 'address' => '16400 Douglas Pkwy, Clive, IA', 'mascot' => 'Sharks', 'principal' => 'Joel Martin'],
  'sugar-creek-elementary' => ['name' => 'Sugar Creek Elementary', 'tier' => 'elementary', 'feeder' => 'northwest', 'grades' => 'K–5', 'address' => '880 77th St, Waukee, IA', 'mascot' => 'Otters', 'principal' => 'Nicole Glynn'],
  'walnut-hills-elementary' => ['name' => 'Walnut Hills Elementary', 'tier' => 'elementary', 'feeder' => 'waukee', 'grades' => 'K–5', 'address' => '4240 NW 156th St, Urbandale, IA', 'mascot' => 'Wildcats', 'principal' => 'Robert Anderson'],
  'waterford-elementary' => ['name' => 'Waterford Elementary', 'tier' => 'elementary', 'feeder' => 'northwest', 'grades' => 'K–5', 'address' => '9950 NW 100th St, Urbandale, IA', 'mascot' => 'Waves', 'principal' => 'Sarah Kautz'],
  'waukee-elementary' => ['name' => 'Waukee Elementary', 'tier' => 'elementary', 'feeder' => 'waukee', 'grades' => 'K–5', 'address' => '850 6th St, Waukee, IA', 'mascot' => 'Pioneers', 'principal' => 'Ryan Marzen'],
  'woodland-hills-elementary' => ['name' => 'Woodland Hills Elementary', 'tier' => 'elementary', 'feeder' => 'northwest', 'grades' => 'K–5', 'address' => '1120 S 95th St, West Des Moines', 'mascot' => 'Hawks', 'principal' => 'Laura Johnson'],
  // -- Specialized centers -----------------------------------------------
  'waukee-innovation-and-learning-center' => [
    'name' => 'Waukee Innovation & Learning Center (WILC)', 'tier' => 'specialized', 'feeder' => 'district-wide',
    'badge' => 'Flagship Innovation',
    'summary' => 'Home of the nationally recognized Waukee APEX program where high schoolers immerse in real-world business client projects.',
    'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDfOerSoP5eC9CbvxfVqW9jDcuHT3W4WVXzA8ieMnUosW5a5geU9zm4i3X83av65EyQeD6gMutIm3z5zUl-CFvAEVr9U12xJBfT2lsILorpIhjymHy-GC1qSoo1Mnv4AseEmUSrgBbs5AnK_2Si3HfBhjucdgJf6KkW3jtpSa5m_2_h3B8MFvQCMpceYlVgJxDXyUqgmcHNbxywKwU8P--kFKq1CAjxZL_Kit2oKXLcCjYcQCc_pdbL',
    'link_label' => 'View APEX',
  ],
  'vince-meyer-learning-center' => [
    'name' => 'Vince Meyer Learning Center', 'tier' => 'specialized', 'feeder' => 'district-wide',
    'badge' => 'Support & Early Access', 'grades' => 'Preschool & Alt Ed',
    'summary' => 'Housing specialized district developmental preschool programs, alternative high school graduation pathways, and community outreach.',
    'address' => '430 Ashworth Dr, Waukee, IA', 'link_label' => 'View Services',
  ],
  'district-natatorium' => [
    'name' => 'District Natatorium & Aquatic Facility', 'tier' => 'specialized', 'feeder' => 'district-wide',
    'badge' => 'Athletics & Community', 'grades' => 'Olympic Standard',
    'summary' => 'Premier 50-meter competitive swimming facility hosting state swim meets, community lessons, and interscholastic aquatic athletics.',
    'address' => '455 N 10th St, Waukee, IA', 'link_label' => 'Pool Schedules',
  ],
];

echo "Schools:\n";
foreach ($schools as $slug => $data) {
  $existing = \Drupal::entityTypeManager()->getStorage('group')
    ->loadByProperties(['type' => 'school', 'field_slug' => $slug]);
  $group = $existing ? reset($existing) : Group::create(['type' => 'school', 'label' => $data['name']]);
  $group->set('label', $data['name']);
  $map = [
    'tier' => 'field_tier', 'feeder' => 'field_feeder', 'grades' => 'field_grades',
    'mascot' => 'field_mascot', 'badge' => 'field_badge', 'enrollment' => 'field_enrollment',
    'address' => 'field_address', 'phone' => 'field_phone', 'attendance_phone' => 'field_attendance_phone',
    'principal' => 'field_principal', 'principal_email' => 'field_principal_email',
    'summary' => 'field_summary', 'image' => 'field_photo_url', 'csd_unit' => 'field_csd_unit',
    'bell_hours' => 'field_bell_hours', 'early_dismissal' => 'field_early_dismissal',
    'hero_tagline' => 'field_hero_tagline', 'link_label' => 'field_link_label',
  ];
  foreach ($map as $key => $field) {
    if (isset($data[$key])) {
      $group->set($field, $data[$key]);
    }
  }
  $group->set('field_slug', $slug);
  $group->set('field_full_profile', !empty($data['full_profile']));
  if (!empty($data['website'])) {
    $group->set('field_website', ['uri' => $data['website']]);
  }
  foreach (['principal_message', 'leadership_team', 'news_highlights', 'family_resources'] as $rich) {
    if (!empty($data[$rich])) {
      $group->set('field_' . $rich, ['value' => $data[$rich], 'format' => 'basic_html']);
    }
  }
  $group->save();
  echo "  $slug\n";
}

drupal_flush_all_caches();
echo "Phase 17 complete: " . count($schools) . " schools.\n";
