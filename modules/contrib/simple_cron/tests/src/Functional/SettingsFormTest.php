<?php

namespace Drupal\Tests\simple_cron\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Settings form Testing.
 *
 * @group simple_cron
 */
class SettingsFormTest extends BrowserTestBase {

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
   * Tests settings form route accessibility.
   *
   * @param string $permission
   *   The user permission.
   * @param int $status_code
   *   The status code.
   *
   * @dataProvider providerTestSettingsFormRoute
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSettingsFormRoute(string $permission, int $status_code): void {
    $user = $this->drupalCreateUser([$permission]);
    $this->drupalLogin($user);

    // Settings form page accessibility.
    $this->drupalGet('admin/config/system/cron/jobs/settings');
    $this->assertSession()->statusCodeEquals($status_code);
  }

  /**
   * Data provider for ::testJobRoutes() method.
   *
   * @return array
   *   The data of settings form route test.
   */
  public function providerTestSettingsFormRoute(): array {
    return [
      ['administer simple cron', 200],
      ['run simple cron jobs', 403],
      ['view simple cron jobs', 403],
    ];
  }

  /**
   * Tests settings form fields.
   */
  public function testSettingsFormFields(): void {
    $this->drupalGet('admin/config/system/cron/jobs/settings');

    $this->assertSession()->fieldExists('cron[override_enabled]');
    $this->assertSession()->fieldExists('queue[override_enabled]');
    $this->assertSession()->fieldExists('base[max_execution_time]');
    $this->assertSession()->fieldExists('base[lock_timeout]');
  }

  /**
   * Tests cron settings override.
   *
   * @param bool $cron_override
   *   Is cron override enabled.
   * @param string $cron_response
   *   The cron response assert method name.
   * @param bool $queue_override
   *   Is queue override enabled.
   * @param string $queue_response
   *   The queue response assert method name.
   *
   * @dataProvider providerTestCronOverride
   */
  public function testCronOverride(bool $cron_override, string $cron_response, bool $queue_override, string $queue_response): void {
    $this->drupalGet('admin/config/system/cron/jobs/settings');
    $this->submitForm([
      'cron[override_enabled]' => $cron_override,
      'queue[override_enabled]' => $queue_override,
    ], 'Save configuration');

    $this->assertSession()->{$queue_response}('Queue worker: Queue test');
    $this->assertSession()->{$cron_response}('The Simple Cron Test module cron');
  }

  /**
   * Data provider for ::testCronOverride() method.
   *
   * @return array
   *   The data of settings form route test.
   */
  public function providerTestCronOverride(): array {
    return [
      [FALSE, 'responseNotContains', FALSE, 'responseNotContains'],
      [FALSE, 'responseNotContains', TRUE, 'responseContains'],
      [TRUE, 'responseContains', FALSE, 'responseNotContains'],
      [TRUE, 'responseContains', TRUE, 'responseContains'],
    ];
  }

}
