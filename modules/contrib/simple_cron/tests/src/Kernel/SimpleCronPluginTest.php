<?php

namespace Drupal\Tests\simple_cron\Kernel;

use Drupal\simple_cron\Form\SimpleCronSettingsForm;
use Drupal\simple_cron\Plugin\SimpleCron\Cron;
use Drupal\simple_cron\Plugin\SimpleCron\Queue;
use Drupal\simple_cron\Plugin\SimpleCronPluginInterface;
use Drupal\simple_cron_test\Plugin\SimpleCron\TestCronJob;
use Drupal\simple_cron_test\Plugin\SimpleCron\TestCronJobMultipleTypes;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the simple cron plugin.
 *
 * @group simple_cron
 */
class SimpleCronPluginTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['simple_cron'];

  /**
   * The simple cron plugin manager.
   *
   * @var \Drupal\simple_cron\Plugin\SimpleCronPluginManagerInterface
   */
  protected $simpleCronPluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->simpleCronPluginManager = $this->container->get('plugin.manager.simple_cron');
  }

  /**
   * Tests the default simple cron plugins.
   */
  public function testSimpleCronDefaultPlugins(): void {
    // All default plugins exists.
    $plugins = $this->simpleCronPluginManager->getPlugins();
    $this->assertSame(['cron', 'queue'], array_keys($plugins), 'All default plugins exists.');

    // Load cron plugin.
    $plugin = $this->simpleCronPluginManager->getPlugin('cron');
    $this->assertInstanceOf(SimpleCronPluginInterface::class, $plugin, 'Cron plugin interface is valid.');
    $this->assertInstanceOf(Cron::class, $plugin, 'Cron plugin class is valid.');

    // Load queue plugin.
    $plugin = $this->simpleCronPluginManager->getPlugin('queue');
    $this->assertInstanceOf(SimpleCronPluginInterface::class, $plugin, 'Queue plugin interface is valid.');
    $this->assertInstanceOf(Queue::class, $plugin, 'Queue plugin class is valid.');
  }

  /**
   * Tests the other module simple cron plugin.
   */
  public function testSimpleCronNewPlugin(): void {
    $this->enableModules(['simple_cron_test']);
    $this->simpleCronPluginManager = $this->container->get('plugin.manager.simple_cron');

    // New module plugins.
    $plugins = $this->simpleCronPluginManager->getPlugins();
    $this->assertSame([
      'test_cron_job',
      'cron',
      'test_cron_job_multi_types',
      'queue',
    ], array_keys($plugins), 'New enabled module plugins exists.');

    // Load test_cron_job plugin.
    $plugin = $this->simpleCronPluginManager->getPlugin('test_cron_job');
    $this->assertInstanceOf(SimpleCronPluginInterface::class, $plugin, 'Test cron job plugin interface is valid.');
    $this->assertInstanceOf(TestCronJob::class, $plugin, 'Test cron job plugin class is valid.');

    // Load test_cron_job_multi_types plugin.
    $plugin = $this->simpleCronPluginManager->getPlugin('test_cron_job_multi_types');
    $this->assertInstanceOf(SimpleCronPluginInterface::class, $plugin, 'Test cron job multi types plugin interface is valid.');
    $this->assertInstanceOf(TestCronJobMultipleTypes::class, $plugin, 'Test cron job multi types plugin class is valid.');
  }

  /**
   * Tests the plugin types.
   */
  public function testSimpleCronPluginTypes(): void {
    $this->enableModules(['simple_cron_test']);
    $this->simpleCronPluginManager = $this->container->get('plugin.manager.simple_cron');

    // Cron plugin type definition disabled.
    $this->config(SimpleCronSettingsForm::SETTINGS_NAME)->set('cron.override_enabled', FALSE)->save();
    $plugin = $this->simpleCronPluginManager->getPlugin('cron');
    $this->assertEmpty($plugin->getTypeDefinitions(), 'Cron plugin type disabling works.');

    // Cron plugin type definition enabled.
    $this->config(SimpleCronSettingsForm::SETTINGS_NAME)->set('cron.override_enabled', TRUE)->save();
    $plugin = $this->simpleCronPluginManager->getPlugin('cron');
    $this->assertSame(['simple_cron_test'], array_keys($plugin->getTypeDefinitions()), 'Cron plugin type enabling works.');

    // Queue plugin type definition disabled.
    $this->config(SimpleCronSettingsForm::SETTINGS_NAME)->set('queue.override_enabled', FALSE)->save();
    $plugin = $this->simpleCronPluginManager->getPlugin('queue');
    $this->assertEmpty($plugin->getTypeDefinitions(), 'Queue plugin type disabling works.');

    // Queue plugin type definition enabled.
    $this->config(SimpleCronSettingsForm::SETTINGS_NAME)->set('queue.override_enabled', TRUE)->save();
    $plugin = $this->simpleCronPluginManager->getPlugin('queue');
    $this->assertSame(['simple_cron_queue_test'], array_keys($plugin->getTypeDefinitions()), 'Queue plugin type enabling works.');

    // Other module plugin default type definition.
    $plugin = $this->simpleCronPluginManager->getPlugin('test_cron_job');
    $this->assertSame(['default'], array_keys($plugin->getTypeDefinitions()), 'Default plugin type exists.');

    // Other module plugin multiple type definitions.
    $plugin = $this->simpleCronPluginManager->getPlugin('test_cron_job_multi_types');
    $this->assertSame(['type1', 'type2'], array_keys($plugin->getTypeDefinitions()), 'Plugin types definitions works.');
  }

}
