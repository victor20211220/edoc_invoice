<?php

namespace Drupal\simple_cron\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Cron job disable form.
 *
 * @package Drupal\simple_cron\Form
 */
class CronJobDisableForm extends EntityConfirmFormBase {

  /**
   * The cron job.
   *
   * @var \Drupal\simple_cron\Entity\CronJobInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    return $this->t('Do you really want to disable @label cron job?', [
      '@label' => $this->getEntity()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->t('This cron job will no longer be executed.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return $this->getEntity()->toUrl('collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string {
    return $this->t('Disable');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->entity->disable()->save();
    $this->messenger()->addStatus(
      $this->t('Disabled the %label Cron job.', ['%label' => $this->entity->label()])
    );

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
