<?php

namespace Drupal\Tests\simple_cron\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simple_cron\Entity\CronJob;
use Drupal\simple_cron\Form\SimpleCronSettingsForm;

/**
 * Cron Job routes Testing.
 *
 * @group simple_cron
 */
class CronJobRoutesTest extends SimpleCronBrowserTestBase {

  use StringTranslationTrait;

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

    // Disable cron and queue override.
    $this->container->get('config.factory')
      ->getEditable(SimpleCronSettingsForm::SETTINGS_NAME)
      ->set('cron.override_enabled', FALSE)
      ->set('queue.override_enabled', FALSE)
      ->save();

    // Update list.
    $this->container->get('simple_cron.cron_job_manager')->updateList();
  }

  /**
   * Tests cron job routes accessibility.
   *
   * @param array $permissions
   *   The user permissions.
   * @param bool $status
   *   The cron status.
   * @param bool $locked
   *   Is cron locked.
   * @param array $status_codes
   *   The routes status codes.
   *
   * @dataProvider providerTestJobRoutes
   *
   * @throws \Exception
   */
  public function testJobRoutes(array $permissions, bool $status, bool $locked, array $status_codes): void {
    $user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($user);

    CronJob::load('test_cron_job')
      ->setStatus($status)
      ->save();

    $this->updateLockStatus('test_cron_job', $locked);

    // Cron job list page accessibility.
    $this->drupalGet('admin/config/system/cron/jobs');
    $this->assertSession()->statusCodeEquals($status_codes[0]);

    // Cron job edit action page accessibility.
    $this->drupalGet('admin/config/system/cron/jobs/test_cron_job/edit');
    $this->assertSession()->statusCodeEquals($status_codes[1]);

    // Cron job run action page accessibility.
    $this->drupalGet('admin/config/system/cron/jobs/test_cron_job/run');
    $this->assertSession()->statusCodeEquals($status_codes[2]);

    // Cron job disable action page accessibility.
    $this->drupalGet('admin/config/system/cron/jobs/test_cron_job/disable');
    $this->assertSession()->statusCodeEquals($status_codes[3]);

    // Cron job enable action page accessibility.
    $this->drupalGet('admin/config/system/cron/jobs/test_cron_job/enable');
    $this->assertSession()->statusCodeEquals($status_codes[4]);

    // Cron job unlock action page accessibility.
    $this->drupalGet('admin/config/system/cron/jobs/test_cron_job/unlock');
    $this->assertSession()->statusCodeEquals($status_codes[5]);
  }

  /**
   * Data provider for ::testJobRoutes() method.
   *
   * @return array
   *   The data of job routes test.
   */
  public function providerTestJobRoutes(): array {
    return [
      [['administer simple cron'], FALSE, FALSE, [200, 200, 403, 403, 200, 403]],
      [['administer simple cron'], FALSE, TRUE, [200, 200, 403, 403, 200, 200]],
      [['administer simple cron'], TRUE, FALSE, [200, 200, 200, 200, 403, 403]],
      [['administer simple cron'], TRUE, TRUE, [200, 200, 403, 200, 403, 200]],
      [['run simple cron jobs'], FALSE, FALSE, [200, 403, 403, 403, 403, 403]],
      [['run simple cron jobs'], FALSE, TRUE, [200, 403, 403, 403, 403, 403]],
      [['run simple cron jobs'], TRUE, FALSE, [200, 403, 200, 403, 403, 403]],
      [['run simple cron jobs'], TRUE, TRUE, [200, 403, 403, 403, 403, 403]],
      [['view simple cron jobs'], FALSE, FALSE, [200, 403, 403, 403, 403, 403]],
      [['view simple cron jobs'], FALSE, TRUE, [200, 403, 403, 403, 403, 403]],
      [['view simple cron jobs'], TRUE, FALSE, [200, 403, 403, 403, 403, 403]],
      [['view simple cron jobs'], TRUE, TRUE, [200, 403, 403, 403, 403, 403]],
    ];
  }

}
