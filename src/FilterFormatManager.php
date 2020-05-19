<?php

declare(strict_types = 1);

namespace Drupal\oe_migration;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\filter\FilterFormatInterface;
use Drupal\filter\FilterPluginCollection;

/**
 * Class FilterFormatManager.
 *
 * Provides filter format related methods.
 *
 * @package Drupal\oe_migration
 */
class FilterFormatManager implements FilterFormatManagerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The filter format storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $filterFormatStorage;

  /**
   * The FilterFormatManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->filterFormatStorage = $this->entityTypeManager->getStorage('filter_format');
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterFormat(string $id): ?FilterFormatInterface {
    return $this->filterFormatStorage ? $this->filterFormatStorage->load($id) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterIds(FilterFormatInterface $filter_format): array {
    $ids = $filter_format->filters()->getInstanceIds();
    return is_array($ids) && !empty($ids) ? array_keys($ids) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function isFilterIdValid(string $filter_id, FilterFormatInterface $filter_format): bool {
    return in_array($filter_id, $this->getFilterIds($filter_format));
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledFilters(FilterFormatInterface $filter_format): FilterPluginCollection {
    /** @var \Drupal\filter\FilterPluginCollection $filters */
    $filters = $filter_format->filters();
    if (($filters->count() > 0)) {
      foreach ($filters as $filter) {
        $configuration = $filter->getConfiguration();
        $status = is_array($configuration) && array_key_exists('status', $configuration) ? $configuration['status'] : FALSE;
        if ($status !== TRUE) {
          $filters->removeInstanceId($filter->getPluginId());
        }
      }
    }

    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedTags(FilterFormatInterface $filter_format): array {
    /** @var array $html_restrictions */
    $html_restrictions = $filter_format->getHtmlRestrictions();
    /** @var string[] $allowed_tags */
    $allowed_tags = is_array($html_restrictions) && array_key_exists('allowed', $html_restrictions) ? array_keys($html_restrictions['allowed']) : [];

    return $allowed_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function isTagAllowed(string $tag, FilterFormatInterface $filter_format): bool {
    /** @var array $allowed_tags */
    $allowed_tags = array_map('strtolower', $this->getAllowedTags($filter_format));
    return in_array(strtolower($tag), $allowed_tags);
  }

}
