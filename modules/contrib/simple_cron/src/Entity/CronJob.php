<?php

namespace Drupal\simple_cron\Entity;

use Cron\CronExpression;
use Drupal\Component\Utility\Timer;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\simple_cron\Form\SimpleCronSettingsForm;
use Drupal\simple_cron\Plugin\SimpleCronPluginInterface;
use Psr\Log\NullLogger;

/**
 * Defines the Cron job entity.
 *
 * @ConfigEntityType(
 *   id = "simple_cron_job",
 *   label = @Translation("Cron job"),
 *   label_collection = @Translation("Simple cron jobs"),
 *   label_singular = @Translation("cron job"),
 *   label_plural = @Translation("cron jobs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count cron job",
 *     plural = "@count cron jobs",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\simple_cron\CronJobAccessControlHandler",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\simple_cron\CronJobListBuilder",
 *     "form" = {
 *       "edit" = "Drupal\simple_cron\Form\CronJobForm",
 *       "enable" = "Drupal\simple_cron\Form\CronJobEnableForm",
 *       "disable" = "Drupal\simple_cron\Form\CronJobDisableForm",
 *       "unlock" = "Drupal\simple_cron\Form\CronJobUnlockForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\simple_cron\Routing\CronJobRouteProvider",
 *     },
 *   },
 *   config_prefix = "job",
 *   admin_permission = "administer simple cron",
 *   entity_keys = {
 *     "id" = "id",
 *     "plugin" = "plugin",
 *     "type" = "type",
 *     "status" = "status",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "crontab",
 *     "plugin",
 *     "type",
 *     "provider",
 *     "single",
 *     "status",
 *     "configuration",
 *     "weight"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/system/cron/jobs/{simple_cron_job}/edit",
 *     "enable" = "/admin/config/system/cron/jobs/{simple_cron_job}/enable",
 *     "disable" = "/admin/config/system/cron/jobs/{simple_cron_job}/disable",
 *     "unlock" = "/admin/config/system/cron/jobs/{simple_cron_job}/unlock",
 *     "run" = "/admin/config/system/cron/jobs/{simple_cron_job}/run",
 *     "collection" = "/admin/config/system/cron/jobs"
 *   }
 * )
 */
class CronJob extends ConfigEntityBase implements CronJobInterface {

  use StringTranslationTrait;
  use LoggerChannelTrait;

  /**
   * State name of last successfully cron run time.
   */
  protected const STATE_LAST_RUN = 'last_run';

  /**
   * State name of next cron run time.
   */
  protected const STATE_NEXT_RUN = 'next_run';

  /**
   * The Cron job ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Cron job crontab expresion.
   *
   * @var string
   */
  protected $crontab;

  /**
   * The plugin id.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The Cron job plugin type.
   *
   * @var string
   */
  protected $type;

  /**
   * The Cron job provider (module machine name).
   *
   * @var string
   */
  protected $provider;

  /**
   * The optional single url status.
   *
   * @var bool
   */
  protected $single = FALSE;

  /**
   * The optional Cron job plugin configuration.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * The optional weight.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The lock service.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The locking timeout.
   *
   * @var int
   */
  protected $lockTimeout;

  /**
   * Stores the state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The simple cron plugin manager.
   *
   * @var \Drupal\simple_cron\Plugin\SimpleCronPluginManagerInterface
   */
  protected $simpleCronPluginManager;

  /**
   * CronJob constructor.
   *
   * @param array $values
   *   An array of values to set, keyed by property name. If the entity type
   *   has bundles, the bundle key has to be specified.
   * @param string $entity_type
   *   The type of the entity to create.
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);

    $this->lock = \Drupal::service('lock');
    $this->database = \Drupal::service('database');
    $this->lockTimeout = \Drupal::config(SimpleCronSettingsForm::SETTINGS_NAME)->get('base.lock_timeout');
    $this->state = \Drupal::state();
    $this->simpleCronPluginManager = \Drupal::service('plugin.manager.simple_cron');
    // If detailed logging isn't enabled, don't log individual execution times.
    $logging_enabled = \Drupal::config('system.cron')->get('logging');
    $this->logger = $logging_enabled ? $this->getLogger('simple_cron') : new NullLogger();
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    if ($plugin = $this->getPlugin()) {
      return $plugin->label();
    }

    return $this->t('Unknown');
  }

  /**
   * {@inheritdoc}
   */
  public function getCrontab(): string {
    return $this->crontab;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin(): ?SimpleCronPluginInterface {
    if (!$this->plugin) {
      return NULL;
    }

    $plugin = $this->simpleCronPluginManager->getPlugin($this->plugin, $this->configuration);
    if ($plugin) {
      $plugin->setCronJob($this);
    }

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function getProvider(): string {
    return $this->provider;
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderName(): string {
    return $this->moduleHandler()->getName($this->provider);
  }

  /**
   * {@inheritdoc}
   */
  public function isSingle(): bool {
    return $this->single ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    $this->addDependency('module', $this->provider);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities): void {
    parent::postDelete($storage, $entities);

    // Delete cron job state values.
    $state_ids = [];
    /** @var \Drupal\simple_cron\Entity\CronJobInterface $entity */
    foreach ($entities as $entity) {
      $state_ids[] = 'simple_cron.state.' . $entity->id();
    }

    \Drupal::state()->deleteMultiple($state_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function run(int $request_time, bool $force = FALSE): bool {
    try {
      $plugin = $this->getPlugin();

      if (!$plugin || !$this->shouldRun($request_time, $force)) {
        $this->logger->warning('Cron job @job should not be running.', ['@job' => $this->label()]);
        return FALSE;
      }

      if (!$this->lock->acquire('simple_cron:' . $this->id(), $this->lockTimeout)) {
        // Cron is still running normally.
        $this->logger->warning('Attempting to re-run @job cron while it is already running.', ['@job' => $this->label()]);
        return FALSE;
      }

      $this->logger->notice('Starting execution of cron job @job.', ['@job' => $this->id()]);

      Timer::start('simple_cron:' . $this->id());
      $plugin->process();
      Timer::stop('simple_cron:' . $this->id());

      $this->setState(static::STATE_LAST_RUN, $request_time);
      $this->resetNextRunTime();

      $this->logger->notice('Execution of cron job @job took @time.', [
        '@job' => $this->id(),
        '@time' => Timer::read('simple_cron:' . $this->id()) . 'ms',
      ]);

      // Release cron lock.
      $this->lock->release('simple_cron:' . $this->id());

      return TRUE;
    }
    catch (\Exception $exception) {
      $message = '%type: @message in %function (line %line of %file).';
      $this->getLogger('simple_cron')->error($message, Error::decodeException($exception));
    }
    catch (\Throwable $throwable) {
      $this->getLogger('simple_cron')->error('@message', ['@message' => $throwable->getMessage()]);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function unlock(): CronJobInterface {
    $this->database->delete('semaphore')
      ->condition('name', 'simple_cron:' . $this->id())
      ->execute();

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked(): bool {
    return !$this->lock->lockMayBeAvailable('simple_cron:' . $this->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getLastRunTime(): ?DrupalDateTime {
    $timestamp = $this->getState(static::STATE_LAST_RUN);

    return $timestamp ? new DrupalDateTime(date('Y-m-d H:i:s', $timestamp)) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextRunTime(): DrupalDateTime {
    $next_run = $this->getState(static::STATE_NEXT_RUN);
    // Set new next run time if not set.
    if (!$next_run) {
      $next_run = $this->resetNextRunTime();
    }

    return new DrupalDateTime(date('Y-m-d H:i:s', $next_run));
  }

  /**
   * {@inheritdoc}
   */
  public function resetNextRunTime(?int $time = NULL): int {
    try {
      $cron = new CronExpression($this->getCronTab());
      $date = $time ? date('Y-m-d H:i', $time) : 'now';
      $next_run = $cron->getNextRunDate($date)->getTimestamp();
      $this->setState(static::STATE_NEXT_RUN, $next_run);
    }
    catch (\Exception $exception) {
      $message = '%type: @message in %function (line %line of %file).';
      $this->getLogger('simple_cron')->error($message, Error::decodeException($exception));
      $next_run = $this->getState(static::STATE_NEXT_RUN, 0);
    }

    return $next_run;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldRun(int $request_time, bool $force): bool {
    return $force || ($this->status() && $this->getNextRunTime()->getTimestamp() <= $request_time);
  }

  /**
   * {@inheritdoc}
   */
  public function getSingleRunUrl(): Url {
    return Url::fromRoute(
      'system.cron',
      ['key' => $this->state->get('system.cron_key')],
      ['query' => ['job' => $this->id()]]
    )->setAbsolute();
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b): int {
    /** @var \Drupal\simple_cron\Entity\CronJobInterface $a */
    /** @var \Drupal\simple_cron\Entity\CronJobInterface $b */
    $a_weight = $a->getWeight();
    $b_weight = $b->getWeight();

    if ($a->getWeight() === $b->getWeight()) {
      return strnatcasecmp($a->id(), $b->id());
    }

    return $a_weight < $b_weight ? -1 : 1;
  }

  /**
   * Set state value.
   *
   * @param string $key
   *   The parameter key.
   * @param mixed $value
   *   The value.
   */
  protected function setState(string $key, $value): void {
    $state_values = $this->state->get('simple_cron.state.' . $this->id(), []);
    $state_values[$key] = $value;

    $this->state->set('simple_cron.state.' . $this->id(), $state_values);
  }

  /**
   * Get state value.
   *
   * @param string $key
   *   The parameter key.
   * @param mixed $default_value
   *   (optional) The default value.
   *
   * @return mixed
   *   Value from state.
   */
  protected function getState(string $key, $default_value = NULL) {
    $state_values = $this->state->get('simple_cron.state.' . $this->id(), []);

    return $state_values[$key] ?? $default_value;
  }

}
