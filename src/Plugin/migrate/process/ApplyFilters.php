<?php

declare(strict_types = 1);

namespace Drupal\oe_migration\Plugin\migrate\process;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\oe_migration\ValidConfigurableMigrationPluginInterface;
use Drupal\oe_migration\FilterFormatManager;
use Drupal\filter\Entity\FilterFormat;
use Drupal\filter\FilterPluginCollection;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Applies filters of the given filter format to a string.
 *
 * Available configuration keys:
 *   - filter_format (mandatory): The filter format id of the destination field,
 *     eg. full_html. If the destination filter format id is declared after the
 *     field value or if a YAML variable is used to store the field's value, the
 *     dynamic detection of the destination field's filter format id won't be
 *     possible.
 *   - filters_to_apply (optional): A list of filter IDs to apply, eg.
 *     filter_autop, filter_html, etc.
 *     If this list is empty, all enabled filters for the given filter format
 *     will be applied.
 *     If this list is not empty, the filters that are in the list of available
 *     filters for the given filter format will be applied instead of those
 *     defined by the web site's configuration, no matter if they are enabled or
 *     not.
 *   - filters_to_skip (optional): A list of filter IDs to skip, eg.
 *     htmlcorrector.
 *     If the "filters_to_apply" key is not declared, all enabled filters for
 *     the given filter format will be applied except of those declared in the
 *     list "filters_to_skip".
 *     If the "filters_to_apply" list is declared, the "filters_to_skip" list
 *     will be ignored.
 *
 * Example with minimum configuration:
 * @code
 * process:
 *   body/value:
 *     # All enabled filters of the "full_html" filter format will be applied.
 *     - plugin: oe_migration_apply_filters
 *       source: body/0/value
 *       filter_format: full_html
 * @endcode
 *
 * Example with filters to apply:
 * @code
 * process:
 *   body/value:
 *     # All of the listed filters will be applied if they are valid "full_html"
 *     # filter format filters.
 *     - plugin: oe_migration_apply_filters
 *       source: body/0/value
 *       filter_format: full_html
 *       filters_to_apply:
 *         # Limit allowed HTML tags.
 *         - filter_html
 *         # Correct faulty and chopped off HTML.
 *         - filter_htmlcorrector
 *         # Convert line breaks to HTML.
 *         - filter_autop
 * @endcode
 *
 * Example with filters to skip:
 * @code
 * process:
 *   body/value:
 *     # All enabled filters of the "full_html" filter format will be applied,
 *     # except of those listed in "filters_to_skip".
 *     - plugin: oe_migration_apply_filters
 *       source: body/0/value
 *       filter_format: full_html
 *       filters_to_skip:
 *         # Do not correct faulty and chopped off HTML.
 *         - filter_htmlcorrector
 * @endcode
 *
 * Example with filters to apply and filters to skip:
 * @code
 * process:
 *   body/value:
 *     # All of the listed filters will be applied if they are valid "full_html"
 *     # filter format filters. The "filters_to_skip" list will be ignored.
 *     - plugin: oe_migration_apply_filters
 *       source: body/0/value
 *       filter_format: full_html
 *       filters_to_apply:
 *         # Limit allowed HTML tags.
 *         - filter_html
 *         # Correct faulty and chopped off HTML.
 *         - filter_htmlcorrector
 *         # Convert line breaks to HTML.
 *         - filter_autop
 *       filters_to_skip:
 *         # Do not correct faulty and chopped off HTML.
 *         - filter_htmlcorrector
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "oe_migration_apply_filters"
 * )
 */
class ApplyFilters extends ProcessPluginBase implements ContainerFactoryPluginInterface, ValidConfigurableMigrationPluginInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The FilterFormatManager service.
   *
   * @var \Drupal\oe_migration\FilterFormatManager
   */
  protected $filterFormatManager;

  /**
   * The row from the source to process.
   *
   * @var \Drupal\migrate\Row
   */
  protected $row;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   *   An exception will be thrown if at least one of the given configuration
   *   keys are not valid.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LanguageManagerInterface $language_manager,
    FilterFormatManager $filter_format_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->languageManager = $language_manager;
    $this->filterFormatManager = $filter_format_manager;
    $this->validateConfigurationKeys();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('oe_migration.filter_format_manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   *   An exception will be thrown if the input value is not a string.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value)) {
      throw new MigrateException(sprintf('%s is not a string.', var_export($value, TRUE)));
    }
    $this->row = $row;

    return $this->doFilter($value);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationKeys(array $keys = NULL): void {
    $this->validateConfigurationKeyFilterFormat('filter_format');
    $this->validateConfigurationKeyFiltersAndFiltersToSkip(['filters_to_apply', 'filters_to_skip']);
  }

  /**
   * Validates the "filter_format" configuration key.
   *
   * @param string $key
   *   The configuration key to validate.
   */
  protected function validateConfigurationKeyFilterFormat(string $key): void {
    if (!array_key_exists($key, $this->configuration) || !is_string($this->configuration[$key])) {
      throw new \InvalidArgumentException(sprintf('The configuration key "%s" is required and must be a string.', $key));
    }
    if (is_null($this->getConfigurationFilterFormat())) {
      throw new \InvalidArgumentException(sprintf('The configuration key "%s" (%s) is not a valid filter format.', $key, $this->configuration[$key]));
    }
  }

  /**
   * Validates the "filters_to_apply" and "filters_to_skip" configuration keys.
   *
   * @param string[] $keys
   *   The configuration keys to validate.
   */
  protected function validateConfigurationKeyFiltersAndFiltersToSkip(array $keys): void {
    $filter_format = $this->getConfigurationFilterFormat();
    foreach ($keys as $key) {
      if (array_key_exists($key, $this->configuration)) {
        if (!is_array($this->configuration[$key]) || empty($this->configuration[$key])) {
          throw new \InvalidArgumentException(sprintf('The configuration option "%s" must be a non-empty array.', $key));
        }
        else {
          foreach ($this->configuration[$key] as $filter_id) {
            if (!is_string($filter_id)) {
              throw new \InvalidArgumentException(sprintf('The configuration option "%s" must contain strings. "%s" is not a string.', $key, var_export($filter_id, TRUE)));
            }
            if ($this->filterFormatManager->isValidFilterId($filter_id, $filter_format) !== TRUE) {
              throw new \InvalidArgumentException(sprintf('"%s" is not a valid filter for the "%s" filter format.', $filter_id, $filter_format->id()));
            }
          }
        }
      }
    }
  }

  /**
   * Returns the current filter format.
   *
   * @return \Drupal\filter\Entity\FilterFormat|null
   *   The current filter format.
   */
  protected function getConfigurationFilterFormat(): ?FilterFormat {
    return is_string($this->configuration['filter_format']) ? $this->filterFormatManager->getFilterFormat($this->configuration['filter_format']) : NULL;
  }

  /**
   * Returns the destination language id.
   *
   * @return string
   *   The destination language id or the site's default language id.
   */
  protected function getLangcode(): string {
    return $this->row->getDestinationProperty('langcode') ?? $this->languageManager->getDefaultLanguage()->getId();
  }

  /**
   * Applies a list of filters to a string.
   *
   * @param string $value
   *   The string to filter.
   *
   * @return string
   *   The filtered string.
   */
  protected function doFilter(string $value): string {
    /** @var \Drupal\filter\FilterPluginCollection|null $filters */
    $filters = $this->getFiltersToApply();
    /** @var string $langcode */
    $langcode = $this->getLangcode();
    if ($filters && $filters->count() > 0) {
      foreach ($filters as $filter) {
        /** @var \Drupal\filter\FilterProcessResult $result */
        $result = $filter->process($value, $langcode);
        $value = $result->getProcessedText();
      }
    }

    return $value;
  }

  /**
   * Returns a list of filters to apply.
   *
   * @return \Drupal\filter\FilterPluginCollection|null
   *   The list of filters to apply or null.
   */
  protected function getFiltersToApply(): ?FilterPluginCollection {
    /** @var \Drupal\filter\Entity\FilterFormat $filter_format */
    $filter_format = $this->getConfigurationFilterFormat();

    // If the list of filters to apply is set, remove the unwanted filters.
    if (isset($this->configuration['filters_to_apply']) && is_array($this->configuration['filters_to_apply'])) {
      // Get the filters for the given filter format.
      /** @var \Drupal\filter\FilterPluginCollection $filters_to_apply */
      $filters_to_apply = $filter_format->filters();
      foreach ($filters_to_apply as $filter) {
        if (!in_array($filter->getPluginId(), $this->configuration['filters_to_apply'])) {
          $filters_to_apply->removeInstanceId($filter->getPluginId());
        }
      }
    }
    else {
      // Get the enabled filters for the given filter format.
      /** @var \Drupal\filter\FilterPluginCollection $filters_to_apply */
      $filters_to_apply = $this->filterFormatManager->getEnabledFilters($filter_format);
      // If the list of filters to skip is set, remove them from the list.
      if (isset($this->configuration['filters_to_skip']) && is_array($this->configuration['filters_to_skip'])) {
        foreach ($filters_to_apply as $filter) {
          if (in_array($filter->getPluginId(), $this->configuration['filters_to_skip'])) {
            $filters_to_apply->removeInstanceId($filter->getPluginId());
          }
        }
      }
    }

    return $filters_to_apply;
  }

}
