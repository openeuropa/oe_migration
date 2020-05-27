<?php

declare(strict_types = 1);

namespace Drupal\oe_migration\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Migration process pipeline entity.
 *
 * The migration process pipeline entity is a process plugin that can be
 * exported to configuration and contains a suite of process plugins to run.
 * It can be used in migrations as a single process plugin.
 *
 * @ConfigEntityType(
 *   id = "oe_migration_process_pipeline",
 *   label = @Translation("Migration Process Pipeline"),
 *   module = "oe_migration",
 *   handlers = {
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "module",
 *     "process",
 *   },
 * )
 */
class MigrationProcessPipeline extends ConfigEntityBase implements MigrationProcessPipelineInterface {

  /**
   * The migration process pipeline ID (machine name).
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable label for the migration process pipeline.
   *
   * @var string
   */
  protected $label;

  /**
   * The configuration describing the process plugins.
   *
   * @var array
   */
  protected $process = [];

  /**
   * {@inheritdoc}
   */
  public function getProcess(array $tokens = []) {
    return $this->getProcessNormalized($this->process, $tokens);
  }

  /**
   * Resolve shorthands into a list of plugin configurations.
   *
   * @param array $process
   *   A process configuration array.
   * @param array $tokens
   *   A process configuration array.
   *
   * @return array
   *   The normalized process configuration.
   */
  protected function getProcessNormalized(array $process, array $tokens) {
    $normalized_configurations = [];
    foreach ($process as $configuration) {
      if (is_string($configuration)) {
        $configuration = [
          'plugin' => 'get',
          'source' => $configuration,
        ];
      }
      $configuration = $this->replaceTokens($configuration, $tokens);
      $normalized_configurations[] = $configuration;
    }
    return $normalized_configurations;
  }

  /**
   * Replace all tokens in the configuration from the token array.
   *
   * @param array $configuration
   *   Process configuration array.
   * @param array $tokens
   *   Tokens array.
   *
   * @return array
   *   The configuration modified.
   */
  protected function replaceTokens(array $configuration, array $tokens) {
    foreach ($tokens as $token_name => $token_value) {
      if ($key = array_search($token_name, $configuration, TRUE)) {
        $configuration[$key] = $token_value;
      }
    }

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    // Make sure we save any explicit module dependencies.
    if ($provider = $this->get('module')) {
      $this->dependencies[] = $this->addDependency('module', $provider);
    }

    return $this->dependencies;
  }

}
