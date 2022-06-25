<?php

namespace Drupal\simple_cron_test\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * The simple cron queue test.
 *
 * @QueueWorker(
 *   id = "simple_cron_queue_test",
 *   title = @Translation("Queue test"),
 *   cron = {"time" = 20}
 * )
 */
class TestQueue extends QueueWorkerBase {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $this->getLogger('simple_cron_test')->info('@message', ['@message' => $data]);
  }

}
