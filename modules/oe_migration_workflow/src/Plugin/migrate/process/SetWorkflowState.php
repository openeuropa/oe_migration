<?php

declare(strict_types = 1);

namespace Drupal\oe_migration_workflow\Plugin\migrate\process;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\oe_migration\ValidConfigurableMigrationPluginInterface;
use Drupal\workflows\WorkflowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets the workflow state of an entity.
 *
 * If the input value is a valid workflow state, it will be returned
 * untransformed. Else, if the workflow state is not set or is not a valid one,
 * it will be set according to the status property, if the status is set to "1",
 * then it will be set to the given "published_state", in any other case, it
 * will be set to the given "unpublished_state".
 *
 * Compared to a static mapping, it can also handle cases where the source
 * entity might not have a moderation state defined (NULL).
 *
 * Available configuration keys:
 *   - workflow_config_name (mandatory): The destination workflow's
 *     configuration name (string).
 *     Default value: "workflows.workflow.oe_corporate_workflow".
 *   - published_state (mandatory): The destination workflow's published state
 *     (string).
 *     Default value: "published".
 *   - unpublished_state (mandatory): The destination workflow's unpublished
 *     state (string).
 *     Default value: "draft".
 *
 * @codingStandardsIgnoreStart
 * Example with default configuration:
 * @code
 * process:
 *   moderation_state:
 *    - plugin: oe_migration_set_workflow_state
 *      source: moderation_state
 * @endcode
 *
 * Example with user defined configuration:
 * @code
 * process:
 *   moderation_state:
 *    - plugin: oe_migration_set_workflow_state
 *      source: moderation_state
 *      workflow_config_name: workflows.workflow.oe_corporate_workflow
 *      published_state: published
 *      unpublished_state: draft
 * @endcode
 * @codingStandardsIgnoreEnd
 *
 * @MigrateProcessPlugin(
 *   id = "oe_migration_set_workflow_state"
 * )
 */
class SetWorkflowState extends ProcessPluginBase implements ContainerFactoryPluginInterface, ValidConfigurableMigrationPluginInterface {

  /**
   * The configuration manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\migrate\MigrateException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigManagerInterface $config_manager,
    EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    // The default configuration.
    $this->configuration += [
      'workflow_config_name' => 'workflows.workflow.oe_corporate_workflow',
      'published_state' => 'published',
      'unpublished_state' => 'draft',
    ];
    $this->configManager = $config_manager;
    $this->entityTypeManager = $entity_type_manager;
    // Check if the configuration is set correctly.
    $this->validateConfigurationKeys();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\migrate\MigrateException
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\migrate\MigrateException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // The expected value has to be a string but the source entity might not
    // have a moderation state defined, in that case stringify the NULL value
    // and continue.
    $value = is_null($value) ? (string) $value : $value;
    // Check if the source value is of the expected type.
    if (!is_string($value)) {
      throw new MigrateException(sprintf('%s is not a string.', var_export($value, TRUE)));
    }
    // Check whether the current workflow state is a valid one or not. If it's
    // not a valid one, set it according to the entity "status". In this case,
    // if the status is set to "1", set it to the given "published_state", in
    // any other case, set it to the given "unpublished_state".
    $workflow = $this->getWorkflow($this->configuration['workflow_config_name']);
    if ($this->isValidWorkflowState($workflow, $value) === FALSE) {
      $value = (int) $row->getSourceProperty('status') === 1 ? $this->configuration['published_state'] : $this->configuration['unpublished_state'];
    }
    // Check whether the entity status and the workflow state status match. If
    // it's not the case, throw a migration exception, otherwise proceed with
    // the operation.
    if ((int) $row->getSourceProperty('status') !== (int) $workflow->get('type_settings')['states'][$value]['published']) {
      throw new MigrateException('The entity status and the workflow state status don\'t match.');
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function validateConfigurationKeys(array $keys = NULL): void {
    // Check if the configuration options are set and are strings.
    foreach (['workflow_config_name', 'published_state', 'unpublished_state'] as $option) {
      if (!array_key_exists($option, $this->configuration) || !is_string($this->configuration[$option])) {
        /** @var string $message */
        $message = sprintf('The "%s" option must be a string. The given value is of type "%s".', $option, gettype(($this->configuration[$option])));
        throw new \InvalidArgumentException($message);
      }
    }
    // The getWorkflow() method might throw an exception if the entity type
    // doesn't exist or if the storage handler couldn't be loaded.
    $workflow = $this->getWorkflow($this->configuration['workflow_config_name']);
    $workflow_config_name = explode('.', $this->configuration['workflow_config_name']);
    // Check if the given workflow is a valid one.
    if (is_null($workflow)) {
      $message = sprintf('"%s" is not a valid workflow.', end($workflow_config_name));
      throw new \InvalidArgumentException($message);
    }
    // Check if the published state is a valid state for the given workflow.
    elseif ($this->isValidWorkflowState($workflow, $this->configuration['published_state']) === FALSE) {
      $message = sprintf('"%s" is not a valid state of the "%s" workflow.', $this->configuration['published_state'], end($workflow_config_name));
      throw new \InvalidArgumentException($message);
    }
    // Check if the unpublished state is a valid state for the given workflow.
    elseif ($this->isValidWorkflowState($workflow, $this->configuration['unpublished_state']) === FALSE) {
      $message = sprintf('"%s" is not a valid state of the "%s" workflow.', $this->configuration['unpublished_state'], end($workflow_config_name));
      throw new \InvalidArgumentException($message);
    }
  }

  /**
   * Get the workflow entity object from the configuration name.
   *
   * @param string $config_name
   *   The configuration object name.
   *
   * @return \Drupal\workflows\WorkflowInterface|null
   *   A workflow entity object. NULL if no matching entity is found.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  protected function getWorkflow(string $config_name): ?WorkflowInterface {
    /** @var string $entity_type_id */
    $entity_type_id = $this->configManager->getEntityTypeIdByName($config_name);
    if ($entity_type_id !== 'workflow') {
      return NULL;
    }
    /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type */
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    /** @var string $entity_id */
    $entity_id = ConfigEntityStorage::getIDFromConfigName($config_name, $entity_type->getConfigPrefix());
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $entities */
    $entities = $this->entityTypeManager->getStorage($entity_type_id);
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $entities->load($entity_id);

    return $entity instanceof WorkflowInterface ? $entity : NULL;
  }

  /**
   * Checks if a state is valid for a given workflow.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The given workflow.
   * @param string $state
   *   The workflow state to verify its validity.
   *
   * @return bool
   *   The answer to whether the state is a valid one for the given workflow.
   */
  protected function isValidWorkflowState(WorkflowInterface $workflow, string $state): bool {
    return in_array($state, array_keys($this->getWorkflowStates($workflow)));
  }

  /**
   * Returns a list of states for a given workflow.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The given workflow.
   *
   * @return array
   *   The list of states for a given workflow if any, or an empty list.
   */
  protected function getWorkflowStates(WorkflowInterface $workflow): array {
    return is_array($workflow->get('type_settings')['states']) ? $workflow->get('type_settings')['states'] : [];
  }

}
