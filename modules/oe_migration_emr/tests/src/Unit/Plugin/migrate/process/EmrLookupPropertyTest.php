<?php

namespace Drupal\Tests\oe_migration_emr\Unit\Plugin\migrate\process;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\oe_migration_emr\Plugin\migrate\process\EmrLookupProperty;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;

/**
 * @coversDefaultClass \Drupal\oe_migration_emr\Plugin\migrate\process\EmrLookupProperty;
 *
 * @group oe_migration
 */
class EmrLookupPropertyTest extends MigrateProcessTestCase {

  protected $validConfiguration = [
    'entity_host_type' => 'node',
    'entity_meta_bundle' => 'valid_meta_bundle',
    'field' => 'nid'
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
   * @param $configuration
   *   The configuration array.
   * @param string $config_option
   *   The config_option that should throw the exception.
   *
   * @dataProvider providerTestConfig
   */
  public function testConfig($configuration, $config_option) {
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

    //      throw new MigrateSkipRowException(sprintf('The node %s does not exist in the destination.', $value));
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
   * Data provider for the testConfig method.
   *
   * @return array[]
   */
  public function providerTestConfig() {
    return [
      // Without config.
      [[], 'entity_host_type'],

      // Without entity_meta_bundle config option.
      [['entity_host_type' => 'node'], 'entity_meta_bundle'],

      // Without entity_host_type config option.
      [['entity_meta_bundle' => 'news'], 'entity_host_type'],

      // Without field config option.
      [[
        'entity_meta_bundle' => 'news',
        'entity_host_type' => 'node',
      ], 'field'],

      // Without entity_meta_bundle config option.
      [[
        'entity_host_type' => 'news',
        'field' => 'nid',
      ], 'entity_meta_bundle'],

      // Checking with a NULL value.
      [['entity_host_type' => NULL], 'entity_host_type'],

      // Checking with a no string value.
      [['entity_host_type' => 1], 'entity_host_type'],
    ];
  }
}
