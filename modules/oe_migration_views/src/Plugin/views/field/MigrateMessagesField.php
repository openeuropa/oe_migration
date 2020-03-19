<?php

declare(strict_types = 1);

namespace Drupal\oe_migration_views\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Defines a field handler to display all migrate messages.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("migrate_messages")
 */
class MigrateMessagesField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if (!isset($this->configuration['message_table'])) {
      // @TODO Log/print or throw exception?
      return '';
    }

    $source_ids_hash = $this->getValue($values);
    $result = $this->getMigrateMessages($this->configuration['message_table'], $source_ids_hash);
    $levels = _oe_migration_views_migrate_message_level_options();

    $rows = [];
    foreach ($result as $message_row) {
      $level = isset($levels[$message_row->level]) ? $levels[$message_row->level] : $this->t('Unknown');
      $rows[] = strtoupper($level) . ': ' . $message_row->message;
    }

    return !empty($rows) ? $this->sanitizeValue(implode("\n", $rows)) : '';
  }

  /**
   * Returns the migrate messages for the given table and source_ids_hash.
   *
   * @param string $message_table
   *   The message table to query.
   * @param string $source_ids_hash
   *   The source_ids_hash to filter on.
   *
   * @return \Drupal\Core\Database\StatementInterface|null
   *   A query result set containing the results of the query.
   */
  protected function getMigrateMessages($message_table, $source_ids_hash) {
    $query = \Drupal::database()->select($message_table, 'msg')
      ->condition('source_ids_hash', $source_ids_hash);
    $query->fields('msg');
    return $query->execute();
  }

}
