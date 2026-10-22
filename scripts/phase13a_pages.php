<?php

/**
 * @file
 * Sample content, part A: homepage, sitemap pages, school and intranet pages,
 * plus a few news items and events so the feeds are not empty.
 * Sample content only. Re-runnable (pages are found by title).
 *
 * Run: ddev drush php:script scripts/phase13a_pages.php
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\file\Entity\File;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\media\Entity\Media;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;

$etm = \Drupal::entityTypeManager();
$uuid = \Drupal::service('uuid');
$publisher = user_load_by_name('comms.publisher');
$review_date = date('Y-m-d', strtotime('+1 year'));

$term_id = function (string $vid, string $name) use ($etm) {
  $t = $etm->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $name]);
  return $t ? reset($t)->id() : NULL;
};
$find = function (string $type, string $title) use ($etm) {
  $n = $etm->getStorage('node')->loadByProperties(['type' => $type, 'title' => $title]);
  return $n ? reset($n) : NULL;
};

// ---------------------------------------------------------------------------
// Sample images (generated; no third-party assets).
// ---------------------------------------------------------------------------
$make_image = function (string $name, string $alt, array $from, array $to, int $w = 1600, int $h = 700) use ($etm) {
  $found = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $name]);
  if ($found) {
    return reset($found)->id();
  }
  if (!function_exists('imagecreatetruecolor')) {
    echo "  (GD missing, skipping image $name)\n";
    return NULL;
  }
  $im = imagecreatetruecolor($w, $h);
  for ($y = 0; $y < $h; $y++) {
    $r = (int) ($from[0] + ($to[0] - $from[0]) * $y / $h);
    $g = (int) ($from[1] + ($to[1] - $from[1]) * $y / $h);
    $b = (int) ($from[2] + ($to[2] - $from[2]) * $y / $h);
    imageline($im, 0, $y, $w, $y, imagecolorallocate($im, $r, $g, $b));
  }
  $accent = imagecolorallocate($im, 197, 230, 58);
  imagefilledellipse($im, (int) ($w * 0.82), (int) ($h * 0.3), (int) ($h * 0.7), (int) ($h * 0.7), $accent);
  imagefilledellipse($im, (int) ($w * 0.7), (int) ($h * 0.9), (int) ($h * 0.5), (int) ($h * 0.5), imagecolorallocate($im, 236, 228, 243));
  ob_start();
  imagepng($im);
  $png = ob_get_clean();
  $dir = 'public://sample';
  \Drupal::service('file_system')->prepareDirectory($dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
  $file = \Drupal::service('file.repository')->writeData($png, "$dir/$name.png", \Drupal\Core\File\FileExists::Replace);
  $file->setPermanent();
  $file->save();
  $media = Media::create(['bundle' => 'image', 'name' => $name, 'status' => 1, 'uid' => 1, 'field_media_image' => ['target_id' => $file->id(), 'alt' => $alt, 'width' => $w, 'height' => $h]]);
  $media->save();
  echo "  image: $name\n";
  return $media->id();
};
echo "Images:\n";
$img_hero = $make_image('sample-hero', 'Abstract purple and lime shapes (sample artwork)', [60, 26, 91], [91, 44, 131]);
$img_news = $make_image('sample-news', 'Abstract purple shapes (sample artwork)', [91, 44, 131], [122, 74, 163], 1000, 600);

// ---------------------------------------------------------------------------
// Layout helpers.
// ---------------------------------------------------------------------------
$inline = function (string $bundle, array $values, int $weight, string $region = 'content') use ($uuid) {
  $block = BlockContent::create(['type' => $bundle, 'reusable' => FALSE, 'info' => $bundle . ' ' . $uuid->generate()] + $values);
  $block->save();
  return new SectionComponent($uuid->generate(), $region, [
    'id' => "inline_block:$bundle", 'label' => 'x', 'label_display' => FALSE, 'provider' => 'layout_builder',
    'view_mode' => 'full', 'block_revision_id' => $block->getRevisionId(), 'block_serialized' => NULL,
  ], ['weight' => $weight]);
};
$view_block = fn(string $plugin, int $weight, string $region = 'content') => new SectionComponent($uuid->generate(), $region, [
  'id' => $plugin, 'label' => '', 'label_display' => FALSE, 'provider' => 'views', 'views_label' => '', 'items_per_page' => 'none',
], ['weight' => $weight]);
$links = fn(array $pairs) => array_map(fn($t, $u) => ['title' => $t, 'uri' => $u], array_keys($pairs), array_values($pairs));

/** Create a node by title (once). $layout is a callable returning sections. */
$make_page = function (string $title, string $alias, string $body, ?callable $layout = NULL, array $extra = []) use ($find, $publisher, $review_date) {
  if ($node = $find('page', $title)) {
    return $node;
  }
  $node = Node::create([
    'type' => 'page', 'title' => $title, 'uid' => 1, 'status' => 1, 'moderation_state' => 'published',
    'body' => ['value' => $body, 'format' => 'basic_html'],
    'field_owner' => ['target_id' => $publisher->id()],
    'field_review_date' => $review_date,
    'path' => ['alias' => $alias, 'pathauto' => 0],
  ] + $extra);
  if ($layout) {
    $node->set('layout_builder__layout', $layout());
  }
  $node->save();
  echo "  page: $title ($alias)\n";
  return $node;
};

// ---------------------------------------------------------------------------
// 1. Sitemap pages (children first so section pages can card them).
// ---------------------------------------------------------------------------
$p = fn(string $text) => "<p>$text</p>";
$sample = ' This is sample content for the prototype; no real District data.';
$children = [
  'families' => [
    ['Enrollment', '/enrollment', $p('How to enroll a student in Sample Community Schools: gather documents, complete the online form, and meet your school office.' . $sample) . '<h2>Steps</h2><ol><li>Check your attendance area.</li><li>Gather proof of residency and immunization records.</li><li>Submit the registration form.</li><li>Schedule a school visit.</li></ol>'],
    ['Transportation', '/transportation', $p('Bus routes, pick-up times and how to report a missed bus.' . $sample) . '<h2>Contact</h2><p>Transportation office: sample.transportation@example.test</p>'],
    ['Nutrition', '/families/nutrition', $p('Daily menus, allergy information and free and reduced-price meal applications.' . $sample)],
    ['Health Services', '/families/health-services', $p('School nurses, medication forms, immunization requirements and illness guidelines.' . $sample)],
    ['School closings', '/closings', $p('Weather closings and late starts are posted here and in the emergency alert banner.' . $sample)],
  ],
  'prospective' => [
    ['Why Sample Community Schools', '/prospective-families/why-us', $p('Small class sizes, strong arts and athletics, and caring staff. Families choose us for the student experience.' . $sample)],
    ['Our schools', '/prospective-families/schools', $p('Find your neighborhood school: Sample Elementary and Sample High School.' . $sample) . '<ul><li><a href="/schools/sample-elementary">Sample Elementary</a></li><li><a href="/schools/sample-high-school">Sample High School</a></li></ul>'],
    ['Enroll in our district', '/prospective-families/enroll', $p('New to the district? Start here, then continue to <a href="/enrollment">Enrollment</a>.' . $sample)],
  ],
  'students' => [
    ['Activities', '/students/activities', $p('Clubs, athletics, fine arts and service opportunities.' . $sample)],
    ['Volunteer hours', '/students/volunteer-hours', $p('Log your service hours here. (The accessible volunteer-hours form is added with the webforms phase.)' . $sample)],
    ['Student resources', '/students/resources', $p('Library, counseling, tutoring and technology help.' . $sample)],
  ],
  'careers' => [
    ['Benefits', '/careers/benefits', $p('Health coverage, retirement, professional development and paid leave.' . $sample)],
    ['Job listings', '/careers/jobs', $p('Current openings. Each posting has a closing date and an application link.' . $sample)],
  ],
  'community' => [
    ['Facility rentals', '/community/facility-rental', $p('Request a gym, auditorium or classroom. (The accessible facility-rental form is added with the webforms phase.)' . $sample)],
    ['Board of Education', '/community/board-of-education', $p('Meeting schedule, agendas and minutes.' . $sample)],
    ['Community news', '/community/news', $p('Latest district news for the community.' . $sample), fn() => [(new Section('layout_onecol'))->appendComponent($view_block('views_block:news_feed-block_1', 0))]],
  ],
];
echo "Pages:\n";
$child_nodes = [];
foreach ($children as $group => $rows) {
  foreach ($rows as $row) {
    [$title, $alias, $body] = $row;
    $child_nodes[$group][] = $make_page($title, $alias, $body, $row[3] ?? NULL);
  }
}

$sections = [
  'families' => ['Families', '/families', 'Everything current families need: enrolling, getting to school, meals, health and closings.'],
  'prospective' => ['Prospective Families', '/prospective-families', 'Thinking about joining us? Learn why families choose our schools and how to enroll.'],
  'students' => ['Students', '/students', 'Activities, service hours and resources for students.'],
  'careers' => ['Careers', '/careers', 'Join our team. Explore open positions and benefits.'],
  'community' => ['Community', '/community', 'Facility rentals, Board of Education information and district news for neighbors.'],
];
foreach ($sections as $key => [$title, $alias, $intro]) {
  $ids = array_map(fn($n) => ['target_id' => $n->id()], $child_nodes[$key]);
  $make_page($title, $alias, "<p>$intro</p>", function () use ($inline, $intro, $ids, $title) {
    return [(new Section('layout_onecol'))
      ->appendComponent($inline('callout', ['field_heading' => $title, 'field_body_text' => ['value' => "<p>$intro</p>", 'format' => 'basic_html'], 'field_style' => 'info'], 0))
      ->appendComponent($inline('card_grid', ['field_heading' => 'In this section', 'field_cards' => $ids], 1))];
  });
}

// ---------------------------------------------------------------------------
// 2. News and events (so feeds are alive). Full volume comes in Phase 13b.
// ---------------------------------------------------------------------------
echo "News and events:\n";
$district = $term_id('school', 'District (all schools)');
$elem = $term_id('school', 'Sample Elementary');
$high = $term_id('school', 'Sample High School');
$aud_fam = $term_id('audience', 'Current families');
$aud_com = $term_id('audience', 'Community');
$news = [
  ['Welcome back to the new school year', [$district], $aud_fam, 'Classes are underway across the district. Thank you to families and staff for a smooth start.'],
  ['Sample Elementary reading night', [$elem], $aud_fam, 'Families joined teachers for an evening of stories, snacks and book swaps.'],
  ['Sample High School robotics team advances', [$high], $aud_com, 'The robotics team placed first in the regional competition and heads to state next month.'],
  ['Board of Education approves calendar', [$district], $aud_com, 'The Board approved next year\'s calendar, including conference days and breaks.'],
];
foreach ($news as $i => [$title, $schools, $aud, $summary]) {
  if ($find('news', $title)) {
    continue;
  }
  Node::create([
    'type' => 'news', 'title' => $title, 'uid' => 1, 'status' => 1, 'moderation_state' => 'published',
    'created' => strtotime("-$i days"),
    'field_summary' => ['value' => "<p>$summary</p>", 'format' => 'basic_html'],
    'body' => ['value' => "<p>$summary" . $sample . '</p>', 'format' => 'basic_html'],
    'field_image' => $img_news ? ['target_id' => $img_news] : [],
    'field_school' => array_map(fn($t) => ['target_id' => $t], $schools),
    'field_audience' => [['target_id' => $aud]],
  ])->save();
  echo "  news: $title\n";
}
$events = [
  ['Back-to-school night', 3, 19, [$district], 'District Auditorium'],
  ['Fall concert', 9, 19, [$high], 'Sample High School Gym'],
  ['Elementary science fair', 14, 18, [$elem], 'Sample Elementary Cafeteria'],
  ['Board of Education meeting', 20, 18, [$district], 'Administration Building'],
  ['Parent-teacher conferences', 27, 16, [$elem, $high], 'Both schools'],
];
foreach ($events as [$title, $days, $hour, $schools, $loc]) {
  // Times are District-local (Central), whatever the server timezone is.
  $central = new \DateTimeZone('America/Chicago');
  $start = (new \DateTimeImmutable('today', $central))->modify("+$days days")->setTime($hour, 0)->getTimestamp();
  if ($existing = $find('event', $title)) {
    $current = $existing->get('field_when')->first();
    if ((int) $current->value !== $start) {
      $existing->set('field_when', [['value' => $start, 'end_value' => $start + 5400, 'duration' => 90, 'timezone' => 'America/Chicago']]);
      $existing->save();
      echo "  event time fixed (Central): $title\n";
    }
    continue;
  }
  Node::create([
    'type' => 'event', 'title' => $title, 'uid' => 1, 'status' => 1, 'moderation_state' => 'published',
    'field_when' => [['value' => $start, 'end_value' => $start + 5400, 'duration' => 90, 'timezone' => 'America/Chicago']],
    'field_location' => $loc,
    'field_school' => array_map(fn($t) => ['target_id' => $t], $schools),
    'field_audience' => [['target_id' => $aud_fam]],
    'body' => ['value' => '<p>Sample event.</p>', 'format' => 'basic_html'],
  ])->save();
  echo "  event: $title\n";
}
foreach ([['Teacher, Sample Elementary', 'Sample Elementary', '+30 days'], ['Bus driver', 'Transportation Center', '+21 days'], ['Food service assistant', 'Sample High School', '+14 days']] as [$title, $loc, $close]) {
  if ($find('job_posting', $title)) {
    continue;
  }
  Node::create([
    'type' => 'job_posting', 'title' => $title, 'uid' => 1, 'status' => 1, 'moderation_state' => 'published',
    'field_location' => $loc, 'field_closing_date' => date('Y-m-d', strtotime($close)),
    'field_apply_link' => ['uri' => 'https://example.com/apply', 'title' => 'Apply'],
    'body' => ['value' => '<p>Sample job posting.</p>', 'format' => 'basic_html'],
  ])->save();
  echo "  job: $title\n";
}

// ---------------------------------------------------------------------------
// 3. Homepage (built from components) and front page setting.
// ---------------------------------------------------------------------------
$home = $make_page('Home', '/home', '<p>Sample Community Schools home page.</p>', function () use ($inline, $view_block, $links, $img_hero) {
  $top = (new Section('layout_onecol'))
    ->appendComponent($inline('hero', [
      'field_heading' => 'Sample Community Schools',
      'field_subheading' => 'Where every student is known, challenged and supported.',
      'field_image' => $img_hero ? ['target_id' => $img_hero] : [],
      'field_cta' => ['uri' => 'internal:/enrollment', 'title' => 'Enroll today'],
    ], 0))
    ->appendComponent($inline('audience_pathways', [
      'field_heading' => 'I am a...',
      'field_links' => $links(['Current family' => 'internal:/families', 'Prospective family' => 'internal:/prospective-families', 'Student' => 'internal:/students', 'Community member' => 'internal:/community', 'Job seeker' => 'internal:/careers', 'Staff member' => 'internal:/user/login']),
    ], 1));
  $feeds = (new Section('layout_twocol_section', ['column_widths' => '50-50']))
    ->appendComponent($view_block('views_block:news_feed-block_1', 0, 'first'))
    ->appendComponent($view_block('views_block:events-block_1', 0, 'second'));
  $bottom = (new Section('layout_onecol'))
    ->appendComponent($inline('quick_links', [
      'field_heading' => 'Quick links',
      'field_links' => $links(['Calendar' => 'internal:/calendar', 'Lunch menus' => 'internal:/lunch-menus', 'Enrollment' => 'internal:/enrollment', 'Jobs' => 'internal:/careers/jobs', 'Transportation' => 'internal:/transportation']),
    ], 0));
  return [$top, $feeds, $bottom];
});
\Drupal::configFactory()->getEditable('system.site')->set('page.front', '/node/' . $home->id())->save();
echo "Front page set to /node/{$home->id()}\n";

// ---------------------------------------------------------------------------
// 4. School sites (pages inside the school groups) and intranet pages.
// ---------------------------------------------------------------------------
echo "School and intranet pages:\n";
$group = fn(string $label) => reset($etm->getStorage('group')->loadByProperties(['label' => $label]));
$school_page = function (string $label, string $alias, string $blurb) use ($make_page, $inline, $view_block, $img_hero, $group) {
  $node = $make_page($label, $alias, "<p>$blurb</p>", function () use ($inline, $view_block, $img_hero, $label, $blurb) {
    return [
      (new Section('layout_onecol'))->appendComponent($inline('hero', ['field_heading' => $label, 'field_subheading' => $blurb, 'field_image' => $img_hero ? ['target_id' => $img_hero] : []], 0)),
      (new Section('layout_twocol_section', ['column_widths' => '50-50']))
        ->appendComponent($view_block('views_block:news_feed-block_1', 0, 'first'))
        ->appendComponent($view_block('views_block:events-block_1', 0, 'second')),
    ];
  });
  $g = $group($label);
  if ($g && !$g->getRelationshipsByEntity($node)) {
    $g->addRelationship($node, 'group_node:page');
  }
};
$school_page('Sample Elementary', '/schools/sample-elementary', 'A welcoming neighborhood school for grades K-5.');
$school_page('Sample High School', '/schools/sample-high-school', 'Preparing students for college, careers and life.');

$intranet = $group('Staff Intranet');
foreach ([['HR resources', '/intranet/hr-resources', 'Benefits enrollment, leave policies, payroll calendar and forms.'], ['IT resources', '/intranet/it-resources', 'Password resets, device requests and the help desk.']] as [$title, $alias, $body]) {
  $node = $make_page($title, $alias, "<p>$body$sample</p>");
  if ($intranet && !$intranet->getRelationshipsByEntity($node)) {
    $intranet->addRelationship($node, 'group_node:page');
  }
  if (!$etm->getStorage('menu_link_content')->loadByProperties(['menu_name' => 'intranet', 'title' => $title])) {
    MenuLinkContent::create(['menu_name' => 'intranet', 'title' => $title, 'link' => ['uri' => 'internal:' . $alias], 'weight' => 10])->save();
  }
}

// Hide the page title on the front page (the hero is the heading).
$title_block = \Drupal\block\Entity\Block::load('waukee_theme_page_title');
if ($title_block) {
  $title_block->setVisibilityConfig('request_path', ['id' => 'request_path', 'negate' => TRUE, 'pages' => '<front>'])->save();
}

echo "Done.\n";
