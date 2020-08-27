<?php

namespace Drupal\Tests\oe_migration_emr\Unit\Plugin\migrate\destination;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

/**
 * @coversDefaultClass \Drupal\oe_migration_emr\Plugin\migrate\destination\EntityMeta
 *
 * @group oe_migration
 */
class EntityMetaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'migrate',
    'entity_reference_revisions',
    'emr',
    'emr_node',
    'entity_meta_example',
    'entity_meta_audio',
    'system',
    'oe_migration',
    'oe_migration_emr',
    'node',
    'user',
    'menu_ui',
    'options',
    'entity_reference_revisions',
    'field',
    'system',
  ];

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * A migration executable mock.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migrationExecutable;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('entity_meta');
    $this->installEntitySchema('entity_meta_relation');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(
      [
        'emr',
        'emr_node',
        'entity_meta_example',
        'entity_meta_audio',
      ]
    );

    $emr_installer = \Drupal::service('emr.installer');
    $emr_installer->installEntityMetaTypeOnContentEntityType('audio', 'node');

    $this->nodeStorage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->migrationExecutable = $this->createMock(MigrationInterface::class);
  }

  /**
   * Test when the field exists and is created properly.
   */
  public function testGetEntity() {
    $old_destination_values = [];
    $config = [
      'destination_property' => 'nid',
      'meta_bundle' => 'audio',
      'overwrite_properties' => ['field_volume'],
    ];

    $node = $this->nodeStorage->create([
      'type' => 'entity_meta_example_ct',
      'title' => 'First node',
    ]);
    $node->save();

    $row = new Row();
    $row->setDestinationProperty('nid', $node->id());
    $row->setDestinationProperty('field_volume', 'low');

    /** @var \Drupal\migrate\Plugin\MigrateDestinationInterface $destination_manager */
    $plugin = \Drupal::service('plugin.manager.migrate.destination')
      ->createInstance('entity:entity_meta', $config, $this->migrationExecutable);

    $entity = $plugin->getEntity($row, $old_destination_values);
    $emr_list = $entity->get('emr_entity_metas');
    $emr = $emr_list->getEntityMeta('audio');

    // Test with only 1 field overridden.
    $this->assertEqual('low', $emr->get('field_volume')->getValue()[0]['value']);
  }

}
