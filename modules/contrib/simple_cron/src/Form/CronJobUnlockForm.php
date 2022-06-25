<?php

namespace Drupal\simple_cron\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Cron job unlock form.
 *
 * @package Drupal\simple_cron\Form
 */
class CronJobUnlockForm extends EntityConfirmFormBase {

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
    return $this->t('Do you really want to unlock @label cron job?', [
      '@label' => $this->getEntity()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->t('This cron job will be unlocked.');
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
    return $this->t('Unlock');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->entity->unlock()->save();
    $this->messenger()->addStatus(
      $this->t('Unlocked the %label Cron job.', ['%label' => $this->entity->label()])
    );

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
