<?php

namespace Drupal\Tests\simple_cron\Kernel;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_cron\Entity\CronJobInterface;
use Drupal\simple_cron\Plugin\SimpleCron\Cron;

/**
 * Tests the storage of cron jobs.
 *
 * @group simple_cron
 */
class CronJobStorageTest extends KernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['simple_cron', 'simple_cron_test'];

  /**
   * The cron job storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->storage = $this->container->get('entity_type.manager')
      ->getStorage('simple_cron_job');
  }

  /**
   * Tests cron job storage.
   */
  public function testCronJobStorage(): void {
    $this->assertInstanceOf(ConfigEntityStorage::class, $this->storage);

    // Run each test method in the same installation.
    $this->createTests();
    $this->loadTests();
    $this->deleteTests();
  }

  /**
   * Tests the creation of cron jobs.
   */
  protected function createTests(): void {
    // Attempt to create a cron job without a plugin.
    /** @var \Drupal\simple_cron\Entity\CronJobInterface $entity */
    $entity = $this->storage->create();
    $plugin = $entity->getPlugin();
    $this->assertEquals(NULL, $plugin, 'The plugin is null when a cron job was created without a plugin.');

    // Create a cron job with required values.
    $id = 'simple_cron_test';
    $entity = $this->storage->create([
      'id' => $id,
      'crontab' => '*/15 * * * *',
      'plugin' => 'cron',
      'type' => 'simple_cron',
      'provider' => 'simple_cron',
    ]);
    $entity->save();

    $this->assertInstanceOf(CronJobInterface::class, $entity, 'Cron job entity is created.');

    // Verify all of the simple cron properties.
    $actual_properties = $this->config("simple_cron.job.$id")->get();
    $this->assertTrue(isset($actual_properties['uuid']), 'The cron job UUID is set.');
    unset($actual_properties['uuid']);

    // Ensure that default values are filled in.
    $expected_properties = [
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => $id,
      'crontab' => '*/15 * * * *',
      'plugin' => 'cron',
      'type' => 'simple_cron',
      'provider' => 'simple_cron',
      'single' => FALSE,
      'configuration' => [],
      'weight' => 0,
    ];

    $this->assertSame($expected_properties, $actual_properties, 'Cron job entity fields data is valid.');
    $this->assertInstanceOf(Cron::class, $entity->getPlugin(), 'Cron job plugin interface is valid.');
  }

  /**
   * Tests the loading of cron job.
   */
  protected function loadTests(): void {
    /** @var \Drupal\simple_cron\Entity\CronJobInterface $entity */
    $entity = $this->storage->load('simple_cron_test');

    $this->assertInstanceOf(CronJobInterface::class, $entity);

    // Verify several properties of the cron job.
    $this->assertSame('*/15 * * * *', $entity->getCrontab(), 'The crontab is valid.');
    $this->assertTrue($entity->status(), 'The status is valid.');
    $this->assertFalse($entity->isSingle(), 'The single URL status is valid.');
    $this->assertEquals('simple_cron', $entity->getType(), 'The type is valid.');
    $this->assertEquals('simple_cron', $entity->getProvider(), 'The provider is valid.');
    $this->assertEquals('Simple Cron', $entity->getProviderName(), 'The provider name is valid.');
    $this->assertNotEmpty($entity->uuid(), 'The UUID is set.');
    $this->assertEquals('simple_cron_test', $entity->id(), 'The entity id is valid.');
    $this->assertEquals(0, $entity->getWeight(), 'The weight is valid.');
  }

  /**
   * Tests the deleting of cron job.
   */
  protected function deleteTests(): void {
    $entity = $this->storage->load('simple_cron_test');

    // Ensure that the storage isn't currently empty.
    $config_storage = $this->container->get('config.storage');
    $config = $config_storage->listAll('simple_cron.job.');
    $this->assertNotEmpty($config, 'There are cron job in config storage.');

    // Delete the cron job.
    $entity->delete();

    // Ensure that the storage is now empty.
    $config = $config_storage->listAll('simple_cron.job.');
    $this->assertEmpty($config, 'There are no cron jobs in config storage.');
  }

  /**
   * Tests the installation of default cron jobs.
   */
  public function testDefaultCronJob(): void {
    $entities = $this->storage->loadMultiple();
    $this->assertEmpty($entities, 'There are no cron jobs initially.');

    // Install the simple_cron_test.module
    // so that its default config is installed.
    $this->installConfig(['simple_cron_test']);
    $entities = $this->storage->loadMultiple();

    $this->assertNotEmpty($entities, 'The default test cron job was loaded.');
  }

}
