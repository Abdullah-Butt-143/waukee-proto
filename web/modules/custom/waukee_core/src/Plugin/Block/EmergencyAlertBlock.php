<?php

namespace Drupal\waukee_core\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Site-wide emergency alert banner.
 *
 * Shows the most recently changed reusable "Emergency alert" content block
 * whose Active box is checked, and nothing at all when none is active. Placed
 * once in the theme's full-width alert region, it appears on every page of
 * the district site and every school site at the same time.
 */
#[Block(
  id: 'waukee_emergency_alert',
  admin_label: new TranslatableMarkup('Emergency alert banner'),
  category: new TranslatableMarkup('Waukee'),
)]
class EmergencyAlertBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the block.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $storage = $this->entityTypeManager->getStorage('block_content');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'emergency_alert')
      ->condition('reusable', 1)
      ->condition('field_active', 1)
      ->sort('changed', 'DESC')
      ->range(0, 1)
      ->execute();

    $build = [
      // Any block content change (activate, edit, deactivate) refreshes this.
      '#cache' => ['tags' => ['block_content_list']],
    ];
    if (!$ids) {
      return $build;
    }
    $entity = $storage->load(reset($ids));
    $build['alert'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['wk-emergency-alert'], 'role' => 'alert'],
      'label' => ['#markup' => '<strong class="wk-emergency-alert__label">' . $this->t('Alert') . '</strong> '],
      'content' => $this->entityTypeManager->getViewBuilder('block_content')->view($entity, 'default'),
    ];
    $build['#cache']['tags'] = array_merge($build['#cache']['tags'], $entity->getCacheTags());
    return $build;
  }

}
