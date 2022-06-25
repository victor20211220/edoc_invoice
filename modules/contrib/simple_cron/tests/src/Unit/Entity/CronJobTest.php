<?php

namespace Drupal\Tests\simple_cron\Unit\Entity;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\State;
use Drupal\simple_cron\Entity\CronJob;
use Drupal\simple_cron\Form\SimpleCronSettingsForm;
use Drupal\simple_cron\Plugin\SimpleCronPluginInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Cron job entity unit tests.
 *
 * Tests the Cron simple cron plugin.
 *
 * @group simple_cron
 * @coversDefaultClass \Drupal\simple_cron\Entity\CronJob
 */
class CronJobTest extends UnitTestCase {

  protected const JOB_ID = 'simple_cron_job';

  protected const JOB_LABEL = 'Simple Cron Test';

  protected const REQUEST_TIME = 1615109176;

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
   * The cron job mock.
   *
   * @var \Drupal\simple_cron\Entity\CronJob|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $job;

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

    // Create a simple cron plugin manager mock.
    $simple_cron_plugin_manager = $this->prophesize(SimpleCronPluginInterface::class);

    // Create a database connection mock.
    $database = $this->prophesize(Connection::class);

    // Set services mocks.
    $this->container->set('state', $this->state);
    $this->container->set('database', $database);
    $this->container->set('plugin.manager.simple_cron', $simple_cron_plugin_manager->reveal());
    $this->container->set('config.factory', $this->getConfigFactoryMock());
  }

  /**
   * Test cron job run.
   *
   * @param bool $locked
   *   Is lock enabled.
   * @param bool $should_run
   *   Is should run.
   * @param string|null $plugin_throw
   *   The plugin process throw on run.
   * @param bool $expected
   *   The expected result.
   *
   * @covers ::run
   *
   * @dataProvider providerTestRun
   */
  public function testRun(bool $locked, bool $should_run, ?string $plugin_throw, bool $expected): void {
    $this->resetTestingState();
    $this->resetJobMock($locked);

    $plugin = $this->prophesize(SimpleCronPluginInterface::class);
    $plugin->label()->willReturn(static::JOB_LABEL);

    if (isset($plugin_throw)) {
      $plugin->process()->willThrow($plugin_throw);
    }
    else {
      $plugin->process();
    }

    $this->job->method('getCronTab')->willReturn('* * * * *');
    $this->job->method('getNextRunTime')->willReturn($this->getDrupalDateTimeMock(static::REQUEST_TIME));
    $this->job->method('status')->willReturn($should_run);
    $this->job->method('getPlugin')->willReturn($plugin->reveal());

    $status = $this->job->run(static::REQUEST_TIME);
    $last_run_time_is_set = !empty($this->state->get('simple_cron.state.' . static::JOB_ID)['last_run']);

    $this->assertEquals($expected, $last_run_time_is_set, 'Last run time is correct.');
    $this->assertEquals($expected, $status, 'Cron run is executed.');
    $this->assertEquals(!$locked, $this->state->get('state.message_logged'), 'Message is logged');
  }

  /**
   * Data provider for ::testRun() method.
   *
   * @return array
   *   The data of reset next run test.
   */
  public function providerTestRun(): array {
    return [
      [FALSE, FALSE, NULL, FALSE],
      [FALSE, TRUE, NULL, TRUE],
      [TRUE, TRUE, NULL, FALSE],
      [FALSE, FALSE, \Exception::class, FALSE],
      [FALSE, TRUE, \Exception::class, FALSE],
      [TRUE, TRUE, \Exception::class, FALSE],
      [TRUE, TRUE, \Exception::class, FALSE],
      [FALSE, TRUE, \RuntimeException::class, FALSE],
      [FALSE, TRUE, \Throwable::class, FALSE],
    ];
  }

  /**
   * Tests cron job should run.
   *
   * @param bool $status
   *   The status.
   * @param int $request_time
   *   The request time.
   * @param int $next_run_time
   *   The next run time.
   * @param bool $force
   *   The cron job force run.
   * @param bool $expected
   *   The expected result.
   *
   * @covers ::shouldRun
   * @dataProvider providerTestShouldRun
   */
  public function testShouldRun(bool $status, int $request_time, int $next_run_time, bool $force, bool $expected): void {
    $this->resetSimpleJobMock();
    $this->job->method('getNextRunTime')->willReturn($this->getDrupalDateTimeMock($next_run_time));
    $this->job->method('status')->willReturn($status);

    $this->assertEquals($expected, $this->job->shouldRun($request_time, $force), 'Should run status is valid.');
  }

  /**
   * Data provider for ::testShouldRun() method.
   *
   * @return array
   *   The data of should run test.
   */
  public function providerTestShouldRun(): array {
    return [
      // Tests timestamp conditions.
      [TRUE, 1615123584, 1615123584, FALSE, TRUE],
      [TRUE, 1615123585, 1615123584, FALSE, TRUE],
      [TRUE, 1615123584, 1615123585, FALSE, FALSE],
      // Tests status conditions.
      [FALSE, 1615123584, 1615123584, FALSE, FALSE],
      [FALSE, 1615123585, 1615123584, FALSE, FALSE],
      [FALSE, 1615123584, 1615123585, FALSE, FALSE],
      // Force condition.
      [TRUE, 1615123584, 1615123584, TRUE, TRUE],
      [TRUE, 1615123585, 1615123584, TRUE, TRUE],
      [TRUE, 1615123584, 1615123585, TRUE, TRUE],
      [FALSE, 1615123584, 1615123584, TRUE, TRUE],
      [FALSE, 1615123585, 1615123584, TRUE, TRUE],
      [FALSE, 1615123584, 1615123585, TRUE, TRUE],
    ];
  }

  /**
   * Tests reset next run time.
   *
   * @param string $crontab
   *   The crontab expresion.
   * @param int $request_time
   *   The request time.
   * @param int $current_next_run_time
   *   The current next run time.
   * @param int $expected
   *   The expected result.
   *
   * @covers ::resetNextRunTime
   * @dataProvider providerTestResetNextRunTime
   */
  public function testResetNextRunTime(string $crontab, int $request_time, int $current_next_run_time, int $expected): void {
    $this->resetSimpleJobMock();
    $this->job->method('getCronTab')->willReturn($crontab);
    $this->job->method('getState')->willReturn($current_next_run_time);

    $this->assertEquals($expected, $this->job->resetNextRunTime($request_time), 'Next run time is valid.');
  }

  /**
   * Data provider for ::testResetNextRunTime() method.
   *
   * @return array
   *   The data of reset next run test.
   */
  public function providerTestResetNextRunTime(): array {
    return [
      // Tests of different request times with same crontab expression.
      ['* * * * *', 1614000000, 0, 1614000060],
      ['* * * * *', 1614000059, 0, 1614000060],
      ['* * * * *', 1613999999, 0, 1614000000],
      // Tests of different crontab expressions.
      ['*/2 * * * *', 1614000000, 0, 1614000120],
      ['25 * * * *', 1614000000, 0, 1614000300],
      ['* 7 15 * *', 1614000000, 0, 1615752000],
      ['* 7 15 5 *', 1614000000, 0, 1621026000],
      ['* 7 * 5 4', 1614000000, 0, 1620248400],
      // Tests crontab expression is invalid.
      ['2/* * * * *', 1614000000, 0, 0],
      ['* * * * * 22', 1614000000, 0, 0],
      ['* * * * 50', 1614000000, 0, 0],
      ['* * 50/* * *', 1614000000, 1613000000, 1613000000],
    ];
  }

  /**
   * Resets the testing state.
   */
  protected function resetTestingState(): void {
    $this->state->set('state.message_logged', FALSE);
    $this->state->delete('simple_cron.state.' . static::JOB_LABEL);
  }

  /**
   * Reset job mock.
   *
   * @param bool|null $locked
   *   (optional) Is lock enabled. Defaults to NULL.
   */
  protected function resetJobMock(?bool $locked = NULL): void {
    // Create a logger factory to produce the resulting logger.
    $logger_factory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $logger_factory->get(Argument::cetera())->willReturn($this->getLoggerMock($locked));
    $this->container->set('logger.factory', $logger_factory->reveal());

    // Create a lock that will always fail when attempting to acquire; we're
    // only interested in testing ::processQueues(), not the other stuff.
    $lock_backend = $this->prophesize(LockBackendInterface::class);
    $lock_backend->acquire('simple_cron:' . static::JOB_ID, Argument::cetera())->willReturn(!$locked);
    $lock_backend->release('simple_cron:' . static::JOB_ID)->willReturn(new \stdClass());
    $this->container->set('lock', $lock_backend->reveal());

    $this->job = $this->getMockBuilder(CronJob::class)
      ->setMethods([
        'id',
        'status',
        'getNextRunTime',
        'getCronTab',
        'getPlugin',
      ])
      ->setConstructorArgs([[], 'simple_cron_job'])
      ->getMock();

    $this->job->method('id')->willReturn(static::JOB_ID);
  }

  /**
   * Reset simple job mock.
   */
  protected function resetSimpleJobMock(): void {
    $logger_factory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $channel = $this->prophesize(LoggerChannelInterface::class);
    $logger_factory->get(Argument::cetera())->willReturn($channel->reveal());
    $this->container->set('logger.factory', $logger_factory->reveal());

    $this->job = $this->getMockBuilder(CronJob::class)
      ->setMethods([
        'id',
        'status',
        'getNextRunTime',
        'getCronTab',
        'getState',
        'setState',
        'getPlugin',
      ])
      ->disableOriginalConstructor()
      ->getMock();
  }

  /**
   * Get config override mock.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config override mock.
   */
  protected function getConfigFactoryMock(): ConfigFactoryInterface {
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);

    $simple_cron_config = $this->prophesize(ImmutableConfig::class);
    $simple_cron_config->get('base.lock_timeout')->willReturn(900);
    $config_factory->get(SimpleCronSettingsForm::SETTINGS_NAME)->willReturn($simple_cron_config->reveal());

    $system_cron_config = $this->prophesize(ImmutableConfig::class);
    $system_cron_config->get('logging')->willReturn(TRUE);
    $config_factory->get('system.cron')->willReturn($system_cron_config->reveal());

    return $config_factory->reveal();
  }

  /**
   * Get logger mock.
   *
   * @param bool|null $locked
   *   Is lock enabled.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   The logger mock.
   */
  protected function getLoggerMock(?bool $locked): LoggerChannelInterface {
    // Create a mock logger to set a flag in the resulting state.
    $logger = $this->prophesize(LoggerChannelInterface::class);
    // Safely ignore the cron re-run message when failing to acquire a lock.
    //
    // We don't need to run regular cron tasks, and we're still implicitly
    // testing that queues are being processed.
    //
    // This argument will need to be updated to match the message text in
    // Drupal\Core\Cron::run() should the original text ever be updated.
    if (isset($locked)) {
      if ($locked) {
        $logger->warning('Attempting to re-run @job cron while it is already running.', ['@job' => static::JOB_LABEL])->shouldBeCalled();
      }
      else {
        $logger->warning('Attempting to re-run @job cron while it is already running.', ['@job' => static::JOB_LABEL])->shouldNotBeCalled();
      }
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
   * Get drupal date time mock.
   *
   * @param int $timestamp
   *   The timestamp.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   The date time mock.
   */
  protected function getDrupalDateTimeMock(int $timestamp): DrupalDateTime {
    $date_time = $this->prophesize(DrupalDateTime::class);
    $date_time->getTimestamp()->willReturn($timestamp);

    return $date_time->reveal();
  }

}
