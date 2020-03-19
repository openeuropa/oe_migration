<?php

declare(strict_types = 1);

namespace Drupal\oe_migration_views\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_migration_views\Plugin\migrate\id_map\OeMigrationViewsSql;
use Drupal\oe_migration_views\Traits\MigrateToolsCommandsTrait;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Plugin\migrate\id_map\Sql;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\Entity\MigrationGroup;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Drush\Utils\StringUtils;

/**
 * Migrate Views drush commands.
 */
class OeMigrationViewsCommands extends DrushCommands {

  use MigrateToolsCommandsTrait;

  /**
   * Migration plugin manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationPluginManager;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * OeMigrationViewsCommands constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migrationPluginManager
   *   Migration Plugin Manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   */
  public function __construct(MigrationPluginManager $migrationPluginManager, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct();
    $this->migrationPluginManager = $migrationPluginManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Generate report views for migrations.
   *
   * @param string $migration_names
   *   Restrict to a comma-separated list of migrations (Optional).
   * @param array $options
   *   Additional options for the command.
   *
   * @option group A comma-separated list of migration groups to list
   * @option tag Name of the migration tag to list
   *
   * @default $options []
   *
   * @command oe_migration_views:generate
   *
   * @validate-module-enabled oe_migration_views
   *
   * @aliases oe_migration_views-generate
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function generate($migration_names = '', array $options = [
    'group' => self::REQ,
    'tag' => self::REQ,
    'update' => FALSE,
  ]) {
    $options += ['group' => NULL, 'tag' => NULL];
    $migrations = $this->migrationsList($migration_names, $options);

    // Take it one group at a time, listing the migrations within each group.
    foreach ($migrations as $group_id => $migration_list) {
      /** @var \Drupal\migrate_plus\Entity\MigrationGroup $group */
      $group = $this->entityTypeManager->getStorage('migration_group')
        ->load($group_id);

      /** @var \Drupal\migrate\Plugin\Migration $migration */
      foreach ($migration_list as $migration) {
        $this->generateMigrationView($group, $migration);
      }
    }
  }

  /**
   * Generates a view for a given migration.
   */
  protected function generateMigrationView(MigrationGroup $group, MigrationInterface $migration) {
    $view_storage = $this->entityTypeManager->getStorage('view');

    $id_map = $migration->getIdMap();
    if (!is_a($id_map, Sql::class)) {
      $this->logger()
        ->warning(dt('Skipped %migration_id view creation: The id map is not of type %type', [
          '%migration_id' => $migration->id(),
          '%type' => '\Drupal\migrate\Plugin\migrate\id_map\Sql',
        ]));
      return;
    }

    if ($view_storage->load($migration->id())) {
      $this->logger()
        ->warning(dt('Skipped %migration_id view creation: The view already exists', ['%migration_id' => $migration->id()]));
      return;
    }

    // Create the view.
    $view = $this->createMigrationView($group, $migration);
    $view_executable = $view->getExecutable();

    // Validate the view.
    $errors = $view_executable->validate();
    if (!empty($errors)) {
      $this->logger()->error(dt('Failed %migration_id view creation:', ['%migration_id' => $migration->id()]));

      foreach ($errors as $display_errors) {
        foreach ($display_errors as $error) {
          $this->logger()->error($error);
        }
      }
      return;
    }

    // Save the view.
    $view->save();
    $this->logger()
      ->success(dt('Created %migration_id migrate report view.', ['%migration_id' => $migration->id()]));
  }

  /**
   * Creates a view for a given migration.
   */
  protected function createMigrationView(MigrationGroup $group, MigrationInterface $migration) {
    $view_storage = $this->entityTypeManager->getStorage('view');
    $group_name = !empty($group) ? $group->label() : 'Default';
    /** @var \Drupal\migrate\Plugin\migrate\id_map\Sql $id_map */
    $id_map = $migration->getIdMap();

    /** @var \Drupal\views\Entity\View $view */
    $view = $view_storage->create([
      'id' => $migration->id(),
      'label' => $group_name . ' - ' . $migration->label(),
      'base_table' => $id_map->mapTableName(),
    ]);
    $view_executable = $view->getExecutable();
    $page_display = $view_executable->newDisplay('page', $migration->label(), 'page_1');
    $page_display->setOption('path', 'admin/structure/migrate/manage/' . $group->id() . '/migrations/' . $migration->id() . '/reports');
    $page_display->setOption('style', [
      'type' => 'table',
      'options' => [
        'row_class' => '',
        'default_row_class' => TRUE,
      ],
    ]);
    $page_display->setOption('pager', [
      'type' => 'full',
      'options' => [
        'items_per_page' => 200,
        'offset' => 0,
      ],
    ]);
    $page_display->setOption('access', [
      'type' => 'perm',
      'options' => [
        'perm' => 'view migrate reports',
      ],
    ]);

    // Add source fields.
    $map_table = $id_map->mapTableName();
    $view_executable->addHandler('default', 'field', $map_table, 'source_row_status');

    $source_id_field_names = array_keys($migration->getSourcePlugin()->getIds());
    $count = 0;
    foreach ($source_id_field_names as $id_definition) {
      $count++;
      $view_executable->addHandler('default', 'field', $map_table, 'sourceid' . $count, [
        'label' => 'Source: ' . $id_definition,
      ]);
    }

    if (is_a($id_map, OeMigrationViewsSql::class)) {
      $source_plugin = $migration->getSourcePlugin();
      $source_fields = $source_plugin->fields();
      foreach ($source_fields as $key => $label) {
        $view_executable->addHandler('default', 'field', $map_table, 'source_data', [
          'format' => 'key',
          'key' => $key,
          'label' => 'Source: ' . $label,
        ]);
      }
    }

    // Add destination relationship/fields.
    $destination_plugin = $migration->getDestinationPlugin();
    if (is_a($destination_plugin, EntityContentBase::class)) {
      list (, $entity_type_id) = explode(':', $destination_plugin->getPluginId());
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $base_table = $entity_type->getDataTable() ?: $entity_type->getBaseTable();
      if (isset($base_table)) {
        // Relationship.
        $view_executable->addHandler('default', 'relationship', $map_table, 'migrate_map_' . $base_table);

        // Fields.
        $destination_id_field_names = $migration->getDestinationPlugin()->getIds();
        $count = 0;
        foreach ($destination_id_field_names as $id_definition => $schema) {
          $count++;
          $view_executable->addHandler('default', 'field', $map_table, 'destid' . $count, [
            'label' => 'Destination: ' . $id_definition,
          ]);
        }
      }
    }

    // Add migrate messages field.
    $view_executable->addHandler('default', 'field', $map_table, 'migrate_messages');

    return $view;
  }

  /**
   * Drop source_data and destination_data columns from migrate_map table(s).
   *
   * @param array $options
   *   An array of options.
   *
   * @option tables
   *   A column-separated list of migrate_map tables.
   * @option all
   *   Perform operation on all migrate_map table(s).
   *
   * @usage oe_migration_views-cleanup-map-tables --all
   *   Drop source_data, destination_data columns from all migrate_map table(s).
   * @usage oe_migration_views-cleanup-map-tables --tables="migrate_map_articles"
   *   Drop source_data, destination_data columns from migrate_map_articles
   *   table.
   * @usage oe_migration_views-cleanup-map-tables --tables="migrate_map_articles, migrate_map_pages"
   *   Drop source_data, destination_data columns from migrate_map_articles
   *   and migrate_map_pages tables.
   *
   * @command oe_migration_views:cleanup-map-tables
   *
   * @aliases oe_migration_views-cleanup-map-tables, oe_migration_views-cleanup
   *
   * @throws \Drush\Exceptions\UserAbortException
   */
  public function cleanup(array $options = ['tables' => '', 'all' => FALSE]) {
    $database_schema = \Drupal::database()->schema();

    if ($options['all']) {
      $tables = $database_schema->findTables('migrate_map_%');
    }
    else {
      $tables = [];
      $input = StringUtils::csvToArray($options['tables']);
      foreach ($input as $table) {
        if ($database_schema->tableExists($table)) {
          $tables[$table] = $table;
        }
      }
    }

    if (empty($tables)) {
      throw new \Exception(dt('No table(s) found.'));
    }

    if (!$this->io()->confirm(dt('Cleanup will be performed on the following table(s): @tables', ['@tables' => implode(', ', $tables)]))) {
      throw new UserAbortException();
    }

    foreach ($tables as $table) {
      foreach (['source_data', 'destination_data'] as $column) {
        if ($database_schema->fieldExists($table, $column)) {
          $database_schema->dropField($table, $column);
          $this->logger()->success(dt('Removed @column column from @table', [
            'column' => $column,
            '@table' => $table,
          ]));
        }
      }
    }
  }

}
