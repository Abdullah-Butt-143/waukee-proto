<?php

namespace Drupal\waukee_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Splits a "a|b|c;;a|b|c" column into Paragraph entities.
 *
 * Each ";;"-separated record is split again on "|"; the resulting parts are
 * mapped onto paragraph field names by position (field_map) and saved as a
 * new Paragraph of the given bundle. Returns one
 * ['target_id' => ..., 'target_revision_id' => ...] pair per record, which
 * is exactly what an entity_reference_revisions field expects — no
 * sub_process wrapper needed, because multiple() below tells Migrate this
 * plugin already returns the full multi-value array itself.
 *
 * Example:
 * @code
 * process:
 *   field_staff:
 *     plugin: delimited_paragraphs
 *     source: staff
 *     bundle: staff_member
 *     record_delimiter: ';;'
 *     field_delimiter: '|'
 *     field_map:
 *       0: field_staff_name
 *       1: field_staff_role
 *       2: field_staff_email
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "delimited_paragraphs"
 * )
 */
class DelimitedParagraphs extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($value === NULL || $value === '') {
      return [];
    }
    if (empty($this->configuration['bundle'])) {
      throw new \InvalidArgumentException('The delimited_paragraphs plugin requires a "bundle" configuration key.');
    }
    if (empty($this->configuration['field_map']) || !is_array($this->configuration['field_map'])) {
      throw new \InvalidArgumentException('The delimited_paragraphs plugin requires a "field_map" configuration key (position => field name).');
    }

    $bundle = $this->configuration['bundle'];
    $field_map = $this->configuration['field_map'];
    $record_delimiter = $this->configuration['record_delimiter'] ?? ';;';
    $field_delimiter = $this->configuration['field_delimiter'] ?? '|';

    $results = [];
    foreach (explode($record_delimiter, (string) $value) as $record) {
      $record = trim($record);
      if ($record === '') {
        continue;
      }
      $parts = array_map('trim', explode($field_delimiter, $record));
      $values = ['type' => $bundle];
      foreach ($field_map as $position => $field_name) {
        if (isset($parts[$position]) && $parts[$position] !== '') {
          $values[$field_name] = $parts[$position];
        }
      }
      $paragraph = Paragraph::create($values);
      $paragraph->save();
      $results[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function multiple(): bool {
    return TRUE;
  }

}
