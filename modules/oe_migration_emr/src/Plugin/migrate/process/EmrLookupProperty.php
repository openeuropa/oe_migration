<?php

declare(strict_types = 1);

namespace Drupal\dmt_migrate_emr\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dmt_migrate\ValidConfigurableMigrationPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Get a field from a related Meta Entity.
 *
 * Available configuration keys:
 *   - entity_host_type: The host entity type, f.e: node.
 *   - entity_meta_bundle: The entity meta name, f.e: ewcms_site_tree_item
 *   - field: The field name.
 *   - field_value: (optional) The name of the element gotten from the field
 *       name. Default value: 'value'.
 *
 * Example:
 *  In the example the 'nid' is used to find the host entity (a node in this
 *  case) and the 'ewcms_sitetree_menu_item' is the returned field.
 *  The field_value option is used to return properly the saved value because
 *  it is a key that might be different, depending on the field type.
 *
 * @code
 *  ewcms_sitetree_relative_menuitem:
 *  - plugin: migration_lookup
 *    source: nid
 *    migration:
 *     - food_migrate_node_food_basic_page
 *     - food_migrate_node_food_highlight
 *  - plugin: dmt_migrate_emr_lookup_property
 *    entity_host_type: node
 *    entity_meta_bundle: ewcms_site_tree_item
 *    field: ewcms_sitetree_menu_item
 *    field_value: target_id
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "dmt_migrate_emr_lookup_property"
 * )
 */
class EmrLookupProperty extends ProcessPluginBase implements ContainerFactoryPluginInterface, ValidConfigurableMigrationPluginInterface {

  /**
   * The storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\migrate\MigrateException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->validateConfigurationKeys(
      [
        'entity_host_type',
        'entity_meta_bundle',
        'field',
      ]
    );

    $this->configuration += [
      'field_value' => 'value',
    ];

    $this->storage = $entity_type_manager->getStorage($this->configuration['entity_host_type']);
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
   * @throws \Drupal\migrate\MigrateSkipRowException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Null values throw exception when you try to load.
    // We don't want to skip the process with an Exception and prefer return
    // an empty value to allow create better pipelines.
    if (is_null($value)) {
      return '';
    }

    /** @var \Drupal\Core\Entity\EntityInterface $host_entity */
    $host_entity = $this->storage->load($value);

    if (!$host_entity) {
      throw new MigrateSkipRowException(sprintf('The node %s does not exist in the destination.', $value));
    }

    // Get the property.
    if (!$host_entity->hasField('emr_entity_metas')) {
      throw new MigrateSkipRowException(sprintf('The node %s does not have a emr_entity_metas field.', $value));
    }

    /** @var \Drupal\emr\Field\EntityMetaItemListInterface $entity_meta_list */
    $entity_meta_list = $host_entity->get('emr_entity_metas');
    /** @var \Drupal\emr\Entity\EntityMetaInterface $entity_meta */
    $entity_meta = $entity_meta_list->getEntityMeta($this->configuration['entity_meta_bundle']);

    if (!$entity_meta->hasField($this->configuration['field'])) {
      throw new MigrateSkipRowException(sprintf('The field %s does not exists', $this->configuration['field']));
    }

    /** @var \Drupal\Core\Field\FieldItemList $link */
    $link = $entity_meta->get($this->configuration['field']);
    if (!empty($link->getValue())) {
      $array_value = $link->getValue();
      /** @var \Drupal\Core\Field\Plugin\DataType\FieldItem $link_value */
      $link_value = reset($array_value);

      return $link_value[$this->configuration['field_value']] ?? '';
    }

    return '';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \InvalidArgumentException
   */
  public function validateConfigurationKeys(array $keys = NULL): void {
    foreach ($keys as $value) {
      if (!isset($this->configuration[$value]) || empty($this->configuration[$value] || !is_string($this->configuration[$value]))) {
        throw new \InvalidArgumentException(sprintf('The configuration option "%s" is mandatory and has to be a string.', $value));
      }
    }
  }

}
