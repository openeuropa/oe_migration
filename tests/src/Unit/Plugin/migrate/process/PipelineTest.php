<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_migration\Unit\Plugin\migrate\process;

use Drupal\oe_migration\Entity\MigrationProcessPipelineInterface;
use Drupal\migrate\MigrateException;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;
use Drupal\oe_migration\Plugin\migrate\process\Pipeline;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * @coversDefaultClass \Drupal\oe_migration\Plugin\migrate\process\Pipeline
 *
 * @group oe_migration
 */
class PipelineTest extends MigrateProcessTestCase {

  /**
   * Name of the plugin tested.
   *
   * @var string
   */
  protected $pluginId = 'oe_migration_pipeline';

  /**
   * A MigrationProcessPipelineInterface mock.
   *
   * @var \Drupal\oe_migration\Entity\MigrationProcessPipelineInterface
   */
  protected $migrationProcessPipeline;

  /**
   * A valid process plugin id.
   *
   * @var string
   */
  protected $validId;

  /**
   * An invalid process plugin id.
   *
   * @var string
   */
  protected $invalidId;

  /**
   * Destination property, only to show errors.
   *
   * @var string
   */
  protected $destinationProperty = 'destination_property';

  /**
   * A entity Manager Mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A entity storage interfaz mock.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->migrationProcessPipeline = $this->createMock(MigrationProcessPipelineInterface::class);
    $this->validId = $this->randomMachineName();
    $this->invalidId = $this->randomMachineName();

    $this->entityStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('oe_migration_process_pipeline')
      ->willReturn($this->entityStorage);
  }

  /**
   * Test with a plugin id configured.
   */
  public function testInvalidConfiguration() {
    $configuration = [];
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The pipeline plugin requires a pipeline ID, none found.');
    new Pipeline($configuration, $this->pluginId, [], $this->entityTypeManager);
  }

  /**
   * Test with an invalid pipeline.
   */
  public function testInvalidPipeline() {
    $configuration = ['id' => $this->invalidId];

    $this->entityStorage->expects($this->once())
      ->method('load')
      ->with($configuration['id'])
      ->willReturn(NULL);

    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage(sprintf('The pipeline plugin could not load the given process pipeline "%s".', $this->invalidId));
    new Pipeline($configuration, $this->pluginId, [], $this->entityTypeManager);
  }

  /**
   * Test the transform method vor a valid use case.
   */
  public function testTransform() {
    $configuration = ['id' => $this->validId];
    $this->entityStorage->expects($this->once())
      ->method('load')
      ->with($configuration['id'])
      ->willReturn($this->migrationProcessPipeline);

    $pipeline = new Pipeline($configuration, $this->pluginId, [], $this->entityTypeManager);
    $value = [];
    $transformed_value = $this->randomGenerator->string();

    // Added a necessary mock only used when the plugin exits and is valid.
    $definitions = [
      'process1' => $this->randomMachineName(),
      'process2' => $this->randomMachineName(),
    ];
    $this->migrationProcessPipeline->expects($this->once())
      ->method('getDefinitions')
      ->willReturn($definitions);

    // Ensure that the processRow is called. That means that the process
    // works as expected.
    $this->migrateExecutable->expects($this->once())
      ->method('processRow')
      ->with($this->row, [$this->destinationProperty => $definitions], $value)
      ->willReturn($transformed_value);
    $pipeline->transform($value, $this->migrateExecutable, $this->row, $this->destinationProperty);
  }

}
