<?php

declare(strict_types = 1);

namespace Drupal\oe_migration_views\Traits;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Plugin\RequirementsInterface;

/**
 * Migrate Tools Commands trait.
 *
 * This contains a verbatim copy of MigrateToolsCommands::migrationsList() from
 * migrate_tools, which is protected so can't be reused.
 * This trait can be reused and is used in OeMigrationViewsCommands.
 *
 * @see \Drupal\migrate_tools\Commands\MigrateToolsCommands::migrationsList()
 *
 * @property \Drupal\migrate\Plugin\MigrationPluginManager $migrationPluginManager
 */
trait MigrateToolsCommandsTrait {

  /**
   * Retrieve a list of active migrations.
   *
   * @param string $migration_ids
   *   Comma-separated list of migrations -
   *   if present, return only these migrations.
   * @param array $options
   *   Command options.
   *
   * @default $options []
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface[][]
   *   An array keyed by migration group, each value containing an array of
   *   migrations or an empty array if no migrations match the input criteria.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function migrationsList($migration_ids = '', array $options = []) {
    // Filter keys must match the migration configuration property name.
    $filter['migration_group'] = explode(',', $options['group']);
    $filter['migration_tags'] = explode(',', $options['tag']);

    $manager = $this->migrationPluginManager;
    $plugins = $manager->createInstances([]);
    $matched_migrations = [];

    // Get the set of migrations that may be filtered.
    if (empty($migration_ids)) {
      $matched_migrations = $plugins;
    }
    else {
      // Get the requested migrations.
      $migration_ids = explode(',', mb_strtolower($migration_ids));
      foreach ($plugins as $id => $migration) {
        if (in_array(mb_strtolower($id), $migration_ids)) {
          $matched_migrations[$id] = $migration;
        }
      }
    }

    // Do not return any migrations which fail to meet requirements.
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    foreach ($matched_migrations as $id => $migration) {
      try {
        if ($migration->getSourcePlugin() instanceof RequirementsInterface) {
          $migration->getSourcePlugin()->checkRequirements();
        }
      }
      catch (RequirementsException $e) {
        unset($matched_migrations[$id]);
      }
      catch (PluginNotFoundException $exception) {
        if ($options['continue-on-failure']) {
          $this->logger()->error($exception->getMessage());
          unset($matched_migrations[$id]);
        }
        else {
          throw $exception;
        }
      }
    }

    // Filters the matched migrations if a group or a tag has been input.
    if (!empty($filter['migration_group']) || !empty($filter['migration_tags'])) {
      // Get migrations in any of the specified groups and with any of the
      // specified tags.
      foreach ($filter as $property => $values) {
        if (!empty($values)) {
          $filtered_migrations = [];
          foreach ($values as $search_value) {
            foreach ($matched_migrations as $id => $migration) {
              // Cast to array because migration_tags can be an array.
              $definition = $migration->getPluginDefinition();
              $configured_values = (array) ($definition[$property] ?? NULL);
              $configured_id = in_array($search_value, $configured_values, TRUE) ? $search_value : 'default';
              if (empty($search_value) || $search_value === $configured_id) {
                if (empty($migration_ids) || in_array(
                    mb_strtolower($id),
                    $migration_ids,
                    TRUE
                  )) {
                  $filtered_migrations[$id] = $migration;
                }
              }
            }
          }
          $matched_migrations = $filtered_migrations;
        }
      }
    }

    // Sort the matched migrations by group.
    if (!empty($matched_migrations)) {
      foreach ($matched_migrations as $id => $migration) {
        $configured_group_id = empty($migration->migration_group) ? 'default' : $migration->migration_group;
        $migrations[$configured_group_id][$id] = $migration;
      }
    }
    return $migrations ?? [];
  }

}
