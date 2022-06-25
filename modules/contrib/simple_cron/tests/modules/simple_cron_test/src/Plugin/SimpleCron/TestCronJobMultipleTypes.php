<?php

namespace Drupal\simple_cron_test\Plugin\SimpleCron;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\simple_cron\Plugin\SimpleCronPluginBase;

/**
 * Test cron job.
 *
 * @SimpleCron(
 *   id = "test_cron_job_multi_types",
 *   label = @Translation("Test cron job multiple types", context = "Simple cron"),
 *   weight = 1
 * )
 */
class TestCronJobMultipleTypes extends SimpleCronPluginBase {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public function getTypeDefinitions(): array {
    return [
      'type1' => [
        'label' => $this->t('Type 1'),
      ],
      'type2' => [
        'label' => $this->t('Type 2'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function process(): void {
    $this->getLogger('test_cron_job_multi_types')->notice(
      'Test cron job multiple types successfully run @type.',
      ['@type' => $this->getType()]
    );
  }

}
