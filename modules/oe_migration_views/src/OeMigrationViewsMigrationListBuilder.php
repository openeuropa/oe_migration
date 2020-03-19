<?php

declare(strict_types = 1);

namespace Drupal\oe_migration_views;

use Drupal\migrate_tools\Controller\MigrationListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Class OeMigrationViewsMigrationListBuilder.
 *
 * @package Drupal\oe_migration_views
 */
class OeMigrationViewsMigrationListBuilder extends MigrationListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = parent::buildRow($entity);

    // Override migrate_tools migration list to include "View details" links.
    if (is_array($row['operations'])) {
      $row['operations']['data'] = $this->buildOperations($entity);
    }

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    // View details.
    // The view ID must be the same as the migration ID, and the display ID must
    // be "page_1".
    // @todo Improve this relationship.
    $route = Url::fromRoute("view.{$entity->id()}.page_1");
    if ($route->access()) {
      $operations['view-details'] = [
        'title' => $this->t('View details'),
        'url' => $route,
      ];
    }

    // Execute.
    $route = Url::fromRoute('migrate_tools.execute', [
      'migration_group' => $entity->get('migration_group'),
      'migration' => $entity->id(),
    ]);

    if ($route->access()) {
      $operations['execute'] = [
        'title' => $this->t('Execute'),
        'url' => $route,
      ];
    }

    return $operations;
  }

}
