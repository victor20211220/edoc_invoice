<?php

namespace Drupal\simple_cron_test\Plugin\SimpleCron;

use Drupal\Core\Form\FormStateInterface;
use Drupal\simple_cron\Plugin\SimpleCronPluginBase;

/**
 * Test cron job.
 *
 * @SimpleCron(
 *   id = "test_cron_job",
 *   label = @Translation("Test cron job", context = "Simple cron"),
 *   weight = -1
 * )
 */
class TestCronJob extends SimpleCronPluginBase {

  /**
   * {@inheritdoc}
   */
  public function process(): void {
    if ($this->getConfigValue('error_enabled')) {
      throw new \RuntimeException('Error in cron execution enabled');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'error_enabled' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['error_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Error enabled'),
      '#default_value' => $this->getConfigValue('error_enabled'),
    ];

    return $form;
  }

}
