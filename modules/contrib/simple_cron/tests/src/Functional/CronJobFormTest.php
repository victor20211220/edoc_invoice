<?php

namespace Drupal\Tests\simple_cron\Functional;

use Drupal\simple_cron\Form\SimpleCronSettingsForm;

/**
 * Cron Job form Testing.
 *
 * @group simple_cron
 */
class CronJobFormTest extends SimpleCronBrowserTestBase {

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

    // Enable cron and queue override.
    $this->container->get('config.factory')
      ->getEditable(SimpleCronSettingsForm::SETTINGS_NAME)
      ->set('cron.override_enabled', TRUE)
      ->set('queue.override_enabled', TRUE)
      ->save();

    // Update list.
    $this->container->get('simple_cron.cron_job_manager')->updateList();

    // Login user.
    $user = $this->drupalCreateUser(['administer simple cron']);
    $this->drupalLogin($user);
  }

  /**
   * Test cron job form fields.
   */
  public function testJobFormFields(): void {
    // Cron cron job form fields exists.
    $this->drupalGet('admin/config/system/cron/jobs/cron.simple_cron_test/edit');
    $this->assertSession()->fieldExists('status');
    $this->assertSession()->fieldExists('crontab');
    $this->assertSession()->fieldExists('single');
    $this->assertSession()->responseNotContains('Configuration');
    $this->assertSession()->buttonExists('Save');

    // Queue cron job form fields exists.
    $this->drupalGet('admin/config/system/cron/jobs/queue.simple_cron_queue_test/edit');
    $this->assertSession()->fieldExists('status');
    $this->assertSession()->fieldExists('crontab');
    $this->assertSession()->fieldExists('single');
    $this->assertSession()->responseContains('Configuration');
    $this->assertSession()->fieldExists('configuration[time]');
    $this->assertSession()->buttonExists('Save');

    // Custom cron job form fields exists.
    $this->drupalGet('admin/config/system/cron/jobs/test_cron_job/edit');
    $this->assertSession()->fieldExists('status');
    $this->assertSession()->fieldExists('crontab');
    $this->assertSession()->fieldExists('single');
    $this->assertSession()->responseContains('Configuration');
    $this->assertSession()->fieldExists('configuration[error_enabled]');
    $this->assertSession()->buttonExists('Save');
  }

  /**
   * Test cron job form save.
   */
  public function testJobFormSave(): void {
    $this->drupalGet('admin/config/system/cron/jobs');

    // Default cron job settings.
    $this->assertTableElementExists(1, 1, 'Test cron job');
    $this->assertTableElementExists(1, 3, '*/15 * * * *');
    $this->assertTableElementExists(1, 6, 'Default');
    $this->assertTableElementExists(1, 7, 'Enabled');

    // Submit cron job form.
    $this->drupalGet('admin/config/system/cron/jobs/test_cron_job/edit');
    $this->submitForm([
      'status' => FALSE,
      'single' => TRUE,
      'crontab' => '*/2 * * * *',
    ], 'Save');

    $this->assertSession()->responseContains('Saved the Test cron job Cron job.');
    $this->assertTableElementExists(1, 1, 'Test cron job');
    $this->assertTableElementExists(1, 3, '*/2 * * * *');
    $this->assertTableElementExists(1, 6, 'Single URL');
    $this->assertTableElementExists(1, 7, 'Disabled');
  }

}
