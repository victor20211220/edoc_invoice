CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Single cron job run
 * Drush commands
 * Examples
 * Plugin

INTRODUCTION
------------

The Simple Cron is a light-weight module for cron job management.

The module provides a SimpleCron plugin that simplifies the
implementation of new cron jobs.
The build configuration form is also included in the SimpleCron plugin
to make it easier to create a custom cron job settings.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/simple_cron

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/simple_cron

REQUIREMENTS
------------

 * PHP 7.2.0 or later
 * [dragonmantank/cron-expression](https://packagist.org/packages/dragonmantank/cron-expression)

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.

CONFIGURATION
-------------

Once created, the simple cron job can be configured in
/admin/config/system/cron/jobs administration page.

All settings for this module are on the Simple Cron configuration page,
under the Configuration &rightarrow; System &rightarrow; Cron &rightarrow;
Simple cron settings.

You can visit the configuration page directly
at admin/config/system/cron/jobs/settings.

SINGLE CRON JOB RUN
-------------------

Single cron job run works by adding cron job ID in default cron run url
query parameter. Use the "force" query parameter for cron run without checking
crontab expresion.

Examples:
 * https://localhost/cron/[system.cron_key]?job=job_id
 * https://localhost/cron/[system.cron_key]?job=job_id&force=1

DRUSH COMMANDS
--------------

Module provides two new drush commands:

 - drush simple-cron job_id
 - drush simple-cron:list

EXAMPLES
--------

This module allows developers to add custom cron jobs by providing a plugin.
New plugin type is provided: SimpleCron. The Simple Cron Examples
module (modules/simple_cron_examples) contains ready to use plugin examples.
You can copy an example over to your (custom) module and modify it
to suit your needs.

PLUGIN
------

Plugin of type "SimpleCron" is used to provide simple cron jobs implementation.
Plugin examples can be found in the included simple_cron_example module.

SimpleCron plugins must be placed in: [module name]/src/Plugin/SimpleCron.
After creating a plugin, clear the cache to make Drupal recognise it.

SimpleCron plugins must at least extend the SimpleCronPluginInterface.

SimpleCron annotation should at least contain:
```
 * @SimpleCron(
 *   id = "plugin_id",
 *   label = @Translation("Job name"),
 * )
```

Other annotation options:
```
 *   weight = 0
```
