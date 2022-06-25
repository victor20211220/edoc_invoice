<?php

namespace Drupal\simple_cron;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Cron;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\simple_cron\Form\SimpleCronSettingsForm;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Simple cron.
 *
 * @package Drupal\simple_cron
 */
class SimpleCron extends Cron {

  /**
   * The cron job manager.
   *
   * @var \Drupal\simple_cron\CronJobManagerInterface
   */
  protected $cronJobManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Set cron job manager.
   *
   * @param \Drupal\simple_cron\CronJobManagerInterface $cron_job_manager
   *   The cron job manager.
   */
  public function setCronJobManager(CronJobManagerInterface $cron_job_manager): void {
    $this->cronJobManager = $cron_job_manager;
  }

  /**
   * Set config factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function setConfigFactory(ConfigFactoryInterface $config_factory): void {
    $this->configFactory = $config_factory;
  }

  /**
   * Set request stack.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function setRequestStack(RequestStack $request_stack): void {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function run(): bool {
    // Allow execution to continue even if the request gets cancelled.
    @ignore_user_abort(TRUE);

    // Force the current user to anonymous to ensure consistent permissions on
    // cron runs.
    $this->accountSwitcher->switchTo(new AnonymousUserSession());

    $config = $this->configFactory->get(SimpleCronSettingsForm::SETTINGS_NAME);

    // Try to allocate enough time to run all the cron implementations.
    Environment::setTimeLimit($config->get('base.max_execution_time'));

    $return = FALSE;

    $current_request = $this->requestStack->getCurrentRequest();

    // Manual cron job run.
    if ($current_request && $current_request->attributes->get('_route') === 'entity.simple_cron_job.run') {
      /** @var \Drupal\simple_cron\Entity\CronJobInterface $job */
      $job = $current_request->get('simple_cron_job');
      $return = $job->run($this->time->getRequestTime(), TRUE);
    }
    // Single cron job run.
    elseif ($current_request && $job_id = $current_request->query->get('job')) {
      $job = $this->cronJobManager->getEnabledJob($job_id);

      if ($job) {
        $force = $current_request->query->get('force', FALSE);
        $return = $job->run($this->time->getRequestTime(), $force);
      }
    }
    // Default cron job run.
    else {
      // Run all simple cron jobs.
      $this->cronJobsRun();

      if (!$config->get('cron.override_enabled')) {
        $return = $this->runDefaultCronHandlers($config->get('base.lock_timeout'));
      }
      else {
        $this->setCronLastTime();
        $return = TRUE;
      }

      // Process cron queues.
      if (!$config->get('queue.override_enabled')) {
        $this->processQueues();
      }
    }

    // Restore the user.
    $this->accountSwitcher->switchBack();

    return $return;
  }

  /**
   * Run default drupal core cron handlers.
   *
   * @param int $lock_timeout
   *   The lock timeout.
   *
   * @return bool
   *   Cron run status.
   */
  protected function runDefaultCronHandlers(int $lock_timeout): bool {
    // Try to acquire cron lock.
    if (!$this->lock->acquire('cron', $lock_timeout)) {
      // Cron is still running normally.
      $this->logger->warning('Attempting to re-run cron while it is already running.');
    }
    else {
      $this->invokeCronHandlers();
      $this->setCronLastTime();

      // Release cron lock.
      $this->lock->release('cron');

      // Return TRUE so other functions can check if it did run successfully.
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Run simple cron job's.
   */
  protected function cronJobsRun(): void {
    $jobs = $this->cronJobManager->getEnabledDefaultRunJobs();
    $request_time = $this->time->getRequestTime();

    foreach ($jobs as $job) {
      $job->run($request_time);
    }
  }

}
