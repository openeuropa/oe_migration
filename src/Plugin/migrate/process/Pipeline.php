<?php

declare(strict_types = 1);

namespace Drupal\oe_migration\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\oe_migration\Entity\MigrationProcessPipeline;

/**
 * This plugin executes a process pipeline loaded from configuration.
 *
 * This plugin is useful to avoid duplicating process definitions in Yaml.
 * You may have a suite of process plugins you are using in several places or a
 * single process plugin with want to duplicate complex/specific configuration
 * that you don't want to duplicate.
 *
 *  Available configuration keys:
 *   - id: Pipeline identification. The ID of the pipeline that you want to use.
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
 * Create a "oe_migration.oe_migration_process_pipeline.my_process_pipeline.yml"
 * file, which can contain an unlimited amount of process plugins:
 * @code
 * langcode: en
 * status: true
 * id: my_process_pipeline
 * label: 'My process pipeline'
 * description: 'Process pipeline for processing fulltext fields'
 * process:
 *   -
 *     plugin: dom
 *     method: import
 *   -
 *     plugin: dom_str_replace
 *     mode: attribute
 *     xpath: '//a'
 *     attribute_options:
 *       name: href
 *     search: 'foo'
 *     replace: 'bar'
 *   -
 *     plugin: dom_str_replace
 *     mode: attribute
 *     xpath: '//a'
 *     attribute_options:
 *       name: href
 *     regex: true
 *     search: '/foo/'
 *     replace: TOKEN1
 *   -
 *     plugin: dom
 *     method: export
 * @endcode
 * @codingStandardsIgnoreEnd
 *
 * @MigrateProcessPlugin(
 *   id = "oe_migration_pipeline",
 *   handle_multiples = TRUE
 * )
 */
class Pipeline extends ProcessPluginBase {

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration += [
      'placeholders' => [],
    ];
    $this->validateConfiguration();

    $this->pipeline = MigrationProcessPipeline::load($this->configuration['id']);
    if (empty($this->pipeline)) {
      throw new MigrateException(sprintf('The pipeline plugin could not load the given process pipeline "%s".', $this->configuration['id']));
    }
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
