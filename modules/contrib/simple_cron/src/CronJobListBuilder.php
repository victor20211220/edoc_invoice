<?php

namespace Drupal\simple_cron;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Cron job entities.
 */
class CronJobListBuilder extends DraggableListBuilder {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new FilterFormatListBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    DateFormatterInterface $date_formatter
  ) {
    parent::__construct($entity_type, $storage);

    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_cron_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'title' => $this->t('Cron job'),
      'provider' => $this->t('Module'),
      'crontab' => $this->t('Crontab'),
      'last_run' => $this->t('Last run'),
      'next_run' => $this->t('Next run'),
      'single' => $this->t('Type'),
      'status' => $this->t('Status'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\simple_cron\Entity\CronJobInterface $entity */
    $row['title']['#markup'] = $entity->label();
    $row['provider']['#markup'] = $entity->getProviderName();
    $row['crontab']['#markup'] = $entity->getCrontab();

    $row['last_run']['#markup'] = $entity->getLastRunTime()
      ? $this->dateFormatter->format($entity->getLastRunTime()->getTimestamp(), 'short')
      : $this->t('Never');

    $row['next_run']['#markup'] = $entity->status() && $entity->getNextRunTime()
      ? $this->dateFormatter->format($entity->getNextRunTime()->getTimestamp(), 'short')
      : $this->t('Disabled');

    $row['single']['#markup'] = $entity->isSingle() ? $this->t('Single URL') : $this->t('Default');
    $row['status']['#markup'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');

    if ($entity->isLocked()) {
      $row['status']['#markup'] = $this->t('Locked');
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);

    if ($entity->access('run')) {
      $operations['run'] = [
        'title' => $this->t('Run'),
        'url' => $entity->toUrl('run'),
        'weight' => 20,
      ];
    }

    if ($entity->access('unlock')) {
      $operations['unlock'] = [
        'title' => $this->t('Unlock'),
        'url' => $entity->toUrl('unlock'),
        'weight' => 0,
      ];
    }

    return $operations;
  }

}
