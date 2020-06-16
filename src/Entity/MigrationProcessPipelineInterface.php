<?php

declare(strict_types = 1);

namespace Drupal\oe_migration\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Interface for migration process pipelines.
 */
interface MigrationProcessPipelineInterface extends ConfigEntityInterface {

  /**
   * Get the normalized process pipeline config describing the process plugins.
   *
   * The process configuration is always normalized. All shorthand processing
   * will be expanded into their full representations.
   *
   * @param array $config
   *   Configuration values to pass to the process.
   *
   * @return array
   *   The normalized configuration describing the process plugins.
   *
   * @see https://www.drupal.org/node/2129651#get-shorthand
   */
  public function getDefinitions(array $config);

}
