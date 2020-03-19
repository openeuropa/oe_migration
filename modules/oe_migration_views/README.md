Introduction
------------
This module integrates Views and Migrate and allows you to create reports using Views.

Once enabled, you'll be able to create Views from `migrate_map_*` and `migrate_message_*` tables.
Views relationships are available to join entity tables if the migrate destination plugin is an `EntityContentBase`.

It also contains an id_map plugin `oe_migration_views_sql` that will store 2 additional serialized columns containing:
- the source data (array before the process pipeline ran)
- the destination data (array after process pipeline ran)

The "serialized" Views field can be used to show data (or a certain key) from those columns.

A Drush 9 command `oe_migration_views:generate` is available to auto-generate Views for migration(s).
Once a View with the same machine name than a migration exists, a `View details` link will be available
in the `List migration` UI (admin/structure/migrate/manage/your_group/migrations)

Important note
--------------
You will need the following patch to use the `oe_migration_views_sql` id_map plugin:
https://www.drupal.org/project/migrate_plus/issues/2944627
