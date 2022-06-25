<?php

namespace Drupal\simple_cron\Form;

use Cron\CronExpression;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;

/**
 * Cron job form.
 */
class CronJobForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\simple_cron\Entity\CronJobInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form['id'] = [
      '#type' => 'textfield',
      '#default_value' => $this->entity->id(),
      '#title' => $this->t('Cron job ID'),
      '#description' => $this->t('This ID is used for @link.', [
        '@link' => Link::fromTextAndUrl(
          $this->t('Single cron job run'),
          $this->entity->getSingleRunUrl()
        )->toString(),
      ]),
      '#disabled' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
      '#description' => $this->t('Disabled cron job are not run.'),
    ];

    $form['single'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Single URL only'),
      '#default_value' => $this->entity->isSingle(),
      '#description' => $this->t('If checked, this cron job will be skipped in the default cron run.'),
    ];

    $form['crontab'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Crontab expression'),
      '#default_value' => $this->entity->getCrontab(),
      '#required' => TRUE,
      '#description' => $this->t('Format: @format [minute hour day(month) month day(week)]', ['@format' => '* * * * *']),
    ];

    $form['configuration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Configuration'),
      '#tree' => TRUE,
    ];

    // Additional configuration from plugin.
    $plugin = $this->entity->getPlugin();
    $form['configuration'] = $plugin ? $plugin->buildConfigurationForm($form['configuration'], $form_state) : [];

    if (!Element::children($form['configuration'])) {
      unset($form['configuration']);
    }

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $plugin = $this->entity->getPlugin();
    if ($plugin) {
      $plugin->validateConfigurationForm($form, $form_state);
    }

    if (!CronExpression::isValidExpression($form_state->getValue('crontab'))) {
      $form_state->setErrorByName('crontab', $this->t('Crontab expression in invalid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): void {
    $plugin = $this->entity->getPlugin();
    if ($plugin) {
      $plugin->submitConfigurationForm($form, $form_state);
    }

    $this->entity->resetNextRunTime();

    parent::save($form, $form_state);

    $this->messenger()->addMessage($this->t('Saved the @label Cron job.', [
      '@label' => $this->entity->label(),
    ]));

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
  }

}
