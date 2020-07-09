<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_migration_views\Functional;

use Drupal\Core\Site\Settings;
use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;

/**
 * Test Drush commands.
 *
 * @group oe_migration_views
 */
class DrushCommandsTest extends BrowserTestBase {

  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_migration_views_test',
    'oe_migration_views',
    'migrate_tools',
    'migrate_plus',
    'views',
    'taxonomy',
    'text',
    'system',
    'user',
  ];

  /**
   * The admin user that will be created.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create the admin user.
    $this->adminUser = $this->drupalCreateUser(['access administration pages', 'view migrate reports']);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * {@inheritdoc}
   *
   * Overrides \Drupal\Core\Test\FunctionalTestSetupTrait::prepareSettings
   * to copy settings.override.php.
   * Required for the "file_scan_ignore_directories" settings.
   */
  protected function prepareSettings() {
    parent::prepareSettings();

    $settings_override = DRUPAL_ROOT . '/' . $this->originalSite . '/settings.override.php';
    if (file_exists($settings_override)) {
      copy($settings_override, DRUPAL_ROOT . '/' . $this->siteDirectory . '/settings.override.php');
      Settings::initialize(DRUPAL_ROOT, $this->siteDirectory, $this->classLoader);
    }
  }

  /**
   * Tests the "generate" command.
   */
  public function testGenerateCommand() {
    // Test with non-existing/invalid migration.
    $this->drush('oe_migration_views:generate', ['non_existing']);
    $this->assertContains('[error]  No migration(s) found.', $this->getErrorOutput());

    // Generate fruit_terms.
    $this->drush('oe_migration_views:generate', ['fruit_terms']);
    $this->assertContains('[success] Created default_fruit_terms migrate report view.', $this->getErrorOutput());

    // Check that the view exists.
    $view_storage_controller = \Drupal::entityTypeManager()->getStorage('view');
    /** @var \Drupal\views\Entity\View $view */
    $view = $view_storage_controller->load('default_fruit_terms');
    $this->assertNotNull($view);

    // Check view permission.
    $display_options = $view->getDisplay('default')['display_options'];
    $this->assertEqual($display_options['access']['options']['perm'], 'view migrate reports');

    // Check view fields.
    $this->assertArraySubset([
      'source_row_status' => [
        'id' => 'source_row_status',
        'table' => 'migrate_map_fruit_terms',
        'field' => 'source_row_status',
        'plugin_id' => 'oe_migration_views_migrate_map_source_row_status',
      ],
      'sourceid1' => [
        'id' => 'sourceid1',
        'table' => 'migrate_map_fruit_terms',
        'field' => 'sourceid1',
        'label' => 'Source: name',
        'plugin_id' => 'standard',
      ],
      'destid1' => [
        'id' => 'destid1',
        'table' => 'migrate_map_fruit_terms',
        'field' => 'destid1',
        'label' => 'Destination: tid',
        'plugin_id' => 'standard',
      ],
      'migrate_messages' => [
        'id' => 'migrate_messages',
        'table' => 'migrate_map_fruit_terms',
        'field' => 'migrate_messages',
        'plugin_id' => 'oe_migration_views_migrate_messages',
      ],
    ], $display_options['fields']);

    // Check view relationships.
    $this->assertArraySubset([
      'migrate_map_taxonomy_term_field_data' => [
        'id' => 'migrate_map_taxonomy_term_field_data',
        'table' => 'migrate_map_fruit_terms',
        'field' => 'migrate_map_taxonomy_term_field_data',
        'plugin_id' => 'standard',
      ],
    ], $display_options['relationships']);

    // Check no result behavior.
    $this->assertArraySubset([
      'area_text_custom' => [
        'id' => 'area_text_custom',
        'table' => 'views',
        'field' => 'area_text_custom',
        'empty' => TRUE,
        'content' => '<h2>No data at the moment, come back later</h2>',
        'plugin_id' => 'text_custom',
      ],
    ], $display_options['empty']);

    // Check page display path.
    $page_1 = $view->getDisplay('page_1');
    $this->assertEqual($page_1['display_options']['path'], 'admin/structure/migrate/manage/default/migrations/fruit_terms/reports');

    // Check "View details" presence in migrations listing.
    $this->drupalGet('/admin/structure/migrate/manage/default/migrations');
    $this->assertSession()->responseContains('admin/structure/migrate/manage/default/migrations/fruit_terms/reports">View details</a>');

    // Check view page response (before import).
    $this->drupalGet('/admin/structure/migrate/manage/default/migrations/fruit_terms/reports');
    $this->assertSession()->responseContains('<h2>No data at the moment, come back later</h2>');

    // Check view page response (after import).
    $this->drush('migrate:import', ['fruit_terms']);
    $this->drupalGet('/admin/structure/migrate/manage/default/migrations/fruit_terms/reports');
    $result = $this->xpath('//tbody/tr/td[@class="views-field views-field-source-row-status" and contains(text(), "Imported")]');
    $this->assertEqual(count($result), 3);

    // Test generate when the view already exists.
    $this->drush('oe_migration_views:generate', ['fruit_terms']);
    $this->assertContains('[warning] Skipped default_fruit_terms view creation: The view already exists.', $this->getErrorOutput());
  }

  /**
   * Tests the "cleanup-map-tables" command.
   */
  public function testCleanupCommand() {
    // Test with no arguments.
    $this->drush('oe_migration_views:cleanup-map-tables');
    $this->assertContains('[error]  No table(s) found.', $this->getErrorOutput());

    // Test with non-existing table.
    $this->drush('oe_migration_views:cleanup-map-tables', [], ['tables' => 'migrate_map_fruit_terms']);
    $this->assertContains('[error]  No table(s) found.', $this->getErrorOutput());

    // Test fruit_terms table cleanup.
    $this->drush('oe_migration_views:generate', ['fruit_terms']);
    $this->drush('oe_migration_views:cleanup-map-tables', [], ['tables' => 'migrate_map_fruit_terms']);
    $this->assertContains('[success] Removed source_data column from migrate_map_fruit_terms.', $this->getErrorOutput());
    $this->assertContains('[success] Removed destination_data column from migrate_map_fruit_terms.', $this->getErrorOutput());

    $this->drush('oe_migration_views:generate', ['fruit_terms']);
    $this->drush('oe_migration_views:cleanup-map-tables');
    $this->assertContains('[success] Removed source_data column from migrate_map_fruit_terms.', $this->getErrorOutput());
    $this->assertContains('[success] Removed destination_data column from migrate_map_fruit_terms.', $this->getErrorOutput());
  }

}
