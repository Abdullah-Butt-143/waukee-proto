<?php

namespace Drupal\waukee_core\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters content by school and, optionally, also keeps district-wide content.
 *
 * This is how district news and events appear on every school site: choosing
 * "Sample Elementary" returns items tagged Sample Elementary OR
 * "District (all schools)". Works with any node that references the school
 * vocabulary (taxonomy_index).
 */
#[ViewsFilter('waukee_school')]
class SchoolFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  protected $alwaysMultiple = FALSE;

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['value']['default'] = '';
    $options['include_district']['default'] = TRUE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['include_district'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Also include district-wide items'),
      '#description' => $this->t('Items tagged "District (all schools)" match every school.'),
      '#default_value' => $this->options['include_district'],
    ];
  }

  /**
   * School terms keyed by term ID.
   */
  protected function schoolOptions(): array {
    $options = [];
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('school', 0, 1, TRUE);
    foreach ($terms as $term) {
      if ($term->label() !== self::districtLabel()) {
        $options[$term->id()] = $term->label();
      }
    }
    return $options;
  }

  /**
   * Label of the district-wide school term.
   */
  public static function districtLabel(): string {
    return 'District (all schools)';
  }

  /**
   * Term ID of the district-wide term, if present.
   */
  protected function districtTid(): ?int {
    $found = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'school', 'name' => self::districtLabel()]);
    return $found ? (int) reset($found)->id() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $options = $this->schoolOptions();
    $exposed = $form_state->get('exposed');
    if ($exposed) {
      $options = ['All' => $this->t('- Any -')] + $options;
    }
    $form['value'] = [
      '#type' => 'select',
      '#title' => $exposed ? $this->t('School') : $this->t('School'),
      '#options' => $options,
      '#default_value' => is_array($this->value) ? reset($this->value) : $this->value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if ($this->isExposed()) {
      return $this->t('exposed');
    }
    $options = $this->schoolOptions();
    $value = is_array($this->value) ? reset($this->value) : $this->value;
    return $options[$value] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $value = is_array($this->value) ? reset($this->value) : $this->value;
    if ($value === '' || $value === NULL || $value === 'All') {
      return;
    }
    $this->ensureMyTable();
    $tids = [(int) $value];
    if (!empty($this->options['include_district']) && ($district = $this->districtTid())) {
      $tids[] = $district;
    }
    $this->query->addWhereExpression(
      $this->options['group'],
      "$this->tableAlias.nid IN (SELECT ti.nid FROM {taxonomy_index} ti WHERE ti.tid IN (:waukee_school_tids[]))",
      [':waukee_school_tids[]' => $tids]
    );
  }

}
