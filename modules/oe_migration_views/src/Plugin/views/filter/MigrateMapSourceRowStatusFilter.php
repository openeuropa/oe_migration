<?php

declare(strict_types = 1);

namespace Drupal\oe_migration_views\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Defines a filter handler for migrate map source row status.
 *
 * @ViewsFilter("migrate_map_source_row_status")
 */
class MigrateMapSourceRowStatusFilter extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = _oe_migration_views_migrate_map_source_row_status_options();
    }
    return $this->valueOptions;
  }

}
