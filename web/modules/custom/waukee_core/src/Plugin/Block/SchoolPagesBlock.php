<?php

namespace Drupal\waukee_core\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Lists the published pages that belong to one school.
 *
 * Pages created through a school's "Add page" link are group content of that
 * school, so they appear here without any menu work. The school's own landing
 * page (same title as the school) is left out.
 */
#[Block(
  id: 'waukee_school_pages',
  admin_label: new TranslatableMarkup('School pages list'),
  category: new TranslatableMarkup('Lists (Views)'),
)]
class SchoolPagesBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['group_id' => 0, 'limit' => 12];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $options = [];
    foreach (\Drupal::entityTypeManager()->getStorage('group')->loadByProperties(['type' => 'school']) as $group) {
      $options[$group->id()] = $group->label();
    }
    $form['group_id'] = [
      '#type' => 'select',
      '#title' => $this->t('School'),
      '#options' => $options,
      '#default_value' => $this->configuration['group_id'],
      '#required' => TRUE,
    ];
    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum pages'),
      '#min' => 1,
      '#max' => 50,
      '#default_value' => $this->configuration['limit'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['group_id'] = (int) $form_state->getValue('group_id');
    $this->configuration['limit'] = (int) $form_state->getValue('limit');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $etm = \Drupal::entityTypeManager();
    $group = $etm->getStorage('group')->load($this->configuration['group_id']);
    if (!$group) {
      return [];
    }
    $relationships = $etm->getStorage('group_relationship')->loadByProperties([
      'gid' => $group->id(),
      'plugin_id' => 'group_node:page',
    ]);
    $items = [];
    foreach ($relationships as $relationship) {
      $node = $relationship->getEntity();
      if (!$node || !$node->isPublished() || $node->label() === $group->label() || !$node->access('view')) {
        continue;
      }
      $items[$node->getChangedTime() . '-' . $node->id()] = [
        '#type' => 'link',
        '#title' => $node->label(),
        '#url' => $node->toUrl(),
      ];
    }
    krsort($items);
    $items = array_slice(array_values($items), 0, (int) $this->configuration['limit']);

    $build = [
      '#cache' => [
        'tags' => Cache::mergeTags(['group_relationship_list', 'node_list'], $group->getCacheTags()),
        'contexts' => ['user.permissions'],
      ],
    ];
    if (!$items) {
      return $build;
    }
    $build['list'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Pages from @school', ['@school' => $group->label()]),
      '#items' => $items,
      '#attributes' => ['class' => ['wk-school-pages']],
    ];
    return $build;
  }

}
