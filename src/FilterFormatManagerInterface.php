<?php

declare(strict_types = 1);

namespace Drupal\oe_migration;

use Drupal\filter\FilterFormatInterface;
use Drupal\filter\FilterPluginCollection;

/**
 * Interface FilterFormatManagerInterface.
 *
 * @package Drupal\oe_migration
 */
interface FilterFormatManagerInterface {

  /**
   * Returns a filter format by its ID.
   *
   * @param string $id
   *   The filter format ID.
   *
   * @return \Drupal\filter\FilterFormatInterface|null
   *   The filter format object. NULL if no matching filter format is found.
   */
  public function getFilterFormat(string $id): ?FilterFormatInterface;

  /**
   * Returns a list of filter IDs for a given filter format.
   *
   * @param \Drupal\filter\FilterFormatInterface $filter_format
   *   The filter format object.
   *
   * @return array
   *   The list of filter IDs for a given filter format or an empty list.
   */
  public function getFilterIds(FilterFormatInterface $filter_format): array;

  /**
   * Verifies whether a filter ID is valid for a given filter format or not.
   *
   * @param string $filter_id
   *   The filter ID.
   * @param \Drupal\filter\FilterFormatInterface $filter_format
   *   The filter format.
   *
   * @return bool
   *   The answer to whether a filter ID is valid for the given filter format or
   *   not.
   */
  public function isFilterIdValid(string $filter_id, FilterFormatInterface $filter_format): bool;

  /**
   * Returns a list of enabled filters for a given filter format.
   *
   * @param \Drupal\filter\FilterFormatInterface $filter_format
   *   The filter format object.
   *
   * @return \Drupal\filter\FilterPluginCollection
   *   The list of enabled filters for the given filter format.
   */
  public function getEnabledFilters(FilterFormatInterface $filter_format): FilterPluginCollection;

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
  public function getAllowedTags(FilterFormatInterface $filter_format): array;

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
  public function isTagAllowed(string $tag, FilterFormatInterface $filter_format): bool;

}
