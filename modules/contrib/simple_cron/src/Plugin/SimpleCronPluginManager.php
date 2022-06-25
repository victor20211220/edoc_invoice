<?php

namespace Drupal\simple_cron\Plugin;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Utility\Error;
use Drupal\simple_cron\Annotation\SimpleCron;

/**
 * Provides the SimpleCron plugin manager.
 */
class SimpleCronPluginManager extends DefaultPluginManager implements SimpleCronPluginManagerInterface {

  use LoggerChannelTrait;

  /**
   * Constructs a new SimpleCronPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/SimpleCron',
      $namespaces,
      $module_handler,
      SimpleCronPluginInterface::class,
      SimpleCron::class
    );

    $this->alterInfo('simple_cron_info');
    $this->setCacheBackend($cache_backend, 'simple_cron_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = parent::getDefinitions();
    uasort($definitions, 'Drupal\Component\Utility\SortArray::sortByWeightElement');

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugins(): array {
    $plugins = [];
    $definitions = $this->getDefinitions();

    foreach ($definitions as $plugin_id => $definition) {
      if ($plugin = $this->getPlugin($plugin_id)) {
        $plugins[$plugin_id] = $plugin;
      }
    }

    return $plugins;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin(string $plugin_id, array $configuration = []): ?SimpleCronPluginInterface {
    try {
      $plugin = $this->createInstance($plugin_id, $configuration);

      if ($plugin instanceof SimpleCronPluginInterface) {
        return $plugin;
      }
    }
    catch (\Exception $exception) {
      $message = '%type: @message in %function (line %line of %file).';
      $this->getLogger('simple_cron')->error($message, Error::decodeException($exception));
    }

    return NULL;
  }

}
