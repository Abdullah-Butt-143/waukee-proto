<?php

/**
 * @file
 * Phase 14: sample artwork. Draws simple flat illustrations with PHP GD (no
 * third-party assets): a school scene for the hero and four themed images for
 * news cards, then assigns them. Re-runnable (images are regenerated and
 * media/nodes are reused).
 *
 * Run: ddev drush php:script scripts/phase14_images.php
 */

use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

if (!function_exists('imagecreatetruecolor')) {
  throw new \RuntimeException('PHP GD is required.');
}

const WK_S = 2; // Supersampling factor (draw at 2x, shrink for smooth edges).

/** Tiny drawing helper working in final-image units. */
class WkCanvas {
  public $im;
  public int $w;
  public int $h;

  public function __construct(int $w, int $h) {
    $this->w = $w;
    $this->h = $h;
    $this->im = imagecreatetruecolor($w * WK_S, $h * WK_S);
    imagealphablending($this->im, TRUE);
  }

  public function col(string $hex, int $alpha = 0) {
    $hex = ltrim($hex, '#');
    return imagecolorallocatealpha($this->im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alpha);
  }

  public function gradient(string $from, string $to): void {
    [$r1, $g1, $b1] = sscanf(ltrim($from, '#'), '%02x%02x%02x');
    [$r2, $g2, $b2] = sscanf(ltrim($to, '#'), '%02x%02x%02x');
    for ($y = 0; $y < $this->h * WK_S; $y++) {
      $t = $y / ($this->h * WK_S);
      imageline($this->im, 0, $y, $this->w * WK_S, $y, imagecolorallocate($this->im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
    }
  }

  public function rect($x1, $y1, $x2, $y2, string $hex, int $alpha = 0): void {
    imagefilledrectangle($this->im, $x1 * WK_S, $y1 * WK_S, $x2 * WK_S, $y2 * WK_S, $this->col($hex, $alpha));
  }

  public function ell($cx, $cy, $w, $h, string $hex, int $alpha = 0): void {
    imagefilledellipse($this->im, $cx * WK_S, $cy * WK_S, $w * WK_S, $h * WK_S, $this->col($hex, $alpha));
  }

  public function poly(array $pts, string $hex, int $alpha = 0): void {
    $flat = [];
    foreach ($pts as [$x, $y]) {
      $flat[] = $x * WK_S;
      $flat[] = $y * WK_S;
    }
    imagefilledpolygon($this->im, $flat, $this->col($hex, $alpha));
  }

  public function line($x1, $y1, $x2, $y2, int $thick, string $hex): void {
    imagesetthickness($this->im, $thick * WK_S);
    imageline($this->im, $x1 * WK_S, $y1 * WK_S, $x2 * WK_S, $y2 * WK_S, $this->col($hex));
    imagesetthickness($this->im, 1);
  }

  public function png(): string {
    $out = imagecreatetruecolor($this->w, $this->h);
    imagecopyresampled($out, $this->im, 0, 0, 0, 0, $this->w, $this->h, $this->w * WK_S, $this->h * WK_S);
    ob_start();
    imagepng($out, NULL, 8);
    return ob_get_clean();
  }

}

function wk_hero_scene(): string {
  $c = new WkCanvas(1200, 900);
  $c->gradient('#3a1860', '#8a5cb3');
  // Sun with a soft ring, clouds.
  $c->ell(930, 230, 420, 420, '#ffffff', 112);
  $c->ell(930, 230, 270, 270, '#f2a900');
  foreach ([[220, 210, 190], [320, 180, 150], [400, 215, 170]] as [$x, $y, $w]) {
    $c->ell($x, $y, $w, $w * 0.45, '#ffffff', 85);
  }
  // Hills.
  $c->ell(260, 930, 1200, 520, '#5b2c83');
  $c->ell(960, 960, 1250, 600, '#4a2273');
  // School building.
  $c->rect(190, 500, 1010, 790, '#e6daf2');
  $c->rect(190, 500, 1010, 520, '#cdbfe0');
  $c->rect(420, 390, 780, 790, '#ffffff');
  $c->poly([[390, 392], [600, 250], [810, 392]], '#3c1a5b');
  $c->ell(600, 335, 78, 78, '#f2a900');
  $c->ell(600, 335, 56, 56, '#ffffff');
  $c->line(600, 335, 600, 312, 4, '#3c1a5b');
  $c->line(600, 335, 618, 345, 4, '#3c1a5b');
  $c->rect(596, 150, 604, 255, '#f1eaf8');
  $c->poly([[604, 158], [690, 182], [604, 206]], '#f2a900');
  // Windows.
  foreach ([230, 300, 370, 830, 900, 970] as $x) {
    foreach ([550, 650] as $y) {
      $c->rect($x - 22, $y, $x + 22, $y + 62, '#7a4aa3');
      $c->rect($x - 22, $y + 29, $x + 22, $y + 33, '#e6daf2');
    }
  }
  foreach ([470, 700] as $x) {
    foreach ([440, 560, 670] as $y) {
      if ($x === 470 && $y === 670) {
        continue;
      }
      $c->rect($x, $y, $x + 50, $y + 70, '#7a4aa3');
    }
  }
  // Door and steps.
  $c->ell(600, 648, 110, 110, '#3c1a5b');
  $c->rect(545, 648, 655, 790, '#3c1a5b');
  $c->rect(598, 648, 602, 790, '#7a4aa3');
  $c->rect(520, 790, 680, 808, '#d9cbe8');
  $c->rect(495, 808, 705, 826, '#cdbfe0');
  // Ground and path.
  $c->rect(0, 826, 1200, 900, '#2a1240');
  $c->poly([[540, 826], [660, 826], [820, 900], [380, 900]], '#e6daf2');
  // Trees.
  foreach ([[95, 650], [1110, 660]] as [$x, $y]) {
    $c->rect($x - 9, $y + 50, $x + 9, 826, '#2a1240');
    $c->ell($x, $y, 190, 190, '#f2a900');
    $c->ell($x + 25, $y - 28, 110, 110, '#d8f067');
  }
  return $c->png();
}

function wk_news_art(string $kind): string {
  $c = new WkCanvas(1000, 600);
  switch ($kind) {
    case 'book':
      $c->gradient('#5b2c83', '#7a4aa3');
      $c->ell(820, 120, 260, 260, '#f2a900');
      $c->ell(180, 520, 300, 300, '#ffffff', 112);
      $c->poly([[250, 190], [500, 240], [500, 440], [250, 390]], '#ffffff');
      $c->poly([[750, 190], [500, 240], [500, 440], [750, 390]], '#f1eaf8');
      $c->poly([[250, 390], [500, 440], [750, 390], [750, 410], [500, 462], [250, 410]], '#3c1a5b');
      $c->line(500, 240, 500, 440, 6, '#f2a900');
      foreach ([270, 305, 340] as $y) {
        $c->line(290, $y, 460, $y + 30, 5, '#cdbfe0');
        $c->line(540, $y + 30, 710, $y, 5, '#cdbfe0');
      }
      break;

    case 'music':
      $c->gradient('#7a4aa3', '#43206a');
      $c->ell(180, 130, 240, 240, '#f2a900');
      $c->ell(860, 500, 320, 320, '#ffffff', 112);
      $c->ell(380, 430, 120, 84, '#ffffff');
      $c->ell(660, 380, 120, 84, '#ffffff');
      $c->rect(424, 200, 438, 430, '#ffffff');
      $c->rect(704, 150, 718, 380, '#ffffff');
      $c->poly([[424, 200], [718, 150], [718, 200], [424, 250]], '#f2a900');
      break;

    case 'ball':
      $c->gradient('#3c1a5b', '#5b2c83');
      $c->ell(150, 480, 380, 380, '#ffffff', 112);
      $c->ell(500, 300, 330, 330, '#f2a900');
      $c->ell(500, 300, 330, 330, '#2a1240', 100);
      $c->ell(500, 300, 316, 316, '#f2a900');
      $c->poly([[500, 232], [565, 280], [540, 356], [460, 356], [435, 280]], '#2a1240');
      foreach ([[500, 232, 500, 150], [565, 280, 640, 255], [540, 356, 585, 420], [460, 356, 415, 420], [435, 280, 360, 255]] as [$a, $b, $x, $y]) {
        $c->line($a, $b, $x, $y, 7, '#2a1240');
      }
      break;

    case 'science':
    default:
      $c->gradient('#2a1240', '#4a2273');
      $c->ell(860, 130, 260, 260, '#f2a900');
      $c->ell(140, 470, 300, 300, '#ffffff', 112);
      $c->rect(445, 130, 555, 150, '#f1eaf8');
      $c->rect(455, 150, 545, 280, '#f1eaf8');
      $c->poly([[455, 280], [545, 280], [700, 500], [300, 500]], '#f1eaf8');
      $c->poly([[372, 400], [628, 400], [700, 500], [300, 500]], '#f2a900');
      foreach ([[470, 350, 34], [540, 320, 24], [500, 250, 18], [585, 372, 14]] as [$x, $y, $d]) {
        $c->ell($x, $y, $d, $d, '#ffffff', 40);
      }
      break;
  }
  return $c->png();
}

function wk_media_for(string $name, string $alt, string $png, int $w, int $h) {
  $etm = \Drupal::entityTypeManager();
  $dir = 'public://sample';
  \Drupal::service('file_system')->prepareDirectory($dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
  $uri = "$dir/$name.png";
  $file = \Drupal::service('file.repository')->writeData($png, $uri, \Drupal\Core\File\FileExists::Replace);
  $file->setPermanent();
  $file->save();
  foreach (ImageStyle::loadMultiple() as $style) {
    $style->flush($uri);
  }
  $found = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $name]);
  if ($found) {
    $media = reset($found);
    $media->set('field_media_image', ['target_id' => $file->id(), 'alt' => $alt, 'width' => $w, 'height' => $h]);
  }
  else {
    $media = Media::create(['bundle' => 'image', 'name' => $name, 'status' => 1, 'uid' => 1,
      'field_media_image' => ['target_id' => $file->id(), 'alt' => $alt, 'width' => $w, 'height' => $h]]);
  }
  $media->save();
  return $media->id();
}

// Hero.
wk_media_for('sample-hero', 'Illustration of a school building with trees under a bright sun (sample artwork)', wk_hero_scene(), 1200, 900);
echo "Hero illustration written\n";

// News variants.
$variants = [
  'book' => 'Illustration of an open book (sample artwork)',
  'music' => 'Illustration of musical notes (sample artwork)',
  'ball' => 'Illustration of a sports ball (sample artwork)',
  'science' => 'Illustration of a science flask (sample artwork)',
];
$media_ids = [];
foreach ($variants as $kind => $alt) {
  $media_ids[$kind] = wk_media_for("sample-news-$kind", $alt, wk_news_art($kind), 1000, 600);
}
echo "News illustrations: " . implode(', ', array_keys($media_ids)) . "\n";

// Assign by topic (falls back to rotating).
$topics = ['reading' => 'book', 'book' => 'book', 'band' => 'music', 'choir' => 'music', 'concert' => 'music',
  'robotics' => 'science', 'career' => 'science', 'science' => 'science', 'lunch' => 'ball', 'salad' => 'ball', 'sport' => 'ball'];
$rotate = array_keys($variants);
$i = 0;
foreach (\Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'news']) as $node) {
  $kind = NULL;
  foreach ($topics as $word => $k) {
    if (stripos($node->label(), $word) !== FALSE) {
      $kind = $k;
      break;
    }
  }
  $kind = $kind ?? $rotate[$i++ % count($rotate)];
  $current = $node->get('field_image')->target_id;
  if ((int) $current !== (int) $media_ids[$kind]) {
    $node->set('field_image', ['target_id' => $media_ids[$kind]]);
    $node->setNewRevision(FALSE);
    $node->save();
    echo "  {$node->label()} -> $kind\n";
  }
}
echo "Phase 14 images complete.\n";
