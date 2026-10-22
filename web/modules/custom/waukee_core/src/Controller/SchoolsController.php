<?php

namespace Drupal\waukee_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /schools (directory) and /schools/{school} (one school's page).
 *
 * Both routes render an empty markup array: the actual HTML comes entirely
 * from theme page templates (page--schools.html.twig and
 * page--schools--detail.html.twig), fed by waukee_theme_preprocess_page().
 * This controller only needs to provide the 404 and cache tags.
 */
class SchoolsController extends ControllerBase {

  /**
   * /schools: the directory. Data is loaded in preprocess_page.
   */
  public function build(): array {
    return [
      '#markup' => '',
      '#cache' => ['tags' => ['group_list']],
    ];
  }

  /**
   * /schools/{school}: one school's page.
   */
  public function detail(string $school): array {
    $group = waukee_core_school_by_slug($school);
    if (!$group) {
      throw new NotFoundHttpException();
    }
    return [
      '#markup' => '',
      '#cache' => ['tags' => $group->getCacheTags()],
    ];
  }

  /**
   * Title callback for waukee_core.school_detail.
   */
  public function detailTitle(string $school): string {
    $group = waukee_core_school_by_slug($school);
    return $group ? (string) $group->label() : (string) $this->t('School');
  }

  /**
   * Routes (e.g. /academics) whose markup lives entirely in a theme page
   * template named for the route's path (e.g. page--academics.html.twig).
   */
  public function staticPage(): array {
    return ['#markup' => ''];
  }

}
