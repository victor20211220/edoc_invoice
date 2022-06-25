<?php

namespace Drupal\simple_cron\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_cron\CronJobManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The settings form of the simple cron module.
 *
 * @package Drupal\simple_cron\Form
 */
class SimpleCronSettingsForm extends ConfigFormBase {

  public const SETTINGS_NAME = 'simple_cron.settings';

  /**
   * The cron job manager.
   *
   * @var \Drupal\simple_cron\CronJobManagerInterface
   */
  protected $cronJobManager;

  /**
   * SimpleCronSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\simple_cron\CronJobManagerInterface $cron_job_manager
   *   The cron job manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    CronJobManagerInterface $cron_job_manager
  ) {
    parent::__construct($config_factory);

    $this->cronJobManager = $cron_job_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('simple_cron.cron_job_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_cron_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [static::SETTINGS_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $values = $this->config(static::SETTINGS_NAME);

    // Cron configuration.
    $form['cron'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cron hook override'),
      '#tree' => TRUE,
    ];

    $form['cron']['override_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $values->get('cron.override_enabled'),
      '#description' => $this->t('If enabled, hook_cron() are exposed as cron jobs and can be configured separately.'),
    ];

    // Queue configuration.
    $form['queue'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Queue workers override'),
      '#tree' => TRUE,
    ];

    $form['queue']['override_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $values->get('queue.override_enabled'),
      '#description' => $this->t('If enabled, queue workers are exposed as cron jobs and can be configured separately.'),
    ];

    // Base configuration.
    $form['base'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Base configuration'),
      '#tree' => TRUE,
    ];

    $form['base']['max_execution_time'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum execution time'),
      '#default_value' => $values->get('base.max_execution_time'),
      '#field_suffix' => $this->t('seconds'),
      '#required' => TRUE,
      '#min' => 0,
      '#max' => 7200,
    ];

    $form['base']['lock_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Lock timeout'),
      '#default_value' => $values->get('base.lock_timeout'),
      '#field_suffix' => $this->t('seconds'),
      '#required' => TRUE,
      '#min' => 0,
      '#max' => 7200,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $values = $form_state->cleanValues()->getValues();
    $config = $this->config(static::SETTINGS_NAME);

    foreach ($values as $key => $value) {
      $config->set($key, $value);
    }

    $config->save();

    $this->cronJobManager->updateList();
    $form_state->setRedirect('entity.simple_cron_job.collection');
  }

}
