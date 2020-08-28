<?php

namespace Drupal\Tests\oe_migration_emr\Unit\Plugin\migrate\process;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\emr\Entity\EntityMeta;
use Drupal\emr\Field\EntityMetaItemListInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\oe_migration_emr\Plugin\migrate\process\EmrLookupProperty;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;

/**
 * @coversDefaultClass \Drupal\oe_migration_emr\Plugin\migrate\process\EmrLookupProperty;
 *
 * @group oe_migration
 */
class EmrLookupPropertyTest extends MigrateProcessTestCase {

  /**
   * A valid configuration array.
   *
   * @var string[]
   */
  protected $validConfiguration = [
    'entity_host_type' => 'node',
    'entity_meta_bundle' => 'valid_meta_bundle',
    'field' => 'nid',
  ];

  /**
   * A entity storage mock.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * The entityTypeManager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The plugin id.
   *
   * @var string
   */
  protected $pluginId = 'oe_migration_emr_lookup_property';

  /**
   * {@inheritdoc}
   */
  public function __construct($name = NULL, array $data = [], $dataName = '') {
    parent::__construct($name, $data, $dataName);

    $this->entityStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->willReturn($this->entityStorage);
  }

  /**
   * Test some wrong configuration.
   *
   * @param array $configuration
   *   The configuration array.
   * @param string $config_option
   *   The config_option that should throw the exception.
   *
   * @dataProvider providerTestConfig
   */
  public function testConfig(array $configuration, $config_option) {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage(sprintf('The configuration option "%s" is mandatory and has to be a string.', $config_option));
    $this->plugin = new EmrLookupProperty($configuration, $this->pluginId, [], $this->entityTypeManager);
  }

  /**
   * Test when the value can't be loaded.
   */
  public function testTransformInvalidEntityId() {
    $invalid_entity_id = $this->getRandomGenerator()->string();
    $this->entityStorage->expects($this->once())
      ->method('load')
      ->with($invalid_entity_id)
      ->willReturn(NULL);
    $this->plugin = new EmrLookupProperty($this->validConfiguration, $this->pluginId, [], $this->entityTypeManager);

    $this->expectException(MigrateSkipRowException::class);
    $this->expectExceptionMessage("The node $invalid_entity_id does not exist in the destination.");
    $this->plugin->transform($invalid_entity_id, $this->migrateExecutable, $this->row, 'r');
  }

  /**
   * Test when the entity doesn't have entity_meta fields.
   */
  public function testTransformInvalidField() {
    $host_entity = $this->createMock(ContentEntityBase::class);
    $host_entity->expects($this->once())
      ->method('hasField')
      ->with('emr_entity_metas')
      ->willReturn(NULL);
    $valid_id = $this->getRandomGenerator()->string();
    $this->entityStorage->expects($this->once())
      ->method('load')
      ->with($valid_id)
      ->willReturn($host_entity);
    $this->plugin = new EmrLookupProperty($this->validConfiguration, $this->pluginId, [], $this->entityTypeManager);

    $this->expectException(MigrateSkipRowException::class);
    $this->expectExceptionMessage("The node $valid_id does not have a emr_entity_metas field.");
    $this->plugin->transform($valid_id, $this->migrateExecutable, $this->row, 'r');
  }

  /**
   * Test when the configured field is not part of the EMR entity.
   */
  public function testTransformInvalidMetaField() {

    $emr_entity = $this->createMock(EntityMeta::class);
    $emr_entity->expects($this->once())
      ->method('hasField')
      ->with($this->validConfiguration['field'])
      ->willReturn(NULL);

    $field_list = $this->createMock(EntityMetaItemListInterface::class);
    $field_list->expects($this->once())
      ->method('getEntityMeta')
      ->with($this->validConfiguration['entity_meta_bundle'])
      ->willReturn($emr_entity);
    $host_entity = $this->createMock(ContentEntityBase::class);
    $host_entity->expects($this->once())
      ->method('hasField')
      ->with('emr_entity_metas')
      ->willReturn(TRUE);
    $host_entity->expects($this->once())
      ->method('get')
      ->with('emr_entity_metas')
      ->willReturn($field_list);

    $valid_id = $this->getRandomGenerator()->string();
    $this->entityStorage->expects($this->once())
      ->method('load')
      ->with($valid_id)
      ->willReturn($host_entity);

    $this->plugin = new EmrLookupProperty($this->validConfiguration, $this->pluginId, [], $this->entityTypeManager);

    $this->expectException(MigrateSkipRowException::class);
    $this->expectExceptionMessage(sprintf('The field %s does not exists', $this->validConfiguration['field']));
    $this->plugin->transform($valid_id, $this->migrateExecutable, $this->row, 'r');
  }

  /**
   * Test a valid use case.
   */
  public function testTransform() {
    $field = $this->createMock(FieldItemInterface::class);
    $field->expects($this->any())
      ->method('getValue')
      ->willReturn([['value' => 'valid_value']]);
    $emr_entity = $this->createMock(EntityMeta::class);
    $emr_entity->expects($this->once())
      ->method('get')
      ->with($this->validConfiguration['field'])
      ->willReturn($field);
    $emr_entity->expects($this->once())
      ->method('hasField')
      ->with($this->validConfiguration['field'])
      ->willReturn(TRUE);
    $field_list = $this->createMock(EntityMetaItemListInterface::class);
    $field_list->expects($this->once())
      ->method('getEntityMeta')
      ->with($this->validConfiguration['entity_meta_bundle'])
      ->willReturn($emr_entity);
    $host_entity = $this->createMock(ContentEntityBase::class);
    $host_entity->expects($this->once())
      ->method('hasField')
      ->with('emr_entity_metas')
      ->willReturn(TRUE);
    $host_entity->expects($this->once())
      ->method('get')
      ->with('emr_entity_metas')
      ->willReturn($field_list);

    $valid_id = $this->getRandomGenerator()->string();
    $this->entityStorage->expects($this->once())
      ->method('load')
      ->with($valid_id)
      ->willReturn($host_entity);

    $this->plugin = new EmrLookupProperty($this->validConfiguration, $this->pluginId, [], $this->entityTypeManager);
    $output = $this->plugin->transform($valid_id, $this->migrateExecutable, $this->row, 'r');

    // The result should be the 'valid_value' configured in the mock declared
    // above.
    $this->assertEquals('valid_value', $output);
  }

  /**
   * Data provider for the testConfig method.
   *
   * @return array[]
   *   The values to perform the different tests.
   */
  public function providerTestConfig() {
    return [
      // Without config any config.
      [[], 'entity_host_type'],

      // Without entity_meta_bundle config option.
      [['entity_host_type' => 'node'], 'entity_meta_bundle'],

      // Without entity_host_type config option.
      [['entity_meta_bundle' => 'news'], 'entity_host_type'],

      // Without field config option.
      [
        [
          'entity_meta_bundle' => 'news',
          'entity_host_type' => 'node',
        ],
        'field',
      ],

      // Without entity_meta_bundle config option (but with the others).
      [
        [
          'entity_host_type' => 'news',
          'field' => 'nid',
        ],
        'entity_meta_bundle',
      ],

      // Checking with a NULL value.
      [['entity_host_type' => NULL], 'entity_host_type'],

      // Checking with a no string value.
      [['entity_host_type' => 1], 'entity_host_type'],
    ];
  }

}
