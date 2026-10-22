<?php

namespace Drupal\waukee_core\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Lists the school groups as cards on /schools.
 */
class SchoolsController extends ControllerBase {

  /**
   * Builds the schools listing.
   */
  public function build(): array {
    $storage = $this->entityTypeManager()->getStorage('group');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'school')
      ->sort('label')
      ->execute();
    $schools = [];
    $nodes = $this->entityTypeManager()->getStorage('node');
    foreach ($storage->loadMultiple($ids) as $group) {
      $value = fn(string $field) => $group->hasField($field) ? $group->get($field)->value : '';
      // The school's site is its landing page (a Basic page titled like the
      // school); fall back to the group page if there is none.
      $landing = $nodes->loadByProperties(['type' => 'page', 'title' => $group->label(), 'status' => 1]);
      $schools[] = [
        'name' => $group->label(),
        'url' => $landing ? reset($landing)->toUrl()->toString() : $group->toUrl()->toString(),
        'grades' => $value('field_grades'),
        'address' => $value('field_address'),
        'phone' => $value('field_phone'),
        'principal' => $value('field_principal'),
        'summary' => $value('field_summary'),
      ];
    }
    return [
      '#theme' => 'waukee_schools',
      '#schools' => $schools,
      '#cache' => ['tags' => ['group_list']],
    ];
  }

}
