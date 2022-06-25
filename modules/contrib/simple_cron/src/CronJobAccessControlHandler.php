<?php

namespace Drupal\simple_cron;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the cron job entity type.
 *
 * @package Drupal\simple_cron
 */
class CronJobAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation === 'delete') {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    /** @var \Drupal\simple_cron\Entity\CronJobInterface $entity */
    switch ($operation) {
      case 'disable':
        return AccessResult::allowedIf($entity->status())
          ->andIf(AccessResult::allowedIfHasPermission($account, $this->entityType->getAdminPermission()));

      case 'enable':
        return AccessResult::allowedIf(!$entity->status())
          ->andIf(AccessResult::allowedIfHasPermission($account, $this->entityType->getAdminPermission()));

      case 'run';
        return AccessResult::allowedIf($entity->status() && !$entity->isLocked())
          ->andIf(AccessResult::allowedIfHasPermissions($account, [
            'run simple cron jobs',
            $this->entityType->getAdminPermission(),
          ], 'OR'));

      case 'unlock';
        return AccessResult::allowedIf($entity->isLocked())
          ->andIf(AccessResult::allowedIfHasPermission($account, $this->entityType->getAdminPermission()));

      default:
        return parent::checkAccess($entity, $operation, $account);
    }
  }

}
