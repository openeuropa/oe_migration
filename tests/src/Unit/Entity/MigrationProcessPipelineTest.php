<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_migration\Unit\Entity;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\oe_migration\Entity\MigrationProcessPipeline;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\oe_migration\Entity\MigrationProcessPipeline
 *
 * @group oe_migration
 */
class MigrationProcessPipelineTest extends UnitTestCase {

  /**
   * The entity type used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityType;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $provider = $this->randomMachineName();
    $this->entityType = $this->createMock(EntityTypeInterface::class);
    $this->entityType->expects($this->any())
      ->method('getProvider')
      ->willReturn($provider);
  }

  /**
   * Test the getProcess method without any replacement nor plugin configured.
   */
  public function testGetProcessWithoutPlugins() {
    $fixed_source = $this->randomMachineName();
    $values = ['definitions' => [$fixed_source]];

    // The default plugin should be 'get'.
    $expected = [
      [
        'plugin' => 'get',
        'source' => $fixed_source,
      ],
    ];
    $pipeline = new MigrationProcessPipeline($values, $this->entityType);
    $result = $pipeline->getDefinitions();
    $this->assertSame($expected, $result);
  }

  /**
   * Test the getDefinitions with a plugin configured.
   */
  public function testGetDefinitionsWithPlugins() {
    $values = [
      'definitions' => [
        [
          'plugin' => $this->randomMachineName(),
          'source' => $this->randomMachineName(),
          'value' => $this->randomMachineName(),
        ],
      ],
    ];
    $pipeline = new MigrationProcessPipeline($values, $this->entityType);
    $result = $pipeline->getDefinitions();
    $this->assertSame($values['definitions'], $result);
  }

  /**
   * Test the getDefitinions with a replacement configured.
   */
  public function testGetDefitinionsWithReplacements() {
    // A case with replacements.
    $token = $this->randomMachineName();
    $value = $this->randomMachineName();
    $tokens = [
      $token => $value,
    ];
    $values = [
      'definitions' => [
        [
          'plugin' => $this->randomMachineName(),
          'source' => $this->randomMachineName(),
          'value' => $token,
        ],
      ],
    ];
    $expected = $values['definitions'];
    $expected[0]['value'] = $value;

    $pipeline = new MigrationProcessPipeline($values, $this->entityType);
    $result = $pipeline->getDefinitions($tokens);
    $this->assertSame($expected, $result);
  }

  /**
   * Test the method calculateDependencies.
   */
  public function testCalculateDependencies() {

    // No new dependencies.
    $values = [];
    $pipeline = new MigrationProcessPipeline($values, $this->entityType);
    $this->assertEmpty($pipeline->calculateDependencies());

    // Test that new dependencies is added properly.
    $values = [
      'module' => $this->randomMachineName(),
    ];

    $definition = $this->createMock(EntityTypeInterface::class);
    $definition->expects($this->any())
      ->method('getProvider')
      ->willReturn($values['module']);
    $entity_type_manager = $this->createMock(EntityTypeManager::class);
    $entity_type_manager->expects($this->any())
      ->method('getDefinition')
      ->willReturn($definition);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    $pipeline = new MigrationProcessPipeline($values, $this->entityType);
    $this->assertCount(1, $pipeline->calculateDependencies());
  }

}
