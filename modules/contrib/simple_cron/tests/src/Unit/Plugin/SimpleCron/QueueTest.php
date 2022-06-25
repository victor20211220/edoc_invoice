<?php

namespace Drupal\Tests\simple_cron\Unit\Plugin\SimpleCron;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\Memory;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\State\State;
use Drupal\simple_cron\Entity\CronJobInterface;
use Drupal\simple_cron\Form\SimpleCronSettingsForm;
use Drupal\simple_cron\Plugin\SimpleCron\Queue;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\Argument\ArgumentsWildcard;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the Queue simple cron plugin.
 *
 * @group simple_cron
 * @coversDefaultClass \Drupal\simple_cron\Plugin\SimpleCron\Queue
 */
class QueueTest extends UnitTestCase {

  protected const REQUEUE_COUNT = 3;

  protected const CLAIM_TIME = 300;

  /**
   * The queue used to store test work items.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The current state of the test in memory.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * The container builder.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Build the container using the resulting mock objects.
    \Drupal::setContainer(new ContainerBuilder());
    $this->container = \Drupal::getContainer();

    // Construct a state object used for testing logger assertions.
    $this->state = new State(new KeyValueMemoryFactory());

    // Create a mock time service.
    $time = $this->prophesize(TimeInterface::class);

    // Build the logger using the resulting mock objects.
    $logger = $this->getLoggerMock();

    // Create a logger factory to produce the resulting logger.
    $logger_factory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $logger_factory->get('simple_cron')->willReturn($logger);

    // Create a queue instance for this queue worker.
    $queue_factory = $this->prophesize(QueueFactory::class);
    $queue_worker_manager = $this->getQueueWorkerMock($queue_factory);

    // Set services mocks.
    $this->container->set('state', $this->state);
    $this->container->set('plugin.manager.queue_worker', $queue_worker_manager);
    $this->container->set('queue', $queue_factory->reveal());
    $this->container->set('datetime.time', $time->reveal());
    $this->container->set('config.factory', $this->getConfigFactoryMock(TRUE));
    $this->container->set('string_translation', $this->getStringTranslationStub());
    $this->container->set('logger.factory', $logger_factory->reveal());
    $this->container->set('logger.channel.cron', $logger);
  }

  /**
   * Tests the process.
   *
   * @param string $item
   *   The item.
   * @param string $message_logged_assertion
   *   The message logged assertion.
   * @param int $expected
   *   The expected results.
   *
   * @covers ::process
   * @dataProvider providerTestProcess
   */
  public function testProcess(string $item, string $message_logged_assertion, int $expected): void {
    if ($item === 'DelayedRequeueException' && !class_exists(DelayedRequeueException::class)) {
      $this->markTestSkipped('DelayedRequeueException class doesn\'t exists in drupal 8.');
    }

    $this->resetTestingState();

    $this->queue->createItem($item);
    $this->assertFalse($this->state->get('state.message_logged'), 'Logger message is not set.');
    $this->assertEquals(1, $this->queue->numberOfItems(), 'Queue item is added.');

    $cron_job = $this->prophesize(CronJobInterface::class);
    $cron_job->getType()->willReturn('queue_worker_tests');
    $plugin = Queue::create($this->container, [], 'queue', []);
    $plugin->setCronJob($cron_job->reveal());
    $plugin->process();

    $this->{$message_logged_assertion}($this->state->get('state.message_logged'));
    $this->assertEquals($expected, $this->queue->numberOfItems(), 'Queue items quantity is correct.');
  }

  /**
   * Data provider for ::testProcess() method.
   *
   * @return array
   *   The data of process test.
   */
  public function providerTestProcess(): array {
    $tests = [
      ['Complete', 'assertFalse', 0],
      ['Exception', 'assertTrue', 1],
      ['DelayedRequeueException', 'assertFalse', 1],
      ['SuspendQueueException', 'assertTrue', 1],
      ['RequeueException', 'assertFalse', 0],
    ];

    return $tests;
  }

  /**
   * Verify that RequeueException causes an item to be processed multiple times.
   *
   * @throws \Exception
   */
  public function testRequeueException(): void {
    $this->resetTestingState();
    $this->queue->createItem('RequeueException');

    $cron_job = $this->prophesize(CronJobInterface::class);
    $cron_job->getType()->willReturn('queue_worker_tests');
    $plugin = Queue::create($this->container, [], 'queue', []);
    $plugin->setCronJob($cron_job->reveal());
    $plugin->process();

    // Fetch the number of times this item was requeued.
    $actual_requeue_count = $this->state->get('state.requeue_count');
    // Make sure the item was requeued at least once.
    $this->assertIsInt($actual_requeue_count, 'Requeue called at least once.');
    // Ensure that the actual requeue count matches the expected value.
    $this->assertEquals(self::REQUEUE_COUNT, $actual_requeue_count, 'Requeue called 3 times.');
  }

  /**
   * Tests the type definitions.
   *
   * @param bool $override_enabled
   *   TRUE when override enabled.
   * @param array $expected
   *   The expected result.
   *
   * @covers ::getTypeDefinitions
   * @dataProvider providerGetTypeDefinitions
   */
  public function testGetTypeDefinitions(bool $override_enabled, array $expected): void {
    $this->resetTestingState();
    $this->container->set('config.factory', $this->getConfigFactoryMock($override_enabled));

    $cron_job = $this->prophesize(CronJobInterface::class);
    $plugin = Queue::create($this->container, [], 'queue', []);
    $plugin->setCronJob($cron_job->reveal());
    $definitions = $plugin->getTypeDefinitions();

    // Convert translatable markup to string.
    foreach ($definitions as $type => $definition) {
      $definitions[$type]['label'] = (string) $definition['label'];
    }

    $this->assertEquals($expected, $definitions, 'Type definitions is correct.');
  }

  /**
   * Data provider for ::testGetTypeDefinitions() method.
   *
   * @return array
   *   The data of type definitions test.
   */
  public function providerGetTypeDefinitions(): array {
    return [
      [
        FALSE,
        [],
      ],
      [
        TRUE,
        [
          'queue_worker_tests' => [
            'label' => 'Test',
            'provider' => 'simple_cron_test',
            'configuration' => [
              'time' => static::CLAIM_TIME,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Resets the testing state.
   */
  protected function resetTestingState(): void {
    $this->state->set('state.message_logged', FALSE);
    $this->state->set('state.requeue_count', NULL);
  }

  /**
   * Get logger mock.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   The logger mock.
   */
  protected function getLoggerMock(): LoggerChannelInterface {
    // Create a mock logger to set a flag in the resulting state.
    $logger = $this->prophesize(LoggerChannelInterface::class);

    // Set a flag to track when a message is logged by adding a callback
    // function for each logging method.
    foreach (get_class_methods(LoggerInterface::class) as $logger_method) {
      $logger->{$logger_method}(Argument::cetera())->will(function () {
        \Drupal::state()->set('state.message_logged', TRUE);
      });
    }

    return $logger->reveal();
  }

  /**
   * Get config factory mock.
   *
   * @param bool $override_enabled
   *   TRUE when override enabled.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory mock.
   */
  protected function getConfigFactoryMock(bool $override_enabled): ConfigFactoryInterface {
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);

    $simple_cron_config = $this->prophesize(ImmutableConfig::class);
    $simple_cron_config->get('queue.override_enabled')->willReturn($override_enabled);
    $config_factory->get(SimpleCronSettingsForm::SETTINGS_NAME)->willReturn($simple_cron_config->reveal());

    return $config_factory->reveal();
  }

  /**
   * Get queue worker mock.
   *
   * @param \Prophecy\Prophecy\ObjectProphecy $queue_factory
   *   The queue factory mock.
   *
   * @return \Drupal\Core\Queue\QueueWorkerManagerInterface
   *   The queue worker manager mock.
   */
  protected function getQueueWorkerMock(ObjectProphecy $queue_factory): QueueWorkerManagerInterface {
    $queue_worker_manager = $this->prophesize(QueueWorkerManagerInterface::class);

    $queue_worker = 'queue_worker_tests';
    $queue_worker_definition = [
      'id' => $queue_worker,
      'title' => 'Test',
      'provider' => 'simple_cron_test',
      'cron' => [
        'time' => static::CLAIM_TIME,
      ],
    ];

    // Create a queue instance for this queue worker.
    $this->queue = new Memory($queue_worker);
    $queue_factory->get($queue_worker)->willReturn($this->queue);

    // Create a mock queue worker plugin instance based on above definition.
    $queue_worker_plugin = $this->prophesize(QueueWorkerInterface::class);
    $queue_worker_plugin->processItem('Complete')->willReturn();
    $queue_worker_plugin->processItem('Exception')->willThrow(\Exception::class);
    // DelayedRequeueException class doesn't exists in drupal 8.
    if (class_exists(DelayedRequeueException::class)) {
      $queue_worker_plugin->processItem('DelayedRequeueException')->willThrow(DelayedRequeueException::class);
    }
    $queue_worker_plugin->processItem('SuspendQueueException')->willThrow(SuspendQueueException::class);
    // 'RequeueException' would normally result in an infinite loop.
    //
    // This is avoided by throwing RequeueException for the first few calls to
    // ::processItem() and then returning void. ::testRequeueException()
    // establishes sanity assertions for this case.
    $queue_worker_plugin->processItem('RequeueException')->will(function ($args, $mock, $method) {
      // Fetch the number of calls to this prophesied method. This value will
      // start at zero during the first call.
      $method_calls = count($mock->findProphecyMethodCalls($method->getMethodName(), new ArgumentsWildcard($args)));

      // Throw the expected exception on the first few calls.
      if ($method_calls < self::REQUEUE_COUNT) {
        \Drupal::state()->set('state.requeue_count', $method_calls + 1);
        throw new RequeueException();
      }
    });

    // Set the mock queue worker manager to return the definition/plugin.
    $queue_worker_manager->getDefinitions()->willReturn([$queue_worker => $queue_worker_definition]);
    $queue_worker_manager->createInstance($queue_worker)->willReturn($queue_worker_plugin->reveal());

    return $queue_worker_manager->reveal();
  }

}
