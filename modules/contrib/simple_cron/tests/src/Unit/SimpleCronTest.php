<?php

namespace Drupal\Tests\simple_cron\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\State\State;
use Drupal\simple_cron\CronJobManagerInterface;
use Drupal\simple_cron\Entity\CronJobInterface;
use Drupal\simple_cron\Form\SimpleCronSettingsForm;
use Drupal\simple_cron\SimpleCron;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the Cron class.
 *
 * @group simple_cron
 * @coversDefaultClass \Drupal\simple_cron\SimpleCron
 */
class SimpleCronTest extends UnitTestCase {

  protected const REQUEST_TIME = 1615109176;

  /**
   * An instance of the Cron class for testing.
   *
   * @var \Drupal\simple_cron\SimpleCron
   */
  protected $cron;

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
  }

  /**
   * Test cron run.
   *
   * @param bool $locked
   *   Is lock enabled.
   * @param bool $cron_override
   *   Is cron override enabled.
   * @param bool $queue_override
   *   Is queue override enabled.
   *
   * @covers ::run
   * @covers ::cronJobsRun
   * @covers ::runDefaultCronHandlers
   * @dataProvider providerTestRun
   *
   * @throws \Exception
   */
  public function testRun(bool $locked, bool $cron_override, bool $queue_override): void {
    $this->resetTestingState();
    $this->resetCronMock($locked, $cron_override, $queue_override);
    $status = $this->cron->run();

    $this->assertEquals(!$locked, $status, 'Cron run status is correct.');
    $this->assertEquals(!$locked, $this->state->get('state.message_logged'), 'Complete status is logged.');
    $this->assertEquals(!$queue_override, $this->state->get('state.queue_requested'), 'Default queue run works.');
    $this->assertEquals($queue_override, $this->state->get('state.queue_requested.override'), 'Queue override works.');
    $this->assertEquals(!$locked && !$cron_override, $this->state->get('state.cron_requested'), 'Default cron run works.');
    $this->assertEquals($cron_override, $this->state->get('state.cron_requested.override'), 'Cron override works.');
    $this->assertFalse($this->state->get('state.manual_cron'), 'Manual cron is not run.');
    $this->assertFalse($this->state->get('state.single_cron'), 'Single cron is not run.');
  }

  /**
   * Data provider for ::testRun() method.
   *
   * @return array
   *   The data of run test.
   */
  public function providerTestRun(): array {
    return [
      [FALSE, FALSE, FALSE],
      [FALSE, FALSE, TRUE],
      [FALSE, TRUE, FALSE],
      [FALSE, TRUE, TRUE],
      [TRUE, FALSE, FALSE],
      [TRUE, FALSE, TRUE],
    ];
  }

  /**
   * Test manual single cron run.
   *
   * @covers ::run
   *
   * @throws \Exception
   */
  public function testManualCronRun(): void {
    $this->resetTestingState();
    $this->resetCronMock(FALSE, FALSE, FALSE);

    // Run default cron.
    $this->cron->run();
    $this->assertFalse($this->state->get('state.manual_cron'), 'Manual cron is not run.');

    // Run manual single cron.
    $this->resetTestingState();
    $this->cron->setRequestStack($this->getRequestStackMock(TRUE, FALSE));
    $status = $this->cron->run();
    $this->assertTrue($status, 'Cron run status is correct.');
    $this->assertTrue($this->state->get('state.manual_cron'), 'Manual cron is run.');
    $this->assertFalse($this->state->get('state.single_cron'), 'Single cron is not run.');
    $this->assertFalse($this->state->get('state.message_logged'), 'Complete status is not logged.');
    $this->assertFalse($this->state->get('state.queue_requested'), 'Default queue not run.');
    $this->assertFalse($this->state->get('state.queue_requested.override'), 'Queue override not run.');
    $this->assertFalse($this->state->get('state.cron_requested'), 'Default cron not run.');
    $this->assertFalse($this->state->get('state.cron_requested.override'), 'Cron override not run.');
  }

  /**
   * Test manual single cron run.
   *
   * @covers ::run
   *
   * @throws \Exception
   */
  public function testSingleCronRun(): void {
    $this->resetTestingState();
    $this->resetCronMock(FALSE, FALSE, FALSE);

    // Run default cron.
    $this->cron->run();
    $this->assertFalse($this->state->get('state.single_cron'), 'Single cron is not run.');

    // Run manual single cron not forced.
    $this->resetTestingState();
    $this->cron->setRequestStack($this->getRequestStackMock(FALSE, TRUE));
    $status = $this->cron->run();
    $this->assertFalse($status, 'Cron run status is correct.');
    $this->assertFalse($this->state->get('state.single_cron'), 'Single cron is not run.');
    $this->assertFalse($this->state->get('state.manual_cron'), 'Manual cron is not run.');
    $this->assertFalse($this->state->get('state.message_logged'), 'Complete status is not logged.');
    $this->assertFalse($this->state->get('state.queue_requested'), 'Default queue not run.');
    $this->assertFalse($this->state->get('state.queue_requested.override'), 'Queue override not run.');
    $this->assertFalse($this->state->get('state.cron_requested'), 'Default cron not run.');
    $this->assertFalse($this->state->get('state.cron_requested.override'), 'Cron override not run.');

    // Run manual single cron forced.
    $this->resetTestingState();
    $this->cron->setRequestStack($this->getRequestStackMock(FALSE, TRUE, TRUE));
    $status = $this->cron->run();
    $this->assertTrue($status, 'Cron run status is correct.');
    $this->assertTrue($this->state->get('state.single_cron'), 'Single cron is run.');
    $this->assertFalse($this->state->get('state.manual_cron'), 'Manual cron is not run.');
    $this->assertFalse($this->state->get('state.message_logged'), 'Complete status is not logged.');
    $this->assertFalse($this->state->get('state.queue_requested'), 'Default queue not run.');
    $this->assertFalse($this->state->get('state.queue_requested.override'), 'Queue override not run.');
    $this->assertFalse($this->state->get('state.cron_requested'), 'Default cron not run.');
    $this->assertFalse($this->state->get('state.cron_requested.override'), 'Cron override not run.');
  }

  /**
   * Resets the testing state.
   */
  protected function resetTestingState(): void {
    $this->state->set('state.manual_cron', FALSE);
    $this->state->set('state.single_cron', FALSE);
    $this->state->set('state.message_logged', FALSE);
    $this->state->set('state.queue_requested', FALSE);
    $this->state->set('state.queue_requested.override', FALSE);
    $this->state->set('state.cron_requested', FALSE);
    $this->state->set('state.cron_requested.override', FALSE);
  }

  /**
   * Reset cron mock.
   *
   * @param bool $locked
   *   Is lock enabled.
   * @param bool $cron_override
   *   Is cron override enabled.
   * @param bool $queue_override
   *   Is queue override enabled.
   *
   * @throws \Exception
   */
  protected function resetCronMock(bool $locked, bool $cron_override, bool $queue_override): void {
    // Build the logger using the resulting mock objects.
    $logger = $this->getLoggerMock($locked);

    // Create a logger factory to produce the resulting logger.
    $logger_factory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $logger_factory->get('simple_cron')->willReturn($logger);

    // Create a mock time service.
    $time = $this->prophesize(TimeInterface::class);
    $time->getCurrentTime()->willReturn(static::REQUEST_TIME);
    $time->getRequestTime()->willReturn(static::REQUEST_TIME);

    // Create a lock that will always fail when attempting to acquire; we're
    // only interested in testing ::processQueues(), not the other stuff.
    $lock_backend = $this->prophesize(LockBackendInterface::class);
    $lock_backend->acquire('cron', Argument::cetera())->willReturn(!$locked);
    $lock_backend->release('cron')->willReturn(new \stdClass());

    // Create mock objects for constructing the Cron class.
    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $account_switcher = $this->prophesize(AccountSwitcherInterface::class);

    // Create a queue instance for this queue worker.
    $queue_factory = $this->prophesize(QueueFactory::class);
    $queue_worker_manager = $this->prophesize(QueueWorkerManagerInterface::class);

    // Set services mocks.
    $this->container->set('config.factory', $this->getConfigFactoryMock($cron_override, $queue_override));
    $this->container->set('datetime.time', $time->reveal());
    $this->container->set('state', $this->state);
    $this->container->set('module_handler', $module_handler->reveal());
    $this->container->set('lock', $lock_backend->reveal());
    $this->container->set('queue', $queue_factory->reveal());
    $this->container->set('account_switcher', $account_switcher->reveal());
    $this->container->set('logger.channel.cron', $logger);
    $this->container->set('plugin.manager.queue_worker', $queue_worker_manager->reveal());

    // Construct the Cron class to test.
    $this->createSimpleCronMock();

    // Set cron job manager.
    $this->cron->setCronJobManager($this->getCronJobManagerMock($cron_override, $queue_override));

    // Set request stack service.
    $this->cron->setRequestStack($this->getRequestStackMock(FALSE, FALSE));

    // Set config factory.
    $this->cron->setConfigFactory($this->container->get('config.factory'));
  }

  /**
   * Create simple cron mock.
   *
   * @throws \Exception
   */
  protected function createSimpleCronMock(): void {
    $cron = $this->getMockBuilder(SimpleCron::class);
    $cron->setConstructorArgs([
      $this->container->get('module_handler'),
      $this->container->get('lock'),
      $this->container->get('queue'),
      $this->container->get('state'),
      $this->container->get('account_switcher'),
      $this->container->get('logger.channel.cron'),
      $this->container->get('plugin.manager.queue_worker'),
      $this->container->get('datetime.time'),
    ]);

    $cron->setMethods(['invokeCronHandlers', 'processQueues']);

    $this->cron = $cron->getMock();

    $this->cron->method('invokeCronHandlers')->willReturnCallback(function () {
      \Drupal::state()->set('state.cron_requested', TRUE);
    });

    $this->cron->method('processQueues')->willReturnCallback(function () {
      \Drupal::state()->set('state.queue_requested', TRUE);
    });
  }

  /**
   * Get config override mock.
   *
   * @param bool $cron_override
   *   Is cron override enabled.
   * @param bool $queue_override
   *   Is queue override enabled.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config override mock.
   */
  protected function getConfigFactoryMock(bool $cron_override, bool $queue_override): ConfigFactoryInterface {
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);

    $simple_cron_config = $this->prophesize(ImmutableConfig::class);
    $simple_cron_config->get('base.max_execution_time')->willReturn(240);
    $simple_cron_config->get('base.lock_timeout')->willReturn(900);
    $simple_cron_config->get('cron.override_enabled')->willReturn($cron_override);
    $simple_cron_config->get('queue.override_enabled')->willReturn($queue_override);
    $config_factory->get(SimpleCronSettingsForm::SETTINGS_NAME)->willReturn($simple_cron_config->reveal());

    $system_cron_config = $this->prophesize(ImmutableConfig::class);
    $system_cron_config->get('logging')->willReturn(FALSE);
    $config_factory->get('system.cron')->willReturn($system_cron_config->reveal());

    return $config_factory->reveal();
  }

  /**
   * Get logger mock.
   *
   * @param bool $locked
   *   Is lock enabled.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   The logger mock.
   */
  protected function getLoggerMock(bool $locked): LoggerChannelInterface {
    // Create a mock logger to set a flag in the resulting state.
    $logger = $this->prophesize(LoggerChannelInterface::class);
    // Safely ignore the cron re-run message when failing to acquire a lock.
    //
    // We don't need to run regular cron tasks, and we're still implicitly
    // testing that queues are being processed.
    //
    // This argument will need to be updated to match the message text in
    // Drupal\Core\Cron::run() should the original text ever be updated.
    if ($locked) {
      $logger->warning('Attempting to re-run cron while it is already running.')->shouldBeCalled();
    }
    else {
      $logger->warning('Attempting to re-run cron while it is already running.')->shouldNotBeCalled();
    }

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
   * Get cron job manager mock.
   *
   * @param bool $cron_override
   *   Is cron override enabled.
   * @param bool $queue_override
   *   Is queue override enabled.
   *
   * @return \Drupal\simple_cron\CronJobManagerInterface
   *   The cron job manager mock.
   */
  protected function getCronJobManagerMock(bool $cron_override, bool $queue_override): CronJobManagerInterface {
    $cron_job_manager = $this->prophesize(CronJobManagerInterface::class);

    // Create single cron job mock.
    $job = $this->prophesize(CronJobInterface::class);

    $job->run(static::REQUEST_TIME, FALSE)->will(function () {
      return FALSE;
    });

    $job->run(static::REQUEST_TIME, TRUE)->will(function () {
      \Drupal::state()->set('state.single_cron', TRUE);
      return TRUE;
    });

    $cron_job_manager->getEnabledJob('single_job')->willReturn($job);

    // Create enabled cron jobs mock.
    $jobs = [];

    // Create cron jobs mock.
    if ($cron_override) {
      $job = $this->prophesize(CronJobInterface::class);
      $job->run(static::REQUEST_TIME)->will(function () {
        \Drupal::state()->set('state.cron_requested.override', TRUE);
        return TRUE;
      });

      $jobs[] = $job->reveal();
    }

    // Create queue jobs mock.
    if ($queue_override) {
      $job = $this->prophesize(CronJobInterface::class);
      $job->run(static::REQUEST_TIME)->will(function () {
        \Drupal::state()->set('state.queue_requested.override', TRUE);
        return TRUE;
      });

      $jobs[] = $job->reveal();
    }

    $cron_job_manager->getEnabledDefaultRunJobs()->willReturn($jobs);

    return $cron_job_manager->reveal();
  }

  /**
   * Get request stack mock.
   *
   * @param bool $is_manual
   *   Is manual request.
   * @param bool $is_single
   *   Is single request.
   * @param bool $is_single_forced
   *   (optional) Is single cron is forced. Defaults to FALSE.
   *
   * @return \Symfony\Component\HttpFoundation\RequestStack
   *   The request stack mock.
   */
  protected function getRequestStackMock(bool $is_manual, bool $is_single, bool $is_single_forced = FALSE): RequestStack {
    $request_stack = $this->prophesize(RequestStack::class);
    $request = Request::create('http://localhost');

    if ($is_manual) {
      $request->attributes->set('_route', 'entity.simple_cron_job.run');
      $job = $this->prophesize(CronJobInterface::class);
      $job->run(static::REQUEST_TIME, TRUE)->will(function () {
        \Drupal::state()->set('state.manual_cron', TRUE);
        return TRUE;
      });
      $request->attributes->set('simple_cron_job', $job->reveal());
      $request_stack->getCurrentRequest()->willReturn($request);
    }

    if ($is_single) {
      $request->query->set('job', 'single_job');
      $request_stack->getCurrentRequest()->willReturn($request);

      if ($is_single_forced) {
        $request->query->set('force', TRUE);
      }
    }

    return $request_stack->reveal();
  }

}
