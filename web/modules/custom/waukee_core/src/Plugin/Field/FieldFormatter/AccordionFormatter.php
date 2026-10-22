<?php

namespace Drupal\waukee_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Renders text-with-summary items as native, keyboard-accessible accordions.
 *
 * The summary is the accordion heading, the text is the panel body.
 */
#[FieldFormatter(
  id: 'waukee_accordion',
  label: new TranslatableMarkup('Accordion (summary = heading)'),
  field_types: ['text_with_summary'],
)]
class AccordionFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'inline_template',
        '#template' => '<details class="wk-accordion"><summary>{{ summary }}</summary><div class="wk-accordion__body">{{ body }}</div></details>',
        '#context' => [
          'summary' => $item->summary,
          'body' => [
            '#type' => 'processed_text',
            '#text' => $item->value,
            '#format' => $item->format,
            '#langcode' => $item->getLangcode(),
          ],
        ],
      ];
    }
    return $elements;
  }

}
