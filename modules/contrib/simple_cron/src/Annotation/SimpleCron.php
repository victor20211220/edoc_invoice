<?php

namespace Drupal\simple_cron\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a SimpleCron annotation object.
 *
 * @see \Drupal\simple_cron\Plugin\SimpleCronPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class SimpleCron extends Plugin {

  /**
   * The plugin id.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * An optional weight of the plugin.
   *
   * @var int
   *
   * @ingroup plugin_translatable
   */
  public $weight = 0;

}
