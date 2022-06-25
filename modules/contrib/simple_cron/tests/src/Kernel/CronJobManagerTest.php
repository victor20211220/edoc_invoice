<?php

namespace Drupal\Tests\simple_cron\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_cron\Entity\CronJob;
use Drupal\simple_cron\Form\SimpleCronSettingsForm;

/**
 * Tests the manager of cron jobs.
 *
 * @group simple_cron
 */
class CronJobManagerTest extends KernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['simple_cron', 'simple_cron_test'];

  /**
   * The cron job manager.
   *
   * @var \Drupal\simple_cron\CronJobManagerInterface
   */
  protected $cronJobManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['simple_cron']);
    $this->cronJobManager = $this->container->get('simple_cron.cron_job_manager');
    $this->cronJobManager->updateList();
  }

  /**
   * Tests cron job update list.
   */
  public function testCronJobUpdateList(): void {
    // Updated list.
    $jobs = $this->cronJobManager->getEnabledDefaultRunJobs();
    $this->assertSame([
      'test_cron_job',
      'cron.simple_cron_test',
      'test_cron_job_multi_types.type1',
      'test_cron_job_multi_types.type2',
    ], array_keys($jobs), 'Enabled cron jobs list is loaded.');

    // New cron jobs update.
    $this->config(SimpleCronSettingsForm::SETTINGS_NAME)->set('queue.override_enabled', TRUE)->save();
    $this->cronJobManager->updateList();
    $jobs = $this->cronJobManager->getEnabledDefaultRunJobs();
    $this->assertSame([
      'test_cron_job',
      'cron.simple_cron_test',
      'test_cron_job_multi_types.type1',
      'test_cron_job_multi_types.type2',
      'queue.simple_cron_queue_test',
    ], array_keys($jobs), 'Enabled cron jobs list is updated.');

    // Remove cron job.
    $this->config(SimpleCronSettingsForm::SETTINGS_NAME)->set('cron.override_enabled', FALSE)->save();
    $this->cronJobManager->updateList();
    $jobs = $this->cronJobManager->getEnabledDefaultRunJobs();
    $this->assertSame([
      'test_cron_job',
      'test_cron_job_multi_types.type1',
      'test_cron_job_multi_types.type2',
      'queue.simple_cron_queue_test',
    ], array_keys($jobs), 'Removed cron jobs list is updated.');

    // Module uninstall update.
    $this->disableModules(['simple_cron_test']);
    $this->cronJobManager = $this->container->get('simple_cron.cron_job_manager');
    $this->cronJobManager->updateList();
    $jobs = $this->cronJobManager->getEnabledDefaultRunJobs();
    $this->assertEmpty($jobs, 'There are no cron jobs when all jobs is disabled.');
  }

  /**
   * Tests cron job update list.
   */
  public function testCronJobSingeJob(): void {
    // Not existing cron job load.
    $cron_job = $this->cronJobManager->getEnabledJob('test');
    $this->assertEmpty($cron_job, 'Cron job not exists');

    // Load default cron run jobs.
    $jobs = $this->cronJobManager->getEnabledDefaultRunJobs();

    $this->assertSame([
      'test_cron_job',
      'cron.simple_cron_test',
      'test_cron_job_multi_types.type1',
      'test_cron_job_multi_types.type2',
    ], array_keys($jobs), 'Cron jobs list is correct.');

    // Single cron job load.
    $job = CronJob::load('cron.simple_cron_test');
    $job->set('single', TRUE)->save();
    $this->cronJobManager = $this->container->get('simple_cron.cron_job_manager');
    $cron_job = $this->cronJobManager->getEnabledJob('cron.simple_cron_test');
    $this->assertTrue($cron_job->isSingle(), 'Single cron job exists');

    // Load default cron run jobs list is changed.
    $jobs = $this->cronJobManager->getEnabledDefaultRunJobs();
    $this->assertSame([
      'test_cron_job',
      'test_cron_job_multi_types.type1',
      'test_cron_job_multi_types.type2',
    ], array_keys($jobs), 'Cron jobs list is without single cron job.');
  }

}
