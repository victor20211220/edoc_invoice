<?php

namespace Drupal\simple_cron\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\simple_cron\Entity\CronJobInterface;

/**
 * Defines an interface for SimpleCron plugins.
 */
interface SimpleCronPluginInterface extends PluginInspectionInterface, ConfigurableInterface, PluginFormInterface {

  /**
   * Get plugin id.
   *
   * @return string
   *   The plugin id.
   */
  public function id(): string;

  /**
   * Get plugin label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The plugin label.
   */
  public function label();

  /**
   * Get type definitions.
   *
   * @return array
   *   Type definitions.
   */
  public function getTypeDefinitions(): array;

  /**
   * Get type.
   *
   * @return string
   *   Cron job type.
   */
  public function getType(): string;

  /**
   * Process cron job.
   */
  public function process(): void;

  /**
   * Get cron job.
   *
   * @return \Drupal\simple_cron\Entity\CronJobInterface
   *   Cron job.
   */
  public function getCronJob(): CronJobInterface;

  /**
   * Set cron job.
   *
   * @param \Drupal\simple_cron\Entity\CronJobInterface $cron_job
   *   The cron job.
   *
   * @return \Drupal\simple_cron\Plugin\SimpleCronPluginInterface
   *   The current simple cron plugin.
   */
  public function setCronJob(CronJobInterface $cron_job): SimpleCronPluginInterface;

}
