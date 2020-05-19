<?php

declare(strict_types = 1);

namespace Drupal\oe_migration;

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
   * Returns a filter format by its id.
   *
   * @param string $id
   *   The filter format id.
   *
   * @return \Drupal\filter\FilterFormatInterface|null
   *   The filter format object. NULL if no matching filter format is found.
   */
  public function getFilterFormat(string $id): ?FilterFormatInterface {
    return $this->filterFormatStorage ? $this->filterFormatStorage->load($id) : NULL;
  }

  /**
   * Returns a list of filter IDs for a given filter format.
   *
   * @param \Drupal\filter\FilterFormatInterface $filter_format
   *   The filter format object.
   *
   * @return array
   *   The list of filter IDs for a given filter format or an empty list.
   */
  public function getFilterIds(FilterFormatInterface $filter_format): array {
    $ids = $filter_format->filters()->getInstanceIds();
    return is_array($ids) && !empty($ids) ? array_keys($ids) : [];
  }

  /**
   * Verifies whether a filter id is valid for a given filter format or not.
   *
   * @param string $filter_id
   *   The filter id.
   * @param \Drupal\filter\FilterFormatInterface $filter_format
   *   The filter format.
   *
   * @return bool
   *   The answer to whether a filter id is valid for the given filter format or
   *   not.
   */
  public function isFilterIdValid(string $filter_id, FilterFormatInterface $filter_format): bool {
    return in_array($filter_id, $this->getFilterIds($filter_format));
  }

  /**
   * Returns a list of enabled filters for a given filter format.
   *
   * @param \Drupal\filter\FilterFormatInterface $filter_format
   *   The filter format object.
   *
   * @return \Drupal\filter\FilterPluginCollection|null
   *   The list of enabled filters for the given filter format.
   */
  public function getEnabledFilters(FilterFormatInterface $filter_format): ?FilterPluginCollection {
    /** @var \Drupal\filter\FilterPluginCollection|null $filters */
    $filters = $filter_format->filters();
    if (!($filters instanceof FilterPluginCollection) || !($filters->count() > 0)) {
      return NULL;
    }
    foreach ($filters as $filter) {
      $configuration = $filter->getConfiguration();
      $status = is_array($configuration) && array_key_exists('status', $configuration) ? $configuration['status'] : FALSE;
      if ($status !== TRUE) {
        $filters->removeInstanceId($filter->getPluginId());
      }
    }

    return $filters;
  }

  /**
   * Returns a list of allowed HTML tags for a given filter format.
   *
   * @param \Drupal\filter\FilterFormatInterface $filter_format
   *   The filter format object.
   *
   * @return array
   *   The list of allowed HTML tags for the given filter format if any, else an
   *   empty array.
   */
  public function getAllowedTags(FilterFormatInterface $filter_format): array {
    /** @var array $html_restrictions */
    $html_restrictions = $filter_format->getHtmlRestrictions();
    /** @var string[] $allowed_tags */
    $allowed_tags = is_array($html_restrictions) && array_key_exists('allowed', $html_restrictions) ? array_keys($html_restrictions['allowed']) : [];

    return $allowed_tags;
  }

  /**
   * Verifies if a tag is among the allowed HTML tags for a given filter format.
   *
   * The verification is case-insensitive.
   *
   * @param string $tag
   *   The tag to verify.
   * @param \Drupal\filter\FilterFormatInterface $filter_format
   *   The filter format object.
   *
   * @return bool
   *   The result of the verification operation.
   */
  public function isAllowedTag(string $tag, FilterFormatInterface $filter_format): bool {
    /** @var array $allowed_tags */
    $allowed_tags = array_map('strtolower', $this->getAllowedTags($filter_format));
    return in_array(strtolower($tag), $allowed_tags);
  }

}
