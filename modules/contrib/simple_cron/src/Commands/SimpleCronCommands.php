<?php

namespace Drupal\simple_cron\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Utility\Environment;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\simple_cron\Form\SimpleCronSettingsForm;
use Drush\Commands\DrushCommands;

/**
 * Simple cron commands.
 *
 * @package Drupal\simple_cron\Commands
 */
class SimpleCronCommands extends DrushCommands {

  use LoggerChannelTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * SimpleCronCommands constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct();

    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Run a specific cron job by cron job id.
   *
   * @param string $id
   *   Cron job ID.
   * @param array $options
   *   Options array.
   *
   * @command simple-cron
   *
   * @option force
   *   Skip the schedule check for each job. Locks are still respected.
   * @usage drush simple-cron cron.node
   *   Run the cron.node cron job
   * @usage drush simple-cron cron.node --force
   *   Force run the cron.node cron job
   *
   * @aliases scron,simple-cron
   *
   * @throws \Exception
   */
  public function cronJobRun($id = NULL, array $options = ['force' => FALSE]): void {
    $force = (bool) $options['force'];

    if (!$id) {
      throw new \Exception(dt('Running all cron jobs is not supported by Simple Cron simple-cron - please use Drupal Core core-cron command!'));
    }

    // Run a specific job.
    /** @var \Drupal\simple_cron\Entity\CronJobInterface $job */
    $job = $this->entityTypeManager->getStorage('simple_cron_job')->load($id);
    if (!$job) {
      throw new \Exception(dt('@name not found', ['@name' => $id]));
    }

    if (!$job->status()) {
      throw new \Exception(dt('The cron job "@name" is disabled', ['@name' => $id]));
    }

    // Allow execution to continue even if the request gets cancelled.
    @ignore_user_abort(TRUE);

    // Try to allocate enough time to run all the cron implementations.
    $config = $this->configFactory->get(SimpleCronSettingsForm::SETTINGS_NAME);
    Environment::setTimeLimit($config->get('base.max_execution_time'));

    $status = $job->run(time(), $force);

    if ($status) {
      $this->getLogger('simple_cron')->notice('Execution of cron job @job is completed.', ['@job' => $job->id()]);
    }
  }

  /**
   * Get a list of all simple cron jobs in the system.
   *
   * @param array $options
   *   Options array.
   *
   * @command simple-cron:list
   *
   * @field-labels
   *   id: Cron job ID
   *   label: Label
   *   status: Status
   *
   * @option status
   *   Simple cron filter by status.
   *   Possible values: ['enabled', 'disabled'].
   * @option format
   *   List display format.
   * @usage drush simple-cron:list
   *   Show a list of all available cron jobs.
   *
   * @aliases scron:list,simple-cron:list
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields|null
   *   The table of cron jobs.
   *
   * @throws \Exception
   */
  public function cronJobsList(array $options = [
    'status' => NULL,
    'format' => 'table',
  ]): ?RowsOfFields {
    /** @var \Drupal\simple_cron\Entity\CronJobInterface[] $jobs */
    $jobs = $this->entityTypeManager->getStorage('simple_cron_job')->loadMultiple();

    // Get the --status option.
    $status = $options['status'];

    if ($status && !in_array($status, ['enabled', 'disabled'])) {
      throw new \Exception(dt('Invalid status: @status. Available options are "enabled" or "disabled"', ['@status' => $status]));
    }

    $rows = [];
    foreach ($jobs as $job) {
      if ($status === 'enabled' && !$job->status()) {
        continue;
      }

      if ($status === 'disabled' && $job->status()) {
        continue;
      }

      $rows[] = [
        'id' => $job->id(),
        'label' => $job->label(),
        'status' => $job->status() ? dt('Enabled') : dt('Disabled'),
      ];
    }

    if (empty($rows)) {
      $this->logger()->notice(dt('No cron jobs found.'));
      return NULL;
    }

    return new RowsOfFields($rows);
  }

}
