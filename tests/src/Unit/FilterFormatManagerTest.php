<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_migration\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\filter\FilterFormatInterface;
use Drupal\oe_migration\FilterFormatManager;
use Drupal\filter\FilterPluginCollection;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\oe_migration\FilterFormatManager
 *
 * @group oe_migration
 */
class FilterFormatManagerTest extends UnitTestCase {

  /**
   * The class that will be tested.
   *
   * @var \Drupal\oe_migration\FilterFormatManager
   */
  protected $filterFormatManager;

  /**
   * The FilterFormatStorage mock object.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $filterFormatStorage;

  /**
   * The FilterFormat mock object.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $filterFormat;

  /**
   * The FilterPluginCollection mock object.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $filterPluginCollection;

  /**
   * A list of Filters.
   *
   * @var array
   */
  protected $filters;

  /**
   * A valid list of allowed tags with the presence of the 'allowed' key.
   *
   * @var array[]
   */
  protected $allowedTags = [
    'allowed' => [
      'allowed_tag_1' => [
        'attribute_1' => TRUE,
        'attribute_2' => TRUE,
        'attribute_3' => TRUE,
      ],
      'allowed_tag_2' => FALSE,
      'allowed_tag_3' => FALSE,
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Mock three Filters.
    $filter_1 = $this->createMock(FilterInterface::class);
    $filter_2 = $this->createMock(FilterInterface::class);
    $filter_3 = $this->createMock(FilterInterface::class);
    $this->filters = [
      'filter_1' => $filter_1,
      'filter_2' => $filter_2,
      'filter_3' => $filter_3,
    ];

    // Mock a FilterFormat.
    $this->filterFormat = $this->createMock(FilterFormatInterface::class);

    // Mock a FilterFormatStorage.
    $this->filterFormatStorage = $this->createMock(EntityStorageInterface::class);

    // Mock a FilterPluginCollection.
    $this->filterPluginCollection = $this->createMock(FilterPluginCollection::class);

    // Mock an EntityTypeManager.
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    // Mock the FilterFormatStorage load() method.
    $this->filterFormatStorage->expects($this->any())
      ->method('load')
      ->willReturnMap([
        ['valid_filter_id', $this->filterFormat],
      ]);

    // Mock the EntityTypeManager getStorage() method.
    $entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('filter_format')
      ->willReturn($this->filterFormatStorage);

    // Mock the inherited getPluginId() method for each filter.
    foreach ($this->filters as $key => $value) {
      $value->expects($this->any())
        ->method('getPluginId')
        ->willReturn($key);
    }

    // Mock the FilterPluginCollection getIterator() method.
    $this->filterPluginCollection->expects($this->any())
      ->method('getIterator')
      ->withConsecutive()
      ->willReturn(new \ArrayObject([
        $this->filters['filter_1'],
        $this->filters['filter_2'],
        $this->filters['filter_3'],
      ]));

    // Mock the FilterFormat filters() method.
    $this->filterFormat->expects($this->any())
      ->method('filters')
      ->willReturn($this->filterPluginCollection);

    // Create an instance of the FilterFormatManager class.
    $this->filterFormatManager = new FilterFormatManager($entityTypeManager);
  }

  /**
   * Test the getFilterFormat method.
   *
   * Create a valid and a non valid filter format id and verify that:
   * - getting a valid filter format works.
   * - getting NULL when trying to get a non valid filter format works.
   */
  public function testGetFilterFormat() {
    // Trying to get a valid filter format. The expected return value is a
    // FilterFormat object.
    $this->assertEquals($this->filterFormat, $this->filterFormatManager->getFilterFormat('valid_filter_id'));

    // Trying to get a non valid filter format. The expected return value is
    // NULL.
    $this->assertNull($this->filterFormatManager->getFilterFormat('invalid_filter_id'));
  }

  /**
   * Test the getFilterIds method.
   *
   * Create three filters and verify that:
   * - getting a list of valid filter ids from a valid FilterPluginCollection
   *   works.
   * - getting an empty array from an empty FilterPluginCollection works.
   * - getting an empty array if the FilterPluginCollection is NULL works.
   */
  public function testGetFilterIds() {
    // Mock the FilterPluginCollection getInstanceIds() method with three
    // consecutive return values, (1) a valid list of filters and (2) an empty
    // list of filters and (3) NULL.
    $this->filterPluginCollection->expects($this->any())
      ->method('getInstanceIds')
      ->will($this->onConsecutiveCalls($this->filters, [], NULL));

    // Try to get a list of valid filter format ids from a valid
    // FilterPluginCollection. The expected return value is a list of three
    // strings, 'filter_1', 'filter_2' and 'filter_3'.
    $this->assertEquals(['filter_1', 'filter_2', 'filter_3'], $this->filterFormatManager->getFilterIds($this->filterFormat));

    // Try to get a list of valid filter format ids from an empty
    // FilterPluginCollection. The expected return value is an empty array.
    $this->assertEmpty($this->filterFormatManager->getFilterIds($this->filterFormat));

    // Try to get a list of valid filter format ids from a non existing
    // FilterPluginCollection. The expected return value is an empty array.
    $this->assertEmpty($this->filterFormatManager->getFilterIds($this->filterFormat));
  }

  /**
   * Test the isFilterIdValid method.
   *
   * Create three filters and verify that:
   * - the validation of a valid filter id works.
   * - the non validation of a non valid filter id works.
   */
  public function testIsFilterIdValid() {
    // Mock the FilterPluginCollection getInstanceIds().
    $this->filterPluginCollection->expects($this->any())
      ->method('getInstanceIds')
      ->willReturn($this->filters);

    // Test the validation of a filter id. The expected return value is TRUE.
    $this->assertTrue($this->filterFormatManager->isFilterIdValid('filter_1', $this->filterFormat));

    // Test the non validation of a non existing filter id. The expected return
    // value is FALSE.
    $this->assertFalse($this->filterFormatManager->isFilterIdValid('filter_1000', $this->filterFormat));
  }

  /**
   * Test the getEnabledFilters method with FilterPluginCollection alterations.
   *
   * Create a FilterPluginCollection that contains three Filters, set the status
   * of all filters to TRUE and verify that:
   * - getting 3 enabled filters (status is set to TRUE for all of them) of a
   *   FilterPluginCollection that contains 3 elements (count = 3) works.
   * - getting NULL when trying to get the enabled filters of a
   *   FilterPluginCollection that contains 0 elements (count = 0) works.
   * - getting NULL when trying to get the enabled filters of a variable that is
   *   not an instance of FilterPluginCollection works.
   */
  public function testGetEnabledFiltersWithFilterPluginCollectionAlterations() {
    // Mock the inherited getConfiguration() method for each filter.
    foreach ($this->filters as $value) {
      $value->expects($this->any())
        ->method('getConfiguration')
        ->willReturn(['status' => TRUE]);
    }

    // Mock the FilterPluginCollection getInstanceIds() method.
    $this->filterPluginCollection->expects($this->any())
      ->method('getInstanceIds')
      ->willReturn($this->filters);

    // Mock the FilterPluginCollection count() method.
    $this->filterPluginCollection->expects($this->any())
      ->method('count')
      ->willReturnOnConsecutiveCalls(3, 0);

    // Set the FilterPluginCollection counter to 3 and test that all enabled
    // filters (x3) will be returned.
    $this->assertEquals($this->filterPluginCollection, $this->filterFormatManager->getEnabledFilters($this->filterFormat));

    // Set the FilterPluginCollection counter to 0 and verify that no filters
    // will be returned. The expected return value is NULL.
    $this->assertNull($this->filterFormatManager->getEnabledFilters($this->filterFormat));

    // Mock a new FilterFormat.
    $filter_format = $this->createMock(FilterFormatInterface::class);

    // Mock the FilterFormat filters() method so that it returns an array, not
    // an instance of FilterPluginCollection.
    $filter_format->expects($this->any())
      ->method('filters')
      ->willReturn($this->filters);

    // The returned value of the FilterFormat filters() method is not an
    // instance of FilterPluginCollection, so the expected value is NULL.
    $this->assertNull($this->filterFormatManager->getEnabledFilters($filter_format));
  }

  /**
   * Test the getEnabledFilters method with enabled filters.
   *
   * Create a FilterPluginCollection that contains three Filters, set the status
   * of all filters to TRUE and verify that:
   * - the removeInstanceId() is never called by enabled filters.
   */
  public function testGetEnabledFiltersWithEnabledFilters() {
    // Mock the FilterPluginCollection count() method.
    $this->filterPluginCollection->expects($this->any())
      ->method('count')
      ->willReturn(3);

    // Mock the inherited getConfiguration() method for each filter.
    foreach ($this->filters as $value) {
      $value->expects($this->any())
        ->method('getConfiguration')
        ->willReturn(['status' => TRUE]);
    }

    // Mock the FilterPluginCollection removeInstanceId() and make sure that it
    // is never invoked.
    $this->filterPluginCollection->expects($this->never())
      ->method('removeInstanceId');

    // Set the FilterPluginCollection counter to 3 and test that all filters
    // will be returned.
    $this->assertEquals($this->filterPluginCollection, $this->filterFormatManager->getEnabledFilters($this->filterFormat));
  }

  /**
   * Test the getEnabledFilters method with enabled and disabled filters.
   *
   * Create a FilterPluginCollection that contains three Filters, set the status
   * of two filters to TRUE, the status of one filter to FALSE and verify that:
   * - the removeInstanceId() is called only once by the filter whose status is
   *   set to FALSE.
   */
  public function testGetEnabledFiltersWithEnabledAndDisabledFilters() {
    // Mock the getConfiguration() method for the first filter in order to
    // return status TRUE.
    $this->filters['filter_1']->expects($this->any())
      ->method('getConfiguration')
      ->willReturn(['status' => TRUE]);

    // Mock the getConfiguration() method for the second filter in order to
    // return status FALSE.
    $this->filters['filter_2']->expects($this->any())
      ->method('getConfiguration')
      ->willReturn(['status' => FALSE]);

    // Mock the getConfiguration() method for the third filter in order to
    // return status TRUE.
    $this->filters['filter_3']->expects($this->any())
      ->method('getConfiguration')
      ->willReturn(['status' => TRUE]);

    // Mock the FilterPluginCollection count() method.
    $this->filterPluginCollection->expects($this->any())
      ->method('count')
      ->willReturn(3);

    // Mock the FilterPluginCollection removeInstanceId() method and make sure
    // that it's invoked only once, by the second filter whose status is set to
    // FALSE.
    $this->filterPluginCollection->expects($this->once())
      ->method('removeInstanceId')
      ->with('filter_2');

    $this->filterFormatManager->getEnabledFilters($this->filterFormat);
  }

  /**
   * Test the getAllowedTags method.
   *
   * Create a valid allowed tags list and a non valid allowed tags list and
   * verify that:
   * - getting the allowed tags from a valid list works.
   * - getting an empty array from a non valid list works.
   * - getting an empty array from an empty list works.
   * - getting an empty array from NULL works.
   */
  public function testGetAllowedTags() {
    // An array where the "allowed" key doesn't exist.
    $no_allowed_key = [
      'allowed_tag_1' => [
        'attribute_1' => TRUE,
        'attribute_2' => TRUE,
        'attribute_3' => TRUE,
      ],
      'allowed_tag_2' => FALSE,
      'allowed_tag_3' => FALSE,
    ];

    // On four consecutive calls, return (1) a valid array where the "allowed"
    // key exists, (2) an array where the "allowed" key doesn't exist, (3) an
    // empty array and (4) NULL.
    $this->filterFormat->expects($this->any())
      ->method('getHtmlRestrictions')
      ->will($this->onConsecutiveCalls($this->allowedTags, $no_allowed_key, [], NULL));

    // $html_restrictions is an array and the "allowed" key exists. The method
    // is expected to return an array containing the three keys.
    $this->assertEquals(['allowed_tag_1', 'allowed_tag_2', 'allowed_tag_3'], $this->filterFormatManager->getAllowedTags($this->filterFormat));

    // $html_restrictions is an array and the "allowed" key doesn't exist. The
    // method is expected to return an empty array.
    $this->assertEmpty($this->filterFormatManager->getAllowedTags($this->filterFormat));

    // $html_restrictions is an empty array. The method is expected to return an
    // empty array.
    $this->assertEmpty($this->filterFormatManager->getAllowedTags($this->filterFormat));

    // $html_restrictions is NULL. The method is expected to return an empty
    // array.
    $this->assertEmpty($this->filterFormatManager->getAllowedTags($this->filterFormat));
  }

  /**
   * Test the isTagAllowed method.
   *
   * Create a valid allowed tags list and verify that:
   * - validating an allowed tag works.
   * - validating an allowed tag in a case-insensitive way works (x2).
   * - not validating a not allowed tag works.
   * - not validating an empty string works.
   */
  public function testIsTagAllowed() {
    // A valid array where the "allowed" key exists. In any other case the list
    // of allowed tags is an empty array and the build-in in_array() function
    // returns FALSE. Please, refer to the previous test, testGetAllowedTags().
    $this->filterFormat->expects($this->any())
      ->method('getHtmlRestrictions')
      ->willReturn($this->allowedTags);

    // The tested tag is an allowed tag. The expected return value is TRUE.
    $this->assertTrue($this->filterFormatManager->isTagAllowed('allowed_tag_1', $this->filterFormat));

    // The tested tag is an allowed tag (case-insensitive match). The expected
    // return value is TRUE.
    $this->assertTrue($this->filterFormatManager->isTagAllowed('ALLOWED_tag_2', $this->filterFormat));

    // The tested tag is an allowed tag (case-insensitive match). The expected
    // return value is TRUE.
    $this->assertTrue($this->filterFormatManager->isTagAllowed('allowed_tag_3', $this->filterFormat));

    // The tested tag is a not allowed tag. The expected return value is FALSE.
    $this->assertFalse($this->filterFormatManager->isTagAllowed('not_allowed_tag', $this->filterFormat));

    // The tested tag is a not allowed tag (empty string). The expected return
    // value is FALSE.
    $this->assertFalse($this->filterFormatManager->isTagAllowed('', $this->filterFormat));
  }

}
