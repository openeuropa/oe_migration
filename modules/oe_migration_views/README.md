# OpenEuropa Migration Views

Integrates Views and Migrate.

## Prerequisites

* [Migrate Tools](https://www.drupal.org/project/migrate_tools) >= 4.0
* [Migrate Plus](https://www.drupal.org/project/migrate_plus) v4 or v5

## Views integration

The OpenEuropa Migration Views module allows you to create Views from `migrate_map_*` and `migrate_message_*` tables.
Views relationships are available to join entity tables if the migrate destination plugin is an `EntityContentBase`.

It also contains an id_map plugin `oe_migration_views_sql` that will store 2 additional serialized columns containing:
- the source data (array **before** the migrate process pipeline)
- the destination data (array **after** the migrate process pipeline)

The "serialized" Views field can be used to show data (or a certain key) from those columns.

## Drush Commands

Several Drush 9 compatible commands are provided:

- `oe_migration_views:generate`

    Auto-generates Views for the given migration(s).
    Once a View with the same machine name than a migration exists, a `View details` link will be available
    in the `List migration` UI (admin/structure/migrate/manage/your_group/migrations)

- `oe_migration_views:cleanup-map-tables`

    Removes `source_data` and `destination_data` columns from the given migrate_map table(s).

## Important note

You will need the following patch to use the `oe_migration_views_sql` id_map plugin:

https://www.drupal.org/project/migrate_plus/issues/2944627
