<?php

namespace Drupal\Tests\simple_cron\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_cron\Entity\CronJob;

/**
 * Tests the cron job.
 *
 * @group simple_cron
 */
class CronJobTest extends KernelTestBase {

  protected const REQUEST_TIME = 1615109176;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['simple_cron', 'simple_cron_test'];

  /**
   * Tests cron job state.
   */
  public function testCronJobState(): void {
    $this->installConfig(['simple_cron_test']);
    $job = CronJob::load('test_cron_job');

    // Cron job state on first load.
    $this->assertEmpty($job->getLastRunTime(), 'The last run time is no set.');
    $this->assertNotEmpty($job->getNextRunTime()->getTimestamp(), 'The next run time is set.');

    // Last run time is set.
    $this->container->get('state')->set('simple_cron.state.test_cron_job', [
      'last_run' => static::REQUEST_TIME,
    ]);
    $this->assertNotEmpty($job->getLastRunTime(), 'The last run time is set.');

    // State is removed when cron job is deleted.
    $job->delete();
    $this->assertNull(
      $this->container->get('state')->get('simple_cron.state.test_cron_job'),
      'State is deleted after cron job delete'
    );
  }

  /**
   * Tests cron job run.
   */
  public function testCronJobRun(): void {
    $this->installConfig(['simple_cron_test']);
    $job = CronJob::load('test_cron_job');

    // Cron job last run time on first load.
    $this->assertEmpty($job->getLastRunTime(), 'The last run time is no set.');

    // Last run time is not set when cron fails.
    $job->set('configuration', ['error_enabled' => TRUE]);
    $job->run(static::REQUEST_TIME, TRUE);
    $this->assertEmpty($job->getLastRunTime(), 'The last run time is no set when cron fails.');

    // Last run time is set.
    $job->set('configuration', ['error_enabled' => FALSE]);
    $job->run(static::REQUEST_TIME, TRUE);
    $this->assertNotEmpty($job->getLastRunTime(), 'The last run time is set when cron is run successfully.');
  }

}
