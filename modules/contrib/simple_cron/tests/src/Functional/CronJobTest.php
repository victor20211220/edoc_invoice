<?php

namespace Drupal\Tests\simple_cron\Functional;

use Drupal\simple_cron\Entity\CronJob;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Cron Job Testing.
 *
 * @group simple_cron
 */
class CronJobTest extends SimpleCronBrowserTestBase {

  use CronRunTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['simple_cron', 'simple_cron_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->drupalCreateUser(['administer simple cron']);
    $this->drupalLogin($user);
  }

  /**
   * Test cron job status change.
   */
  public function testStatusChange(): void {
    $this->drupalGet('admin/config/system/cron/jobs');

    // Default cron job status.
    $this->assertSession()->linkByHrefExists('admin/config/system/cron/jobs/test_cron_job/disable');
    $this->assertSession()->linkByHrefNotExists('admin/config/system/cron/jobs/test_cron_job/enable');
    $this->assertTableElementExists(1, 1, 'Test cron job');
    $this->assertTableElementExists(1, 7, 'Enabled');

    // Disable cron job.
    $this->drupalGet('admin/config/system/cron/jobs/test_cron_job/disable');
    $this->submitForm([], 'Disable');

    $this->assertSession()->responseContains('Disabled the <em class="placeholder">Test cron job</em> Cron job.');
    $this->assertSession()->linkByHrefNotExists('admin/config/system/cron/jobs/test_cron_job/disable');
    $this->assertSession()->linkByHrefExists('admin/config/system/cron/jobs/test_cron_job/enable');
    $this->assertTableElementExists(1, 1, 'Test cron job');
    $this->assertTableElementExists(1, 7, 'Disabled');

    // Enable cron job.
    $this->drupalGet('admin/config/system/cron/jobs/test_cron_job/enable');
    $this->submitForm([], 'Enable');

    $this->assertSession()->responseContains('Enabled the <em class="placeholder">Test cron job</em> Cron job.');
    $this->assertSession()->linkByHrefExists('admin/config/system/cron/jobs/test_cron_job/disable');
    $this->assertSession()->linkByHrefNotExists('admin/config/system/cron/jobs/test_cron_job/enable');
    $this->assertTableElementExists(1, 1, 'Test cron job');
    $this->assertTableElementExists(1, 7, 'Enabled');
  }

  /**
   * Test cron job status change.
   */
  public function testUnlock(): void {
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertTableElementExists(1, 1, 'Test cron job');
    $this->assertTableElementExists(1, 7, 'Enabled');
    $this->assertSession()->linkByHrefNotExists('admin/config/system/cron/jobs/test_cron_job/unlock');

    // Lock cron job.
    $this->updateLockStatus('test_cron_job', TRUE);

    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertTableElementExists(1, 1, 'Test cron job');
    $this->assertTableElementExists(1, 7, 'Locked');
    $this->assertSession()->linkByHrefExists('admin/config/system/cron/jobs/test_cron_job/unlock');

    // Unlock cron job.
    $this->drupalGet('admin/config/system/cron/jobs/test_cron_job/unlock');
    $this->submitForm([], 'Unlock');

    $this->assertSession()->responseContains('Unlocked the <em class="placeholder">Test cron job</em> Cron job.');
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertTableElementExists(1, 1, 'Test cron job');
    $this->assertTableElementExists(1, 7, 'Enabled');
    $this->assertSession()->linkByHrefNotExists('admin/config/system/cron/jobs/test_cron_job/unlock');
  }

  /**
   * Test cron job run.
   */
  public function testRun(): void {
    // Cron run should run.
    $this->resetCronJobState('test_cron_job', time() - 600);
    $this->assertCronNotRun();
    $this->cronRun();
    $this->assertCronRun();

    // Single cron run should run.
    $this->resetCronJobState('test_cron_job', time() + 600);
    $this->assertCronNotRun();
    $this->singleCronJobRun('test_cron_job', TRUE);
    $this->assertCronRun();

    // Manual cron job run.
    $this->resetCronJobState('test_cron_job', time() + 600);
    $this->assertCronNotRun();
    $this->drupalGet('admin/config/system/cron/jobs/test_cron_job/run');
    $this->assertCronRun();

    // Enable single cron job run.
    CronJob::load('test_cron_job')->set('single', TRUE)->save();

    // Single cron should not run with default cron run.
    $this->resetCronJobState('test_cron_job', time() - 600);
    $this->assertCronNotRun();
    $this->cronRun();
    $this->assertCronNotRun();

    // Single cron should not run.
    $this->resetCronJobState('test_cron_job', time() + 600);
    $this->assertCronNotRun();
    $this->singleCronJobRun('test_cron_job');
    $this->assertCronNotRun();

    // Single cron should run.
    $this->resetCronJobState('test_cron_job', time() - 600);
    $this->assertCronNotRun();
    $this->singleCronJobRun('test_cron_job');
    $this->assertCronRun();

    // Single cron should run by forcing.
    $this->resetCronJobState('test_cron_job', time() + 600);
    $this->assertCronNotRun();
    $this->singleCronJobRun('test_cron_job', TRUE);
    $this->assertCronRun();

    // Manual cron job run.
    $this->resetCronJobState('test_cron_job', time() + 600);
    $this->assertCronNotRun();
    $this->drupalGet('admin/config/system/cron/jobs/test_cron_job/run');
    $this->assertCronRun();
  }

  /**
   * Reset cron job state.
   *
   * @param string $job_id
   *   The cron job id.
   * @param int $timestamp
   *   The timestamp.
   */
  protected function resetCronJobState(string $job_id, $timestamp): void {
    $this->container->get('state')->set('simple_cron.state.' . $job_id, [
      'next_run' => $timestamp,
      'last_run' => NULL,
    ]);
  }

  /**
   * Assert cron not run.
   *
   * @param int $row
   *   The row.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertCronNotRun(int $row = 1): void {
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertTableElementExists($row, 5, 'Never');
  }

  /**
   * Assert cron run.
   *
   * @param int $row
   *   The row.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertCronRun(int $row = 1): void {
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertTableElementExists($row, 5, 'Never', FALSE);
  }

}
