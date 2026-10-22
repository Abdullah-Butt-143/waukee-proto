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
    foreach ($storage->loadMultiple($ids) as $group) {
      $value = fn(string $field) => $group->hasField($field) ? $group->get($field)->value : '';
      $schools[] = [
        'name' => $group->label(),
        'url' => $group->toUrl()->toString(),
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
