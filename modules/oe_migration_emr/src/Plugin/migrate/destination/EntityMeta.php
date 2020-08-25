<?php

declare(strict_types = 1);

namespace Drupal\oe_migration_emr\Plugin\migrate\destination;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\oe_migration\ValidConfigurableMigrationPluginInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

/**
 * Provides entity meta destination plugin to update existing entities.
 *
 * Available configuration keys:
 * - destination_property: Name of the field that stores the host Entity ID to
 *     load.
 * - meta_bundle: Name of the Entity Meta bundle.
 *
 * Example:
 *  The destination is the Entity Meta with bundle ewcms_site_tree_item, the
 *  elements already exist in destination and the host entities are nodes (in
 *  the example). The ID of the host entity is saved in the field
 *  ewcms_sitetree_menu_item and the param overwrite_properties lists the
 *  properties than can be overridden.
 *
 * @code
 * destination:
 *   plugin: entity:entity_meta
 *   overwrite_properties:
 *   - ewcms_sitetree_pool
 *   - ewcms_sitetree_relative_position
 *   - ewcms_sitetree_relative_menuitem
 *   - ewcms_in_horizontal_menu
 *   destination_property: ewcms_sitetree_menu_item
 *   meta_bundle: ewcms_site_tree_item
 * @encode
 *
 * @MigrateDestination(
 *   id = "entity:entity_meta",
 * )
 */
class EntityMeta extends EntityContentBase implements ValidConfigurableMigrationPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, EntityStorageInterface $storage, array $bundles, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $storage, $bundles, $entity_field_manager, $field_type_manager);
    $this->validateConfigurationKeys(
      [
        'destination_property' => 'string',
        'meta_bundle' => 'string',
        'overwrite_properties' => 'array',
      ]
    );
  }

  /**
   * Loads the host entity based on the configured source property.
   *
   * @param \Drupal\migrate\Row $row
   *   The row object.
   * @param array $old_destination_id_values
   *   The old destination IDs.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity we are importing into.
   */
  protected function getEntity(Row $row, array $old_destination_id_values) {
    $entity = NULL;
    $destination_property_value = $row->getDestinationProperty($this->configuration['destination_property']);
    if (!empty($destination_property_value) > 0 && $entity = $this->storage->load($destination_property_value)) {
      $entity = $this->updateMetaEntity($entity, $row);
    }

    return $entity;
  }

  /**
   * Update the linked Entity Meta from the host Entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The host entity.
   * @param \Drupal\migrate\Row $row
   *   The current row.
   *
   * @return mixed
   *   The updated host entity.
   */
  protected function updateMetaEntity(EntityInterface $entity, Row $row) {
    /** @var \Drupal\emr\Field\EntityMetaItemListInterface $entity_meta_list */
    $entity_meta_list = $entity->get('emr_entity_metas');

    if (is_null($entity_meta_list)) {
      throw new MigrateSkipRowException(sprintf('The entity %s does not have any entity meta field.', $entity->id()));
    }

    /** @var \Drupal\emr\Entity\EntityMetaInterface $entity_meta */
    $entity_meta = $entity_meta_list->getEntityMeta($this->configuration['meta_bundle']);

    if (is_null($entity_meta)) {
      throw new MigrateSkipRowException(sprintf('The entity %s does not have any entity meta field with the name %s.', $entity->id(), $this->configuration['meta_bundle']));
    }

    foreach ($this->configuration['overwrite_properties'] as $property) {
      $entity_meta->set($property, $row->getDestinationProperty($property));
    }
    $entity_meta_list->attach($entity_meta);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(array $destination_identifier) {
    // The rollback can't be managed because the destination entities already
    // exist.
  }

  /**
   * Override to use the host management.
   *
   * The Entity Meta is managed directly with the host entity using the API, so
   * we need to manage the host entity.
   *
   * @todo Expands for all entity types (although maybe it isn't possible).
   */
  protected static function getEntityTypeId($plugin_id) {
    return 'node';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \InvalidArgumentException
   */
  public function validateConfigurationKeys(array $keys = NULL): void {
    foreach ($keys as $value => $type) {
      if (!isset($this->configuration[$value]) || empty($this->configuration[$value] || $type == gettype($this->configuration[$value]))) {
        throw new \InvalidArgumentException(sprintf('The configuration option "%s" is mandatory and has to be a %s.', $value, $type));
      }
    }
  }

}
