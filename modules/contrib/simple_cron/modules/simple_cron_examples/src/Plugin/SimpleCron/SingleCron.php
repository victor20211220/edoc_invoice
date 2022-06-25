<?php

namespace Drupal\simple_cron_examples\Plugin\SimpleCron;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\simple_cron\Plugin\SimpleCronPluginBase;

/**
 * Single cron example implementation.
 *
 * @SimpleCron(
 *   id = "example_simple_cron_single",
 *   label = @Translation("Example: Single", context = "Simple cron")
 * )
 */
class SingleCron extends SimpleCronPluginBase {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public function process(): void {
    $this->getLogger('simple_cron_examples')->info('Simple cron run successfully');
  }

}
