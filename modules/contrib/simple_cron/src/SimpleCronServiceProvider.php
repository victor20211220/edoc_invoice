<?php

namespace Drupal\simple_cron;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Defines service provider for simple cron.
 */
class SimpleCronServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $container->getDefinition('cron')
      ->setClass(SimpleCron::class)
      ->addMethodCall('setCronJobManager', [new Reference('simple_cron.cron_job_manager')])
      ->addMethodCall('setRequestStack', [new Reference('request_stack')])
      ->addMethodCall('setConfigFactory', [new Reference('config.factory')]);
  }

}
