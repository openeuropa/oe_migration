<?php

declare(strict_types = 1);

namespace Drupal\oe_migration_views\Plugin\migrate\id_map;

use Drupal\migrate\Plugin\migrate\id_map\Sql;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Row;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateMapSaveEvent;

/**
 * Defines the sql based ID map implementation.
 *
 * It creates one map and one message table per migration entity to store the
 * relevant information.
 *
 * @PluginID("oe_migration_views_sql")
 */
class OeMigrationViewsSql extends Sql {

  /**
   * {@inheritdoc}
   */
  protected function ensureTables() {
    parent::ensureTables();

    $schema = $this->getDatabase()->schema();
    foreach (['source_data', 'destination_data'] as $field) {
      if (!$schema->fieldExists($this->mapTableName, $field)) {
        $schema->addField($this->mapTableName, $field, [
          'type' => 'blob',
          'size' => 'big',
          'serialize' => TRUE,
          'not null' => FALSE,
          'description' => "The serialized $field.",
        ]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveIdMapping(Row $row, array $destination_id_values, $source_row_status = MigrateIdMapInterface::STATUS_IMPORTED, $rollback_action = MigrateIdMapInterface::ROLLBACK_DELETE) {
    // Construct the source key.
    $source_id_values = $row->getSourceIdValues();
    // Construct the source key and initialize to empty variable keys.
    $fields = [];
    foreach ($this->sourceIdFields() as $field_name => $key_name) {
      // A NULL key value is usually an indication of a problem.
      if (!isset($source_id_values[$field_name])) {
        $this->message->display($this->t(
          'Did not save to map table due to NULL value for key field @field',
          ['@field' => $field_name]), 'error');
        return;
      }
      $fields[$key_name] = $source_id_values[$field_name];
    }

    if (!$fields) {
      return;
    }

    $fields += [
      'source_row_status' => (int) $source_row_status,
      'rollback_action' => (int) $rollback_action,
      'hash' => $row->getHash(),
    ];
    $count = 0;
    foreach ($destination_id_values as $dest_id) {
      $fields['destid' . ++$count] = $dest_id;
    }
    if ($count && $count != count($this->destinationIdFields())) {
      $this->message->display(t('Could not save to map table due to missing destination id values'), 'error');
      return;
    }
    if ($this->migration->getTrackLastImported()) {
      $fields['last_imported'] = time();
    }
    $keys = [static::SOURCE_IDS_HASH => $this->getSourceIdsHash($source_id_values)];

    // Add source and destination data.
    $fields['source_data'] = serialize($row->getSource());
    $fields['destination_data'] = serialize($row->getDestination());

    // Notify anyone listening of the map row we're about to save.
    $this->eventDispatcher->dispatch(MigrateEvents::MAP_SAVE, new MigrateMapSaveEvent($this, $fields));
    $this->getDatabase()->merge($this->mapTableName())
      ->key($keys)
      ->fields($fields)
      ->execute();
  }

}
