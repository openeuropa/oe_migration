<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_migration\Unit\Plugin\migrate\process;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\filter\FilterFormatInterface;
use Drupal\filter\FilterPluginCollection;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Row;
use Drupal\oe_migration\FilterFormatManagerInterface;
use Drupal\oe_migration\Plugin\migrate\process\ApplyFilters;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;

/**
 * @coversDefaultClass \Drupal\oe_migration\Plugin\migrate\process\ApplyFilters
 *
 * @group oe_migration
 */
class ApplyFiltersTest extends MigrateProcessTestCase {

  /**
   * The ID of the plugin under test.
   *
   * @var string
   */
  protected string $pluginId = 'oe_migration_apply_filters';

  /**
   * The instance of the plugin under test.
   *
   * @var \Drupal\migrate\ProcessPluginBase
   */
  protected $plugin;

  /**
   * Destination property, only to show errors.
   *
   * @var string
   */
  protected string $destinationProperty = 'destination_property';

  /**
   * Default values for the filter map.
   *
   * @var array
   */
  protected array $sourcePluginIds = [
    'name' => [
      'type' => 'string',
      'max_length' => 128,
      'is_ascii' => TRUE,
      'alias' => 'wpt',
    ],
  ];

  /**
   * The row from the source to process.
   *
   * @var \Drupal\migrate\Row
   */
  protected $row;

  /**
   * The LanguageManager mock object.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The FilterFormatManager service mock object.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $filterFormatManager;

  /**
   * The FilterFormat mock object.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $filterFormat;

  /**
   * The Filter mock object.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $filter;

  /**
   * The FilterPluginCollection mock object.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $filterPluginCollection;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $valid_filter = new Row(['name' => 'valid_filter_id'], $this->sourcePluginIds, TRUE);
    $this->row = $valid_filter;

    // Mock a Language object.
    $languageMock = $this->createMock(LanguageInterface::class);

    // Mock the Language getId() method.
    $languageMock->expects($this->any())
      ->method('getId')
      ->willReturn('en');

    // Mock a LanguageManager object.
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);

    // Mock the LanguageManager getDefaultLanguage() method.
    $this->languageManager->expects($this->any())
      ->method('getDefaultLanguage')
      ->willReturn($languageMock);

    // Mock a FilterProcessResult object.
    $filterProcessResult = $this->createMock(FilterProcessResult::class);

    // Mock the FilterProcessResult getProcessedText() method.
    $filterProcessResult->expects($this->any())
      ->method('getProcessedText')
      ->willReturn('processed text');

    // Mock a Filter object.
    $this->filter = $this->createMock(FilterInterface::class);

    // Mock the FilterInterface process() method.
    $this->filter->expects($this->any())
      ->method('process')
      ->with('unprocessed text', 'en')
      ->willReturn($filterProcessResult);

    // Mock the Filter inherited getPluginId() method.
    $this->filter->expects($this->any())
      ->method('getPluginId')
      ->willReturn('valid_filter_id');

    // Mock a FilterPluginCollection object.
    $this->filterPluginCollection = $this->createMock(FilterPluginCollection::class);
    $this->filterPluginCollection->expects($this->any())
      ->method('count')
      ->willReturn(1);

    // Mock the FilterPluginCollection inherited getIterator() method.
    $this->filterPluginCollection->expects($this->any())
      ->method('getIterator')
      ->willReturn(new \ArrayObject([$this->filter]));

    // Mock a FilterFormat object.
    $this->filterFormat = $this->getMockBuilder(FilterFormatInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    // Mock the FilterFormat inherited id() method.
    $this->filterFormat->expects($this->any())
      ->method('id')
      ->willReturn('filter_format_id');

    // Mock the FilterFormat filters() method.
    $this->filterFormat->expects($this->any())
      ->method('filters')
      ->willReturn($this->filterPluginCollection);

    // Mock a FilterFormatManager service.
    $this->filterFormatManager = $this->createMock(FilterFormatManagerInterface::class);

    // Mock the FilterFormatManager isFilterIdValid() method.
    $this->filterFormatManager->expects($this->any())
      ->method('isFilterIdValid')
      ->with('valid_filter_id', $this->filterFormat)
      ->willReturn(TRUE);

    // Mock the FilterFormatManager getFilterFormat() method.
    $this->filterFormatManager->expects($this->any())
      ->method('getFilterFormat')
      ->with('filter_format_id')
      ->willReturn($this->filterFormat);

    // Mock the FilterFormatManager getEnabledFilters() method.
    $this->filterFormatManager->expects($this->any())
      ->method('getEnabledFilters')
      ->with($this->filterFormat)
      ->willReturn($this->filterPluginCollection);
  }

  /**
   * Test the base case without any configuration.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function testBaseCase() {
    $configuration = [];
    $configuration['filter_format'] = 'filter_format_id';

    $this->initializePlugin($configuration);

    $filtered = $this->plugin->transform('unprocessed text', $this->migrateExecutable, $this->row, $this->destinationProperty);
    $this->assertEquals('processed text', $filtered);
  }

  /**
   * Test that the "filters_to_apply" configuration key works as expected.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function testFiltersToApply() {
    $configuration = [];
    $configuration['filter_format'] = 'filter_format_id';
    $configuration['filters_to_apply'] = ['valid_filter_id'];

    $this->initializePlugin($configuration);

    $filtered = $this->plugin->transform('unprocessed text', $this->migrateExecutable, $this->row, '');
    $this->assertEquals('processed text', $filtered);
  }

  /**
   * Test that the "filters_to_skip" configuration key works as expected.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function testFiltersToSkip() {
    $configuration = [];
    $configuration['filter_format'] = 'filter_format_id';
    $configuration['filters_to_skip'] = ['valid_filter_id'];
    $this->initializePlugin($configuration);
    // Ensure that the function removeInstanceId is called with the
    // valid_filter_id parameter. The 'remove' action cannot be testing without
    // implement login in the mock filterPluginCollection mock.
    $this->filterPluginCollection
      ->expects($this->once())
      ->method('removeInstanceId')
      ->with('valid_filter_id');

    $this->plugin->transform('unprocessed text', $this->migrateExecutable, $this->row, '');
  }

  /**
   * Test filters_to_skip and filters_to_apply configuration keys.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function testFiltersToApplyAndFiltersToSkipTogether() {
    $configuration = [];
    $configuration['filter_format'] = 'filter_format_id';
    $configuration['filters_to_skip'] = ['valid_filter_id'];
    $configuration['filters_to_apply'] = ['valid_filter_id'];

    $this->initializePlugin($configuration);

    // Ensure that the removeInstanceId() method is never invoked. This means
    // that the configuration key "filters_to_skip" is ignored as expected.
    $this->filterPluginCollection
      ->expects($this->any())
      ->method('removeInstanceId');

    $filtered = $this->plugin->transform('unprocessed text', $this->migrateExecutable, $this->row, '');
    $this->assertEquals('processed text', $filtered);
  }

  /**
   * Test that invalid input throws an exception.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function testInvalidInput() {
    $configuration = [];
    $configuration['filter_format'] = 'filter_format_id';

    $this->initializePlugin($configuration);

    try {
      $this->plugin->transform([], $this->migrateExecutable, $this->row, '');
      $this->fail('Expected exception 1162011 not thrown');
    }
    catch (MigrateException $e) {
      $this->assertStringContainsString('is not a string.', $e->getMessage());
    }
  }

  /**
   * Initialize the ApplyFilters class with the $configuration.
   *
   * @param array $configuration
   *   A configuration array.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  protected function initializePlugin(array $configuration): void {
    $this->plugin = new ApplyFilters(
      $configuration,
      $this->pluginId,
      [],
      $this->languageManager,
      $this->filterFormatManager
    );
  }

}
