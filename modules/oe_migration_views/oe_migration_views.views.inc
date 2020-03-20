<?php

/**
 * @file
 * Contains hooks for Views.
 */

declare(strict_types = 1);

use Drupal\oe_migration_views\Plugin\migrate\id_map\SqlData;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Plugin\migrate\id_map\Sql;
use Drupal\migrate\Plugin\Migration;

/**
 * Implements hook_views_data_alter().
 */
function oe_migration_views_views_data_alter(&$data) {
  $plugin_manager = \Drupal::service('plugin.manager.migration');
  $migrations = $plugin_manager->createInstances([]);

  /** @var \Drupal\migrate\Plugin\Migration $migration */
  foreach ($migrations as $migration) {
    /** @var \Drupal\migrate\Plugin\migrate\id_map\Sql $id_map */
    $id_map = $migration->getIdMap();
    if (!is_a($id_map, Sql::class)) {
      continue;
    }

    // Define migrate map tables and fields.
    _oe_migration_views_views_data_alter_migrate_map_definitions($data, $migration);

    // Add source_data and destination_data fields to migrate map tables for
    // the "oe_migration_views_sql_data" id_map plugin.
    _oe_migration_views_views_data_alter_migrate_map_oe_migration_views_sql_data_definitions($data, $migration);

    // Define relationships from migrate map tables to entity tables.
    _oe_migration_views_views_data_alter_migrate_map_relationship_definitions($data, $migration);

    // Define relationships from entity tables to migrate map tables.
    _oe_migration_views_views_data_alter_entity_migrate_map_definitions($data, $migration);

    // Define migrate message tables and fields.
    _oe_migration_views_views_data_alter_migrate_message_definitions($data, $migration);
  }
}

/**
 * Defines migrate map tables and fields.
 *
 * @param array $data
 *   The array of all information about Views tables and fields, collected from
 *   hook_views_data(), passed by reference.
 * @param \Drupal\migrate\Plugin\Migration $migration
 *   The migration plugin used to build the migrate map declaration.
 *
 * @throws \Drupal\migrate\MigrateSkipRowException
 */
function _oe_migration_views_views_data_alter_migrate_map_definitions(array &$data, Migration $migration) {
  $id_map = $migration->getIdMap();
  $map_table = $id_map->mapTableName();

  // Migrate map table definition.
  $data[$map_table]['table']['group'] = t('Migrate');
  $data[$map_table]['table']['base'] = [
    'field' => 'sourceid1',
    'title' => $map_table,
    'help' => t('Migrate map.'),
  ];

  $source_id_field_names = array_keys($migration->getSourcePlugin()->getIds());
  $count = 0;
  foreach ($source_id_field_names as $id_definition) {
    $count++;
    $title = t('Source ID @count (@name)', [
      '@count' => $count,
      '@name' => $id_definition,
    ]);
    $data[$map_table]['sourceid' . $count] = [
      'title' => $title,
      'help' => $title,
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
  }

  $destination_id_field_names = $migration->getDestinationPlugin()->getIds();
  $count = 0;
  foreach ($destination_id_field_names as $id_definition => $schema) {
    $count++;
    $title = t('Destination ID @count (@name)', [
      '@count' => $count,
      '@name' => $id_definition,
    ]);
    $data[$map_table]['destid' . $count] = [
      'title' => $title,
      'help' => $title,
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];
  }

  $data[$map_table]['source_row_status'] = [
    'title' => 'Source row status',
    'help' => 'Source row status',
    'field' => [
      'id' => 'oe_migration_views_migrate_map_source_row_status',
    ],
    'filter' => [
      'id' => 'oe_migration_views_migrate_map_source_row_status',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data[$map_table]['rollback_action'] = [
    'title' => 'Rollback action',
    'help' => 'Rollback action',
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data[$map_table]['last_imported'] = [
    'title' => 'Last imported',
    'help' => 'Last imported',
    'field' => [
      'id' => 'date',
    ],
    'argument' => [
      'id' => 'date',
    ],
    'filter' => [
      'id' => 'date',
    ],
    'sort' => [
      'id' => 'date',
    ],
  ];

  $data[$map_table]['source_ids_hash'] = [
    'title' => 'Source IDs hash',
    'help' => 'Source IDs hash',
    'field' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'relationship' => [
      'title' => t('Migrate messages'),
      'label' => t('Migrate messages'),
      'help' => t('Migrate messages logged.'),
      'base' => $id_map->messageTableName(),
      'base field' => 'source_ids_hash',
      'id' => 'standard',
    ],
  ];

  $data[$map_table]['migrate_messages'] = [
    'title' => t('Migrate messages'),
    'help' => t('Returns all migrate messages for this migrate_map entry.'),
    'field' => [
      'id' => 'oe_migration_views_migrate_messages',
      'real field' => 'source_ids_hash',
      'message_table' => $id_map->messageTableName(),
    ],
  ];
}

/**
 * Adds source_data and destination_data fields to migrate map tables.
 *
 * @param array $data
 *   The array of all information about Views tables and fields, collected from
 *   hook_views_data(), passed by reference.
 * @param \Drupal\migrate\Plugin\Migration $migration
 *   The migration plugin used to build the migrate map declaration.
 */
function _oe_migration_views_views_data_alter_migrate_map_oe_migration_views_sql_data_definitions(array &$data, Migration $migration) {
  $id_map = $migration->getIdMap();
  if (!is_a($id_map, SqlData::class)) {
    return;
  }

  $map_table = $id_map->mapTableName();
  $data[$map_table]['source_data'] = [
    'title' => 'Source data array',
    'help' => 'The raw source data (as array) from the migrate source plugin',
    'field' => [
      'id' => 'serialized',
      'click sortable' => FALSE,
    ],
    'argument' => [
      'id' => 'string',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];
  $data[$map_table]['destination_data'] = [
    'title' => 'Destination data array',
    'help' => 'The raw destination data (as array) after the migrate process',
    'field' => [
      'id' => 'serialized',
      'click sortable' => FALSE,
    ],
    'argument' => [
      'id' => 'string',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];
}

/**
 * Defines migrate map tables relationships to Views.
 *
 * @param array $data
 *   The array of all information about Views tables and fields, collected from
 *   hook_views_data(), passed by reference.
 * @param \Drupal\migrate\Plugin\Migration $migration
 *   The migration plugin used to build the migrate map declaration.
 *
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\migrate\MigrateException
 * @throws \Drupal\migrate\MigrateSkipRowException
 */
function _oe_migration_views_views_data_alter_migrate_map_relationship_definitions(array &$data, Migration $migration) {
  $id_map = $migration->getIdMap();
  $map_table = $id_map->mapTableName();

  $destination_plugin = $migration->getDestinationPlugin();
  if (is_a($destination_plugin, EntityContentBase::class)) {
    [, $entity_type_id] = explode(':', $destination_plugin->getPluginId());
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $base_table = $entity_type->getDataTable() ?: $entity_type->getBaseTable();
    if (isset($data[$base_table])) {
      $extra = [];

      // Handle the JOIN on language.
      if ($entity_type->isTranslatable()) {
        /** @var \Drupal\migrate\Plugin\migrate\destination\EntityContentBase $destination_plugin */
        if ($destination_plugin->isTranslationDestination()) {
          $destid_field = 'destid' . count($destination_plugin->getIds());
          $extra[] = [
            'field' => 'langcode',
            'left_field' => $destid_field,
          ];
        }
        else {
          $extra[] = [
            'field' => 'default_langcode',
            'value' => 1,
          ];
        }
      }

      $id_field = $entity_type->getKey('id');
      $data[$map_table]['migrate_map_' . $base_table] = [
        'real field' => 'destid1',
        'title' => t('Relate each migrate mapping with a @entity_label entity', ['@entity_label' => $entity_type->getLabel()]),
        'help' => t('Relate this migrate map table to an entity table.'),
        'relationship' => [
          'label' => t('@entity_label entity', ['@entity_label' => $entity_type->getLabel()]),
          'id' => 'standard',
          'entity_type' => $entity_type_id,
          'base' => $base_table,
          'base field' => $id_field,
          'extra' => $extra,
        ],
      ];
    }
  }
}

/**
 * Defines relationships from entity tables to migrate map tables.
 *
 * @param array $data
 *   The array of all information about Views tables and fields, collected from
 *   hook_views_data(), passed by reference.
 * @param \Drupal\migrate\Plugin\Migration $migration
 *   The migration plugin used to build the migrate map declaration.
 */
function _oe_migration_views_views_data_alter_entity_migrate_map_definitions(array &$data, Migration $migration) {
  $id_map = $migration->getIdMap();
  $map_table = $id_map->mapTableName();
  $entity_types = \Drupal::entityTypeManager()->getDefinitions();
  foreach ($entity_types as $entity_type_id => $entity_type) {
    $base_table = $entity_type->getBaseTable();
    if (isset($data[$base_table])) {
      $id_field = $entity_type->getKey('id');
      $data[$base_table]['migrate_map_' . $map_table] = [
        'real field' => $id_field,
        'title' => t('Migrate map @map_table', ['@map_table' => $map_table]),
        'help' => t('Relate this entity to a migration map table.'),
        'relationship' => [
          'label' => t('Migrate map @map_table', ['@map_table' => $map_table]),
          'id' => 'standard',
          'base' => $map_table,
          'base field' => 'destid1',
        ],
      ];
    }
  }
}

/**
 * Defines migrate messages tables and fields.
 *
 * @param array $data
 *   The array of all information about Views tables and fields, collected from
 *   hook_views_data(), passed by reference.
 * @param \Drupal\migrate\Plugin\Migration $migration
 *   The migration plugin used to build the migrate message declaration.
 */
function _oe_migration_views_views_data_alter_migrate_message_definitions(array &$data, Migration $migration) {
  $id_map = $migration->getIdMap();
  $message_table = $id_map->messageTableName();

  // Migrate map table definition.
  $data[$message_table]['table']['group'] = t('Migrate');
  $data[$message_table]['table']['base'] = [
    'field' => 'msgid',
    'title' => $message_table,
    'help' => t('Migrate message.'),
  ];

  $data[$message_table]['msgid'] = [
    'title' => t('MSGID'),
    'help' => t('Unique message ID.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data[$message_table]['source_ids_hash'] = [
    'title' => 'Source IDs hash',
    'help' => 'Source IDs hash',
    'field' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'relationship' => [
      'title' => t('Migrate map'),
      'label' => t('Migrate map'),
      'help' => t('Migrate map entry.'),
      'base' => $id_map->mapTableName(),
      'base field' => 'source_ids_hash',
      'id' => 'standard',
    ],
  ];

  $data[$message_table]['level'] = [
    'title' => 'Message level',
    'help' => 'Message level',
    'field' => [
      'id' => 'oe_migration_views_migrate_message_level',
    ],
    'argument' => [
      'id' => 'oe_migration_views_migrate_message_level',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data[$message_table]['source_row_status'] = [
    'title' => 'Source row status',
    'help' => 'Source row status',
    'field' => [
      'id' => 'oe_migration_views_migrate_map_source_row_status',
    ],
    'filter' => [
      'id' => 'oe_migration_views_migrate_map_source_row_status',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data[$message_table]['message'] = [
    'title' => 'Message',
    'help' => 'Message',
    'field' => [
      'id' => 'standard',
    ],
    'argument' => [
      'id' => 'string',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];
}
