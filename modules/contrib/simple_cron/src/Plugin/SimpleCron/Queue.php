<?php

namespace Drupal\simple_cron\Plugin\SimpleCron;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Utility\Error;
use Drupal\simple_cron\Form\SimpleCronSettingsForm;
use Drupal\simple_cron\Plugin\SimpleCronPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue jobs handler.
 *
 * @SimpleCron(
 *   id = "queue",
 *   label = @Translation("Queue worker", context = "Simple cron"),
 *   weight = 100
 * )
 */
class Queue extends SimpleCronPluginBase {

  use LoggerChannelTrait;

  /**
   * The queue worker manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->queueManager = $container->get('plugin.manager.queue_worker');
    $instance->queueFactory = $container->get('queue');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeDefinitions(): array {
    $definitions = [];

    if ($this->configFactory->get(SimpleCronSettingsForm::SETTINGS_NAME)->get('queue.override_enabled')) {
      $queue_definitions = $this->queueManager->getDefinitions();

      foreach ($queue_definitions as $definition) {
        if (isset($definition['cron'])) {
          $definitions[$definition['id']] = [
            'label' => $definition['title'],
            'provider' => $definition['provider'],
            'configuration' => [
              'time' => $definition['cron']['time'] ?? 15,
            ],
          ];
        }
      }
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'time' => 15,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['time'] = [
      '#type' => 'number',
      '#title' => $this->t('Cron time'),
      '#default_value' => $this->getConfigValue('time'),
      '#step' => 1,
      '#min' => 1,
      '#max' => 3600,
      '#required' => TRUE,
      '#description' => $this->t('How much time Drupal cron should spend on calling this worker in seconds.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function process(): void {
    $queue_name = $this->getType();

    // Make sure every queue exists. There is no harm in trying to recreate
    // an existing queue.
    $this->queueFactory->get($queue_name)->createQueue();

    $queue_worker = $this->queueManager->createInstance($queue_name);
    $time = $this->getConfigValue('time');
    $queue = $this->queueFactory->get($queue_name);
    $end = time() + ($time ?? 15);
    $lease_time = $time ?? 0;

    while (time() < $end && ($item = $queue->claimItem($lease_time))) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (DelayedRequeueException $e) {
        // The worker requested the task not be immediately re-queued.
        // - If the queue doesn't support ::delayItem(), we should leave the
        // item's current expiry time alone.
        // - If the queue does support ::delayItem(), we should allow the
        // queue to update the item's expiry using the requested delay.
        if ($queue instanceof DelayableQueueInterface) {
          // This queue can handle a custom delay; use the duration provided
          // by the exception.
          $queue->delayItem($item, $e->getDelay());
        }
      }
      catch (RequeueException $e) {
        // The worker requested the task be immediately requeued.
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException $e) {
        // If the worker indicates there is a problem with the whole queue,
        // release the item and skip to the next queue.
        $queue->releaseItem($item);

        $message = '%type: @message in %function (line %line of %file).';
        $this->getLogger('simple_cron')->error($message, Error::decodeException($e));

        // Stop this queue.
        return;
      }
      catch (\Exception $e) {
        // In case of any other kind of exception, log it and leave the item
        // in the queue to be processed again later.
        $message = '%type: @message in %function (line %line of %file).';
        $this->getLogger('simple_cron')->error($message, Error::decodeException($e));
      }
    }
  }

}
