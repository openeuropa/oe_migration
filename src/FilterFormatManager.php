<?php

declare(strict_types = 1);

namespace Drupal\oe_migration;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\filter\FilterPluginCollection;

/**
 * Class FilterFormatManager.
 *
 * Provides filter format related methods.
 *
 * @package Drupal\oe_migration
 */
class FilterFormatManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The filter format storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage
   */
  protected $filterFormatStorage;

  /**
   * The FilterFormatManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->setFilterFormatStorage();
  }

  /**
   * The filterFormatStorage setter.
   */
  protected function setFilterFormatStorage(): void {
    try {
      $this->filterFormatStorage = $this->entityTypeManager->getStorage('filter_format');
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      $this->filterFormatStorage = NULL;
    }
  }

  /**
   * Returns a filter format by its id.
   *
   * @param string $id
   *   The filter format id.
   *
   * @return \Drupal\filter\Entity\FilterFormat|null
   *   The filter format object or null.
   */
  public function getFilterFormat(string $id): ?FilterFormat {
    return $this->filterFormatStorage ? $this->filterFormatStorage->load($id) : NULL;
  }

  /**
   * Returns a list of filter id's for a given filter format.
   *
   * @param \Drupal\filter\Entity\FilterFormat $filter_format
   *   The filter format object.
   *
   * @return array
   *   The list of filter id's for a given filter format or an empty list.
   */
  public function getFilterIds(FilterFormat $filter_format): array {
    return is_array($filter_format->filters()->getInstanceIds()) ? array_keys($filter_format->filters()->getInstanceIds()) : [];
  }

  /**
   * Verifies whether a filter id is valid for a given filter format or not.
   *
   * @param string $filter_id
   *   The filter id.
   * @param \Drupal\filter\Entity\FilterFormat $filter_format
   *   The filter format.
   *
   * @return bool
   *   The answer to whether a filter id is valid for the given filter format or
   *   not.
   */
  public function isValidFilterId(string $filter_id, FilterFormat $filter_format): bool {
    return in_array($filter_id, $this->getFilterIds($filter_format));
  }

  /**
   * Returns a list of enabled filters for a given filter format.
   *
   * @param \Drupal\filter\Entity\FilterFormat $filter_format
   *   The filter format object.
   *
   * @return \Drupal\filter\FilterPluginCollection|null
   *   The list of enabled filters for the given filter format.
   */
  public function getEnabledFilters(FilterFormat $filter_format): ?FilterPluginCollection {
    /** @var \Drupal\filter\FilterPluginCollection|null $filters */
    $filters = $filter_format->filters();
    if (!($filters instanceof FilterPluginCollection) || !($filters->count() > 0)) {
      return NULL;
    }
    foreach ($filters as $filter) {
      if ($filter->status !== TRUE) {
        $filters->removeInstanceId($filter->getPluginId());
      }
    }

    return $filters;
  }

  /**
   * Returns a list of allowed HTML tags for a given filter format.
   *
   * @param \Drupal\filter\Entity\FilterFormat $filter_format
   *   The filter format object.
   *
   * @return array
   *   The list of allowed HTML tags for the given filter format if any, else an
   *   empty array.
   */
  public function getAllowedTags(FilterFormat $filter_format): array {
    /** @var array $html_restrictions */
    $html_restrictions = $filter_format->getHtmlRestrictions();
    /** @var string[] $allowed_tags */
    $allowed_tags = is_array($html_restrictions) && !empty($html_restrictions) ? array_keys($html_restrictions['allowed']) : [];
    asort($allowed_tags);

    return $allowed_tags;
  }

  /**
   * Verifies if a tag is among the allowed HTML tags for a given filter format.
   *
   * @param string $tag
   *   The tag to verify.
   * @param \Drupal\filter\Entity\FilterFormat $filter_format
   *   The filter format object.
   *
   * @return bool
   *   The result of the verification operation.
   */
  public function isAllowedTag(string $tag, FilterFormat $filter_format): bool {
    return in_array(strtolower($tag), $this->getAllowedTags($filter_format));
  }

}
