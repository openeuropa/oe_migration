<?php

declare(strict_types = 1);

namespace Drupal\oe_migration_views\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Defines a field handler to display a migrate map source row status.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("migrate_map_source_row_status")
 */
class MigrateMapSourceRowStatusField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    $options = _oe_migration_views_migrate_map_source_row_status_options();
    return $options[$value] ?: $this->t('Unknown');
  }

}
