# OpenEuropa Migration Views

Integrates Views and Migrate.

## Prerequisites

* [Migrate Tools](https://www.drupal.org/project/migrate_tools) >= 4.0
* [Migrate Plus](https://www.drupal.org/project/migrate_plus) v4 or v5

## Views integration

The OpenEuropa Migration Views module allows you to create Views from `migrate_map_*` and `migrate_message_*` tables.

Views relationships are available to join entity tables if the migrate destination plugin is an `EntityContentBase`.

## Extended SQL id_map Plugin

An id_map plugin `oe_migration_views_sql_data` is also available and will store 2 additional serialized columns containing:
- the source data (array **before** the migrate process pipeline)
- the destination data (array **after** the migrate process pipeline)

Migrations can use it as follows:
```
...
id: fruit_terms
migration_group: default
idMap:
  plugin: oe_migration_views_sql_data
...
```

The `serialized` Views field can be used to show the data from those columns, or certain key(s) within the array.

#### Important note

You will need the following patch to use the `oe_migration_views_sql_data` id_map plugin: https://www.drupal.org/project/migrate_plus/issues/2944627

## Drush Commands

Several Drush 9 compatible commands are provided:

- `oe_migration_views:generate`

    Auto-generates Views for the given migration(s).
    Once a View with the machine name `groupID_migrationID` with a `page_1` display exists, a `View details` link will
    be available in the `List migration` UI (admin/structure/migrate/manage/your_group/migrations) if the user has the
    `view migrate reports` permission.

    e.g: A migration with group `default`, id `test_migration` needs a view with machine name `default_test_migration`
    and a page display with id `page_1`.

- `oe_migration_views:cleanup-map-tables`

    Removes `source_data` and `destination_data` columns from the given migrate_map table(s).
