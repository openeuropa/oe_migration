# OpenEuropa Migration

The OpenEuropa Migration provides extensions to the core migration framework, to migrate data from Drupal 7 to the OpenEuropa project.

### Process plugins

* **ApplyFilters (oe_migration_apply_filters)** : Applies filters of the given filter format to a string.
* **Pipeline (oe_migration_pipeline)** : Executes a group of process plugins, stored in a `oe_migration_process_pipeline` configuration entity.

## Development setup

You can build the development site by running the following steps:

* Install the Composer dependencies:

```bash
composer install
```

A post command hook (`drupal:site-setup`) is triggered automatically after `composer install`.
It will make sure that the necessary symlinks are properly setup in the development site.
It will also perform token substitution in development configuration files such as `behat.yml.dist`.

* Install test site by running:

```bash
./vendor/bin/run drupal:site-install
```

The development site web root should be available in the `build` directory.

### Using Docker Compose

Alternatively, you can build a development site using [Docker](https://www.docker.com/get-docker) and
[Docker Compose](https://docs.docker.com/compose/) with the provided configuration.

Docker provides the necessary services and tools such as a web server and a database server to get the site running,
regardless of your local host configuration.

#### Requirements:

- [Docker](https://www.docker.com/get-docker)
- [Docker Compose](https://docs.docker.com/compose/)

#### Configuration

By default, Docker Compose reads two files, a `docker-compose.yml` and an optional `docker-compose.override.yml` file.
By convention, the `docker-compose.yml` contains your base configuration and it's provided by default.
The override file, as its name implies, can contain configuration overrides for existing services or entirely new
services.
If a service is defined in both files, Docker Compose merges the configurations.

Find more information on Docker Compose extension mechanism on [the official Docker Compose documentation](https://docs.docker.com/compose/extends/).

#### Usage

To start, run:

```bash
docker-compose up
```

It's advised to not daemonize `docker-compose` so you can turn it off (`CTRL+C`) quickly when you're done working.
However, if you'd like to daemonize it, you have to add the flag `-d`:

```bash
docker-compose up -d
```

Then:

```bash
docker-compose exec web composer install
docker-compose exec web ./vendor/bin/run drupal:site-install
```

Using default configuration, the development site files should be available in the `build` directory and the development site
should be available at: [http://127.0.0.1:8080/build](http://127.0.0.1:8080/build).

##### How to use the Pipelines plugin
The process plugin Pipeline executes a process pipeline loaded from configuration.

This configuration entity can be created in the database of different ways, provided by the Drupal Core modules:
- Using the command `drush config:import` with the `--partial` option (`drush config:import --partial --source/path/to/the/folder/`).
- Using the UI provided by the module Configuration Manager to import single files (or going directly to the path `/admin/config/development/configuration/single/import_en`)
- Setting a correct yaml file into a /config/install directory inside a custom module.

This is an example of a `oe_migration.oe_migration_process_pipeline` config entity in a yaml format, ready to be imported:

```
  id: my_process_pipeline
  label: 'My process pipeline'
  description: 'Process pipeline for processing fulltext fields'
  process:
    -
      plugin: dom
      method: import
    -
      plugin: dom_str_replace
      mode: attribute
      xpath: '//a'
      attribute_options:
        name: href
      search: 'foo'
      replace: 'bar'
    -
      plugin: dom_str_replace
      mode: attribute
      xpath: '//a'
      attribute_options:
        name: href
      regex: true
      search: '/foo/'
      replace: TOKEN1
    -
      plugin: dom
      method: export
```

#### Running the tests

To run the grumphp checks:

```bash
docker-compose exec web ./vendor/bin/grumphp run
```

To run the phpunit tests:

```bash
docker-compose exec web ./vendor/bin/phpunit
```

To run the behat tests:

```bash
docker-compose exec web ./vendor/bin/behat
```

## Contributing

Please read [the full documentation](https://github.com/openeuropa/openeuropa) for details on our code of conduct, and the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the available versions, see the [tags on this repository](https://github.com/openeuropa/oe_migration/tags).
