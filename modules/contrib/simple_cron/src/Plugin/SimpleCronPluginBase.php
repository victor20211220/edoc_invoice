<?php

namespace Drupal\simple_cron\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simple_cron\Entity\CronJobInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for SimpleCron plugins.
 */
abstract class SimpleCronPluginBase extends PluginBase implements SimpleCronPluginInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The cron job.
   *
   * @var \Drupal\simple_cron\Entity\CronJobInterface
   */
  protected $cronJob;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory|object|null
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    $instance->setConfiguration($configuration);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $label = $this->pluginDefinition['label'];
    $type = $this->getType();
    $definition = $this->getTypeDefinitions()[$type] ?? [];

    if ($type !== 'default' && isset($definition['label'])) {
      $label .= ': ' . $definition['label'];
    }

    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeDefinitions(): array {
    return [
      'default' => [
        'label' => $this->pluginDefinition['label'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->getCronJob()->getType();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    // Default configuration is optional.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    // Configuration form is optional.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Configuration form validation is optional.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Configuration form submit callback is optional.
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = array_merge(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCronJob(): CronJobInterface {
    return $this->cronJob;
  }

  /**
   * {@inheritdoc}
   */
  public function setCronJob(CronJobInterface $cron_job): SimpleCronPluginInterface {
    $this->cronJob = $cron_job;
    return $this;
  }

  /**
   * Returns the value of a given cron configuration.
   *
   * @param string $config_key
   *   The config key.
   *
   * @return mixed
   *   The configuration value.
   */
  protected function getConfigValue(string $config_key) {
    return $this->getConfiguration()[$config_key] ?? NULL;
  }

}
