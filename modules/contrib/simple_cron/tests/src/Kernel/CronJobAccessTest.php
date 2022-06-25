<?php

namespace Drupal\Tests\simple_cron\Kernel;

use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_cron\Entity\CronJob;
use Drupal\user\Entity\User;

/**
 * Tests the cron access job.
 *
 * @group simple_cron
 */
class CronJobAccessTest extends KernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['simple_cron'];

  /**
   * The cron job enabled.
   *
   * @var \Drupal\simple_cron\Entity\CronJobInterface
   */
  protected $jobEnabled;

  /**
   * The cron job disabled.
   *
   * @var \Drupal\simple_cron\Entity\CronJobInterface
   */
  protected $jobDisabled;

  /**
   * The cron job locked.
   *
   * @var \Drupal\simple_cron\Entity\CronJobInterface
   */
  protected $jobLocked;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->jobEnabled = CronJob::create(['status' => TRUE]);
    $this->jobDisabled = CronJob::create(['status' => FALSE]);

    $lock_mock = $this->createMock(DatabaseLockBackend::class);
    $lock_mock->method('lockMayBeAvailable')->willReturn(FALSE);
    $this->container->set('lock', $lock_mock);
    $this->jobLocked = CronJob::create(['status' => TRUE]);
  }

  /**
   * Tests cron job access for admin permission.
   */
  public function testCronJobAccessAdminPermission(): void {
    $user = $this->createMock(User::class);
    $user->method('hasPermission')->willReturnCallback(static function ($permission) {
      return $permission === 'administer simple cron';
    });

    // Cron job is enabled.
    $this->assertTrue($this->jobEnabled->access('update', $user), 'Update action is accessible.');
    $this->assertFalse($this->jobEnabled->access('delete', $user), 'Delete action is accessible.');
    $this->assertFalse($this->jobEnabled->access('unlock', $user), 'Unlock action is not accessible when is not locked.');
    $this->assertTrue($this->jobEnabled->access('run', $user), 'Run action is accessible.');
    $this->assertTrue($this->jobEnabled->access('disable', $user), 'Disable action is accessible.');
    $this->assertFalse($this->jobEnabled->access('enable', $user), 'Enable action is not accessible.');

    // Cron job is disabled.
    $this->assertFalse($this->jobDisabled->access('run', $user), 'Run action is not accessible when cron job is disabled.');
    $this->assertFalse($this->jobDisabled->access('disable', $user), 'Disable action is not accessible.');
    $this->assertTrue($this->jobDisabled->access('enable', $user), 'Enable action is accessible.');

    // Cron job is locked.
    $this->assertFalse($this->jobLocked->access('run', $user), 'Run action is not accessible when cron job is locked.');
    $this->assertTrue($this->jobLocked->access('unlock', $user), 'Run action is accessible when cron job is locked.');
  }

  /**
   * Tests cron job access for view permission.
   */
  public function testCronJobAccessViewPermission(): void {
    $user = $this->createMock(User::class);
    $user->method('hasPermission')->willReturnCallback(static function ($permission) {
      return $permission === 'view simple cron jobs';
    });

    // Cron job is enabled.
    $this->assertFalse($this->jobEnabled->access('update', $user), 'Update action is not accessible');
    $this->assertFalse($this->jobEnabled->access('delete', $user), 'Delete action is not accessible.');
    $this->assertFalse($this->jobEnabled->access('run', $user), 'Run action is not accessible.');
    $this->assertFalse($this->jobEnabled->access('unlock', $user), 'Unlock action is not accessible.');
    $this->assertFalse($this->jobEnabled->access('disable', $user), 'Disable action is not accessible.');
    $this->assertFalse($this->jobEnabled->access('enable', $user), 'Enable action is not accessible.');

    // Cron job is disabled.
    $this->assertFalse($this->jobDisabled->access('run', $user), 'Run action is not accessible when cron job is disabled.');
    $this->assertFalse($this->jobDisabled->access('disable', $user), 'Disable action is not accessible.');
    $this->assertFalse($this->jobDisabled->access('enable', $user), 'Enable action is not accessible.');

    // Cron job is locked.
    $this->assertFalse($this->jobLocked->access('unlock', $user), 'Unlock action is not accessible when cron job is locked.');
    $this->assertFalse($this->jobLocked->access('run', $user), 'Run action is not accessible when cron job is locked.');
  }

  /**
   * Tests cron job access for run permission.
   */
  public function testCronJobAccessRunPermission(): void {
    $user = $this->createMock(User::class);
    $user->method('hasPermission')->willReturnCallback(static function ($permission) {
      return $permission === 'run simple cron jobs';
    });

    // Cron job is enabled.
    $this->assertFalse($this->jobEnabled->access('update', $user), 'Update action is not accessible.');
    $this->assertFalse($this->jobEnabled->access('delete', $user), 'Delete action is not accessible.');
    $this->assertTrue($this->jobEnabled->access('run', $user), 'Run action is accessible.');
    $this->assertFalse($this->jobEnabled->access('unlock', $user), 'Unlock action is not accessible.');
    $this->assertFalse($this->jobEnabled->access('disable', $user), 'Disable action is not accessible.');
    $this->assertFalse($this->jobEnabled->access('enable', $user), 'Enable action is not accessible.');

    // Cron job is disabled.
    $this->assertFalse($this->jobDisabled->access('run', $user), 'Run action is not accessible when cron job is disabled.');
    $this->assertFalse($this->jobDisabled->access('disable', $user), 'Disable action is not accessible.');
    $this->assertFalse($this->jobDisabled->access('enable', $user), 'Enable action is not accessible.');

    // Cron job is locked.
    $this->assertFalse($this->jobLocked->access('unlock', $user), 'Unlock action is not accessible when cron job is locked.');
    $this->assertFalse($this->jobLocked->access('run', $user), 'Run action is not accessible when cron job is locked.');
  }

}
