<?php

namespace Drupal\Tests\simple_cron\Kernel;

use Drupal\simple_cron\Entity\CronJob;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the cron job config schema.
 *
 * @group simple_cron
 */
class CronJobConfigSchemaTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['simple_cron'];

  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfig;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->typedConfig = $this->container->get('config.typed');
  }

  /**
   * Tests the cron job config schema for simple cron plugins.
   */
  public function testCronJobConfigSchema(): void {
    $id = 'simple_cron_test';

    $job = CronJob::create([
      'id' => $id,
      'crontab' => '*/15 * * * *',
      'plugin' => 'cron',
      'type' => 'simple_cron',
      'provider' => 'simple_cron',
    ]);
    $job->save();

    $config = $this->config("simple_cron.job.$id");
    $this->assertEquals($id, $config->get('id'), 'Cron job config is saved.');
    $this->assertConfigSchema($this->typedConfig, $config->getName(), $config->get());
  }

}
