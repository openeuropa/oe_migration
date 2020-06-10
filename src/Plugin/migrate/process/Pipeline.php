<?php

declare(strict_types = 1);

namespace Drupal\oe_migration\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\oe_migration\Entity\MigrationProcessPipeline;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This plugin executes a process pipeline loaded from configuration.
 *
 * This plugin is useful to avoid process duplications in Yaml files.
 * You may have a suite of process plugins that you are using in several places
 * or a single process plugin with a complex/specific configuration that you
 * don't want to replicate in several places.
 *
 *  Available configuration keys:
 *   - id: The ID of the oe_migration_process_pipeline configuration entity.
 *   - placeholders (optional): an array of placeholders to replace in the
 *     pipeline definition. Useful to increase the possibility of pipeline
 *     reuse.
 *
 * @codingStandardsIgnoreStart
 * Usage example:
 * @code
 * process:
 *   'body/value':
 *     - plugin: oe_migration_pipeline
 *       id: my_process_pipeline
 *       source: 'body/0/value'
 *       placeholders:
 *         TOKEN1: value_token1
 * @endcode
 *
 * The above example expects a process pipeline "my_process_pipeline" defined
 * in configuration.
 *
 * This my_process_pipeline has to be an instance of a configuration entity with
 * type 'oe_migration_process_pipeline'.
 * @see oe_migration.schema.yml
 *
 * @MigrateProcessPlugin(
 *   id = "oe_migration_pipeline",
 *   handle_multiples = TRUE
 * )
 */
class Pipeline extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The process pipeline.
   *
   * @var \Drupal\oe_migration\Entity\MigrationProcessPipelineInterface
   */
  protected $pipeline;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration += [
      'placeholders' => [],
    ];
    $this->validateConfiguration();

    $this->pipeline = $entity_type_manager->getStorage('oe_migration_process_pipeline')->load($this->configuration['id']);
    if (empty($this->pipeline)) {
      throw new MigrateException(sprintf('The pipeline plugin could not load the given process pipeline "%s".', $this->configuration['id']));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function transform($value, MigrateExecutableInterface $migrateExecutable, Row $row, $destinationProperty) {
    // Execute the pipeline.
    $process = $this->pipeline->getDefinitions($this->configuration['placeholders']);
    $migrateExecutable->processRow($row, [$destinationProperty => $process], $value);
    return $row->getDestinationProperty($destinationProperty);
  }

  /**
   * {@inheritdoc}
   */
  protected function validateConfiguration() {
    // Check the pipeline.
    if (!isset($this->configuration['id']) || empty($this->configuration['id']) || !is_string($this->configuration['id'])) {
      throw new \InvalidArgumentException('The pipeline plugin requires a pipeline ID, none found.');
    }
  }

}
