<?php

declare(strict_types = 1);

namespace Drupal\oe_migration_emr\Plugin\migrate\source;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\oe_migration\ValidConfigurableMigrationPluginInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Plugin to select menu items related to D7 nodes.
 *
 * Available configuration keys:
 * - menu_name: (optional) The menu name to select the terms from.
 *     The default value is 'main-menu'.
 *
 * Returned values:
 *  mlid. Menu item ID.
 *  plid. Parent menu item ID if exists.
 *  link_path. Menu path.
 *  parent_link_path. Parent path.
 *  weight. Menu item weight.
 *  depth. Menu item depth.
 *  nid. Node ID related to the menu item.
 *  parent_nid. Parent related node ID if exists.
 *
 * Example:
 * Select all menu items pointing to nodes in the main-menu menu.
 *
 * @code
 * source:
 *   plugin: oe_migration_emr_entity_meta_relation_menu_item_nodes
 *   menu_name: 'main-menu'
 *   track_changes: true
 * @endcode
 *
 * @MigrateSource(
 *   id = "oe_migration_emr_menu_item_nodes",
 *   source_module = "system"
 * )
 */
class MenuItemNodes extends DrupalSqlBase implements ValidConfigurableMigrationPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state, $entity_type_manager);

    $this->validateConfigurationKeys(['menu_name']);

    if (!isset($this->configuration['menu_name']) || empty($this->configuration['menu_name'])) {
      $this->configuration['menu_name'] = 'main-menu';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    /*
     * The order is explicitly declared of this way to maintain a correct
     * hierarchy:
     *  -> Ordered by levels (highest  items first)
     *  -> Ordered by parent (to list all children of the same parent)
     *  -> Ordered by the weigh to maintain the order in the level.
     */
    $result = $this->select('menu_links', 'i');
    $result->leftJoin('menu_links', 'p', 'i.plid=p.mlid');
    $result->fields('i', ['mlid', 'plid', 'link_path', 'weight', 'depth']);
    $result->fields('p', ['parent_link_path' => 'link_path']);
    $result->condition('i.menu_name', $this->configuration['menu_name'])
      ->condition('i.link_path', 'node/%', 'LIKE')
      ->orderBy('i.depth', 'ASC')
      ->orderBy('i.plid', 'ASC')
      ->orderBy('i.weight', 'ASC');

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'mlid' => 'Menu item ID',
      'plid' => 'Parent menu item ID',
      'link_path' => 'Path',
      'parent_link_path' => 'Parent path',
      'weight' => 'Weight',
      'depth' => 'Depth',
      'nid' => 'Related node ID',
      'parent_nid' => 'Parent related node ID',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['mlid']['type'] = 'string';
    return $ids;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function prepareRow(Row $row) {
    $related_nid = str_replace('node/', '', $row->getSourceProperty('link_path'));
    $row->setSourceProperty('nid', $related_nid);

    $parent_nid = str_replace('node/', '', $row->getSourceProperty('p_link_path'));
    $row->setSourceProperty('parent_nid', $parent_nid);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \invalidArgumentException
   */
  public function validateConfigurationKeys(array $keys = NULL): void {
    foreach ($keys as $value) {
      if (isset($this->configuration[$value]) && !is_string($this->configuration[$value])) {
        throw new \InvalidArgumentException(sprintf('The configuration option "%s" has to be a string', $value));
      }
    }
  }

}
