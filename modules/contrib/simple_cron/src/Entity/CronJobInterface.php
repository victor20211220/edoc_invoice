<?php

namespace Drupal\simple_cron\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\simple_cron\Plugin\SimpleCronPluginInterface;

/**
 * Provides an interface for defining Cron job entities.
 */
interface CronJobInterface extends ConfigEntityInterface {

  /**
   * Get crontab.
   *
   * @return string
   *   Cron job crontab.
   */
  public function getCrontab(): string;

  /**
   * Get plugin.
   *
   * @return \Drupal\simple_cron\Plugin\SimpleCronPluginInterface|null
   *   Simple cron plugin.
   */
  public function getPlugin(): ?SimpleCronPluginInterface;

  /**
   * Get type.
   *
   * @return string
   *   Cron job type.
   */
  public function getType(): string;

  /**
   * Get provider.
   *
   * @return string
   *   Provider.
   */
  public function getProvider(): string;

  /**
   * Get provider name.
   *
   * @return string
   *   Provider name.
   */
  public function getProviderName(): string;

  /**
   * Is only single single run enabled.
   *
   * @return bool
   *   TRUE if only single run is enabled.
   */
  public function isSingle(): bool;

  /**
   * Get weight.
   *
   * @return int
   *   The weight of this cron job.
   */
  public function getWeight(): int;

  /**
   * Executes a cron job run.
   *
   * @param int $request_time
   *   The request time.
   * @param bool $force
   *   TRUE for force run.
   *
   * @return bool
   *   TRUE upon success, FALSE otherwise.
   */
  public function run(int $request_time, bool $force = FALSE): bool;

  /**
   * Unlock job.
   *
   * @return \Drupal\simple_cron\Entity\CronJobInterface
   *   The cron job entity.
   */
  public function unlock(): CronJobInterface;

  /**
   * Is job locked.
   *
   * @return bool
   *   TRUE when cron job is locked.
   */
  public function isLocked(): bool;

  /**
   * Get next run time.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   Last run date.
   */
  public function getLastRunTime(): ?DrupalDateTime;

  /**
   * Get next run timestamp.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   Next run date.
   */
  public function getNextRunTime(): DrupalDateTime;

  /**
   * Reset next run time.
   *
   * @param int|null $time
   *   (Optional) The timestamp. Defaults to current timestamp.
   *
   * @return int
   *   Next run timestamp.
   */
  public function resetNextRunTime(?int $time = NULL): int;

  /**
   * Check or cron should run.
   *
   * @param int $request_time
   *   The request time.
   * @param bool $force
   *   TRUE for force run.
   *
   * @return bool
   *   TRUE if cron should run, FALSE if not.
   */
  public function shouldRun(int $request_time, bool $force): bool;

  /**
   * Get single cron job run url.
   *
   * @return \Drupal\Core\Url
   *   The single run url.
   */
  public function getSingleRunUrl(): Url;

}
