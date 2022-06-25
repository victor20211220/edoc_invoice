<?php

namespace Drupal\simple_cron;

use Drupal\simple_cron\Entity\CronJobInterface;

/**
 * Interface of the cron job manager.
 *
 * @package Drupal\simple_cron\Form
 */
interface CronJobManagerInterface {

  /**
   * Update cron job's list.
   */
  public function updateList(): void;

  /**
   * Get enabled job.
   *
   * @param string $job_id
   *   The job id.
   *
   * @return \Drupal\simple_cron\Entity\CronJobInterface|null
   *   Cron job.
   */
  public function getEnabledJob(string $job_id): ?CronJobInterface;

  /**
   * Get enabled cron jobs for default cron run.
   *
   * @return \Drupal\simple_cron\Entity\CronJobInterface[]
   *   Cron jobs.
   */
  public function getEnabledDefaultRunJobs(): array;

}
