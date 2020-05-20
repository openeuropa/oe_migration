<?php

declare(strict_types = 1);

namespace Drupal\oe_migration;

/**
 * Interface ValidConfigurableMigrationPluginInterface.
 *
 * Forces the validation of the configuration key values in order to prevent
 * erroneous migrations to be executed.
 *
 * It should be implemented by every configurable migration plugin.
 *
 * @package Drupal\oe_migration
 */
interface ValidConfigurableMigrationPluginInterface {

  /**
   * Validates the configuration keys.
   *
   * @param array|null $keys
   *   An optional list of configuration keys to validate.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function validateConfigurationKeys(array $keys = NULL): void;

}
