<?php

namespace Drupal\simple_cron\Plugin;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Interface of the SimpleCron plugin manager.
 */
interface SimpleCronPluginManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface {

  /**
   * Get plugins.
   *
   * @return \Drupal\simple_cron\Plugin\SimpleCronPluginInterface[]
   *   An array of plugins. Keys are plugin IDs.
   */
  public function getPlugins(): array;

  /**
   * Get plugin.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param array $configuration
   *   (Optional) The plugin configuration. Defaults to [].
   *
   * @return \Drupal\simple_cron\Plugin\SimpleCronPluginInterface|null
   *   The simple cron plugin.
   */
  public function getPlugin(string $plugin_id, array $configuration = []): ?SimpleCronPluginInterface;

}
