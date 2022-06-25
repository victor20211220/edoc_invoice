<?php

namespace Drupal\simple_cron\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\CronInterface;
use Drupal\simple_cron\Entity\CronJobInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Simple cron job controller.
 *
 * @package Drupal\simple_cron\Controller
 */
class JobController extends ControllerBase {

  /**
   * The cron.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected $cron;

  /**
   * JobController constructor.
   *
   * @param \Drupal\Core\CronInterface $cron
   *   The cron.
   */
  public function __construct(CronInterface $cron) {
    $this->cron = $cron;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cron')
    );
  }

  /**
   * Runs a single cron job.
   *
   * @param \Drupal\simple_cron\Entity\CronJobInterface $simple_cron_job
   *   The cron job.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects to the job listing after running a job.
   */
  public function run(CronJobInterface $simple_cron_job): RedirectResponse {
    if ($this->cron->run()) {
      $this->messenger()->addStatus($this->t('Cron job %label was successfully run.', [
        '%label' => $simple_cron_job->label(),
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Cron job %label was not successful.', [
        '%label' => $simple_cron_job->label(),
      ]));
    }

    return $this->redirect('entity.simple_cron_job.collection');
  }

}
