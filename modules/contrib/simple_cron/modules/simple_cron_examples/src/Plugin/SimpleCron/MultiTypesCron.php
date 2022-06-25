<?php

namespace Drupal\simple_cron_examples\Plugin\SimpleCron;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\simple_cron\Plugin\SimpleCronPluginBase;

/**
 * Multiple types cron example implementation.
 *
 * @SimpleCron(
 *   id = "example_simple_cron_multi_types",
 *   label = @Translation("Example: Multiple types", context = "Simple cron")
 * )
 */
class MultiTypesCron extends SimpleCronPluginBase {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public function getTypeDefinitions(): array {
    return [
      'first' => [
        'label' => $this->t('First'),
      ],
      'second' => [
        'label' => $this->t('Second (run fail)'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function process(): void {
    if ($this->getType() === 'second') {
      $last_success_run = $this->getCronJob()->getLastRunTime();

      $message = sprintf(
        'The multi type %s cron not run. Last success run date: %s',
        $this->getType(),
        $last_success_run ? $last_success_run->format('Y-m-d H:i:s') : $this->t('Never')
      );

      throw new \RuntimeException($message);
    }

    $this->getLogger('simple_cron_examples')->info('Job type: @type', ['@type' => $this->getType()]);
  }

}
