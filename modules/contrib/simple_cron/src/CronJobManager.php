<?php

namespace Drupal\simple_cron;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_cron\Entity\CronJob;
use Drupal\simple_cron\Entity\CronJobInterface;
use Drupal\simple_cron\Plugin\SimpleCronPluginInterface;
use Drupal\simple_cron\Plugin\SimpleCronPluginManagerInterface;

/**
 * Manager of the cron job list.
 *
 * @package Drupal\simple_cron\Form
 */
class CronJobManager implements CronJobManagerInterface {

  /**
   * The cron job storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $cronJobStorage;

  /**
   * The simple cron plugin manager.
   *
   * @var \Drupal\simple_cron\Plugin\SimpleCronPluginManagerInterface
   */
  protected $simpleCronPluginManager;

  /**
   * CronJobManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\simple_cron\Plugin\SimpleCronPluginManagerInterface $simple_cron_plugin_manager
   *   The simple cron plugin manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    SimpleCronPluginManagerInterface $simple_cron_plugin_manager
  ) {
    $this->cronJobStorage = $entity_type_manager->getStorage('simple_cron_job');
    $this->simpleCronPluginManager = $simple_cron_plugin_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function updateList(): void {
    $updated_jobs = [];

    foreach ($this->simpleCronPluginManager->getPlugins() as $plugin) {
      foreach ($plugin->getTypeDefinitions() as $type => $definition) {
        $job_id = $type !== 'default' ? sprintf('%s.%s', $plugin->id(), $type) : $plugin->id();
        $this->createCronJob($job_id, $type, $definition, $plugin);

        $updated_jobs[] = $job_id;
      }
    }

    $this->deleteCronJobs($updated_jobs, 'NOT IN');
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledJob(string $job_id): ?CronJobInterface {
    $jobs = $this->cronJobStorage->loadByProperties([
      'id' => $job_id,
      'status' => TRUE,
    ]);

    return $jobs ? reset($jobs) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledDefaultRunJobs(): array {
    $jobs = $this->cronJobStorage->loadByProperties([
      'status' => TRUE,
      'single' => FALSE,
    ]);

    uasort($jobs, CronJob::class . '::sort');

    return $jobs;
  }

  /**
   * Delete cron jobs entities.
   *
   * @param array $jobs_ids
   *   The cron jobs ids.
   * @param string $operator
   *   The condition operator.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function deleteCronJobs(array $jobs_ids, string $operator): void {
    // Delete old cron jobs entities.
    $ids = $this->cronJobStorage->getQuery()
      ->condition('id', $jobs_ids, $operator)
      ->execute();

    if ($ids) {
      $cron_jobs = $this->cronJobStorage->loadMultiple($ids);
      $this->cronJobStorage->delete($cron_jobs);
    }
  }

  /**
   * Create cron job.
   *
   * @param string $id
   *   The cron job type.
   * @param string $type
   *   The type.
   * @param array $definition
   *   The definition.
   * @param \Drupal\simple_cron\Plugin\SimpleCronPluginInterface $plugin
   *   The simple cron plugin.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createCronJob(string $id, string $type, array $definition, SimpleCronPluginInterface $plugin): void {
    if (!$this->cronJobStorage->load($id)) {
      $plugin_definition = $plugin->getPluginDefinition();

      $this->cronJobStorage->create([
        'id' => $id,
        'crontab' => '*/15 * * * *',
        'plugin' => $plugin->getPluginId(),
        'type' => $type,
        'provider' => $definition['provider'] ?? $plugin_definition['provider'],
        'configuration' => $definition['configuration'] ?? $plugin->defaultConfiguration(),
        'weight' => $plugin_definition['weight'],
        'single' => FALSE,
        'status' => TRUE,
      ])->save();
    }
  }

}
