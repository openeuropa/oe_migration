<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_migration\Kernel\Plugin\migrate\source;

use Drupal\Tests\migrate\Kernel\MigrateSqlSourceTestBase;

/**
 * @covers \Drupal\oe_migration\Plugin\migrate\source\MenuItemNodes
 *
 * @group dmt_migrate
 */
class MenuItemNodesTest extends MigrateSqlSourceTestBase {

  /**
   * An array of original values.
   *
   * @var array[]
   */
  protected $menuLinks = [
    1 => [
      'mlid' => 1,
      'plid' => 0,
      'link_path' => 'node/1',
      'weight' => 1,
      'depth' => 1,
      'menu_name' => 'main-menu',
    ],
    2 => [
      'mlid' => 2,
      'plid' => 1,
      'link_path' => 'node/2',
      'weight' => 1,
      'depth' => 2,
      'menu_name' => 'main-menu',
    ],
    3 => [
      'mlid' => 3,
      'plid' => 0,
      'link_path' => 'node/3',
      'weight' => 1,
      'depth' => 1,
      'menu_name' => 'main-menu',
    ],
    4 => [
      'mlid' => 4,
      'plid' => 1,
      'link_path' => 'no-node-path/4',
      'weight' => 1,
      'depth' => 2,
      'menu_name' => 'main-menu',
    ],
    5 => [
      'mlid' => 5,
      'plid' => 3,
      'link_path' => 'node/5',
      'weight' => 1,
      'depth' => 2,
      'menu_name' => 'main-menu',
    ],
    6 => [
      'mlid' => 6,
      'plid' => 3,
      'link_path' => 'node/6',
      'weight' => 2,
      'depth' => 2,
      'menu_name' => 'main-menu',
    ],
    7 => [
      'mlid' => 7,
      'plid' => 1,
      'link_path' => 'node/7',
      'weight' => 2,
      'depth' => 2,
      'menu_name' => 'another-menu',
    ],
  ];

  /**
   * An array of transformed values.
   *
   * @var array[]
   */
  protected $expectedData = [
    1 => [
      'mlid' => 1,
      'plid' => 0,
      'link_path' => 'node/1',
      'p_link_path' => NULL,
      'weight' => 1,
      'depth' => 1,
      'nid' => 1,
      'parent_nid' => "",
    ],
    2 => [
      'mlid' => 2,
      'plid' => 1,
      'link_path' => 'node/2',
      'weight' => 1,
      'depth' => 2,
      'menu_name' => 'main-menu',
      'p_link_path' => 'node/1',
      'nid' => 2,
      'parent_nid' => 1,
    ],
    3 => [
      'mlid' => 3,
      'plid' => 0,
      'link_path' => 'node/3',
      'weight' => 1,
      'depth' => 1,
      'menu_name' => 'main-menu',
      'p_link_path' => NULL,
      'nid' => 3,
      'parent_nid' => "",

    ],
    5 => [
      'mlid' => 5,
      'plid' => 3,
      'link_path' => 'node/5',
      'weight' => 1,
      'depth' => 2,
      'menu_name' => 'main-menu',
      'p_link_path' => 'node/3',
      'nid' => 5,
      'parent_nid' => 3,
    ],
    6 => [
      'mlid' => 6,
      'plid' => 3,
      'link_path' => 'node/6',
      'weight' => 2,
      'depth' => 2,
      'menu_name' => 'main-menu',
      'p_link_path' => 'node/3',
      'nid' => 6,
      'parent_nid' => 3,
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'path',
    'oe_migration',
    'migrate',
    'migrate_drupal',
  ];

  /**
   * {@inheritDoc}
   */
  public function providerSource() {
    $tests = [];
    // Case with no valid menu items in the database.
    $tests[0]['source_data']['menu_links'] = [
      $this->menuLinks[4],
    ];
    $tests[0]['expected_data'] = [];

    // Case with only a valid menu item in the database.
    $tests[1]['source_data']['menu_links'] = [
      $this->menuLinks[1],
    ];
    $tests[1]['expected_data'] = [
      $this->expectedData[1],
    ];

    // Test the menu-name filter.
    $tests[2]['source_data']['menu_links'] = [
      $this->menuLinks[1],
      $this->menuLinks[7],
    ];
    $tests[2]['expected_data'] = [
      $this->expectedData[1],
    ];

    // Test the order.
    $tests[3]['source_data']['menu_links'] = [
      $this->menuLinks[1],
      $this->menuLinks[2],
      $this->menuLinks[3],
      $this->menuLinks[5],
      $this->menuLinks[6],
    ];
    $tests[3]['expected_data'] = [
      $this->expectedData[1],
      $this->expectedData[3],
      $this->expectedData[2],
      $this->expectedData[5],
      $this->expectedData[6],
    ];

    return $tests;
  }

}
