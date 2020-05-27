<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_migration\Unit\Plugin\migrate\process;

use Drupal\oe_migration\Entity\MigrationProcessPipelineInterface;
use Drupal\migrate\MigrateException;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;
use Drupal\oe_migration\Plugin\migrate\process\Pipeline;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeRepository;
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
   * {@inheritDoc}
   */
  public function setUp() {
    parent::setUp();

    $this->migrationProcessPipeline = $this->createMock(MigrationProcessPipelineInterface::class);
    $this->validId = $this->randomMachineName();
    $this->invalidId = $this->randomMachineName();
  }

  /**
   * Test with a plugin id configured.
   */
  public function testInvalidConfiguration() {
    $configuration = [];

    try {
      new Pipeline($configuration, $this->pluginId, []);
    }
    catch (\InvalidArgumentException | MigrateException $e) {
      $this->assertEquals('The pipeline plugin requires a pipeline ID, none found.', $e->getMessage());
    }
  }

  /**
   * Test with an invalid pipeline.
   */
  public function testInvalidPipeline() {
    $this->initializeContainer();

    $configuration = ['id' => $this->invalidId];

    try {
      new Pipeline($configuration, $this->pluginId, []);
    }
    catch (MigrateException $e) {
      $this->assertEquals(sprintf('The pipeline plugin could not load the given process pipeline "%s".', $this->invalidId), $e->getMessage());
    }
  }

  /**
   * Test the transform method vor a valid use case.
   */
  public function testTransform() {

    $this->initializeContainer();

    $configuration = ['id' => $this->validId];
    $pipeline = new Pipeline($configuration, $this->pluginId, []);
    $value = [];
    $transformed_value = $this->randomGenerator->string();

    // Added a necessary mock only used when the plugin exits and is valid.
    $process = [
      'process1' => $this->randomMachineName(),
      'process2' => $this->randomMachineName(),
    ];
    $this->migrationProcessPipeline->expects($this->once())
      ->method('getProcess')
      ->willReturn($process);

    // Ensure that the processRow is called. That means that the process
    // works as expected.
    $this->migrateExecutable->expects($this->once())
      ->method('processRow')
      ->with($this->row, [$this->destinationProperty => $process], $value)
      ->willReturn($transformed_value);
    $pipeline->transform($value, $this->migrateExecutable, $this->row, $this->destinationProperty);
  }

  /**
   * Initialize the Drupal container with the necessary mocks.
   */
  protected function initializeContainer() {
    $entity_class = $this->randomMachineName();

    // Mock the entity repository for the used methods.
    $entity_repository = $this->createMock(EntityTypeRepository::class);
    $entity_repository->expects($this->any())
      ->method('getEntityTypeFromClass')
      ->willReturn($entity_class);

    // The storage mock is necessary to be returned later.
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->any())
      ->method('load')
      ->willReturnMap([
        [$this->validId, $this->migrationProcessPipeline],
        [$this->invalidId, NULL],
      ]);

    // Finally, the entity type manager is called to load the config entity
    // MigrationProcessPipeline.
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->any())
      ->method('getStorage')
      ->with($entity_class)
      ->willReturn($storage);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('entity_type.repository', $entity_repository);
    \Drupal::setContainer($container);
  }

}
