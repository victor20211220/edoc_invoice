<?php

namespace Drupal\simple_cron\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\simple_cron\Controller\JobController;
use Symfony\Component\Routing\Route;

/**
 * Provides HTML routes for cron job entity.
 */
class CronJobRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);
    $entity_type_id = $entity_type->id();

    if ($collection_route = $collection->get("entity.{$entity_type_id}.collection")) {
      $collection_route->setRequirement(
        '_permission',
        $entity_type->getAdminPermission() . '+view simple cron jobs+run simple cron jobs'
      );
    }

    if ($route = $this->getEnableFormRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.enable", $route);
    }

    if ($route = $this->getDisableFormRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.disable", $route);
    }

    if ($route = $this->getUnlockPageRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.unlock", $route);
    }

    if ($route = $this->getRunPageRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.run", $route);
    }

    return $collection;
  }

  /**
   * Gets the enable form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getEnableFormRoute(EntityTypeInterface $entity_type): ?Route {
    if ($entity_type->hasLinkTemplate('enable')) {
      $entity_type_id = $entity_type->id();

      $route = new Route($entity_type->getLinkTemplate('enable'));
      $route->setDefault('_entity_form', "{$entity_type_id}.enable");
      $route->setDefault('_title', 'Enable');
      $route->setRequirement('_entity_access', "{$entity_type_id}.enable");
      $route->setOption('_admin_route', TRUE);

      return $route;
    }

    return NULL;
  }

  /**
   * Gets the disable form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getDisableFormRoute(EntityTypeInterface $entity_type): ?Route {
    if ($entity_type->hasLinkTemplate('disable')) {
      $entity_type_id = $entity_type->id();

      $route = new Route($entity_type->getLinkTemplate('disable'));
      $route->setDefault('_entity_form', "{$entity_type_id}.disable");
      $route->setDefault('_title', 'Disable');
      $route->setRequirement('_entity_access', "{$entity_type_id}.disable");
      $route->setOption('_admin_route', TRUE);

      return $route;
    }

    return NULL;
  }

  /**
   * Gets the unlock form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getUnlockPageRoute(EntityTypeInterface $entity_type): ?Route {
    if ($entity_type->hasLinkTemplate('unlock')) {
      $entity_type_id = $entity_type->id();

      $route = new Route($entity_type->getLinkTemplate('unlock'));
      $route->setDefault('_entity_form', "{$entity_type_id}.unlock");
      $route->setDefault('_title', 'Unlock');
      $route->setRequirement('_entity_access', "{$entity_type_id}.unlock");
      $route->setOption('_admin_route', TRUE);

      return $route;
    }

    return NULL;
  }

  /**
   * Gets the run page route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRunPageRoute(EntityTypeInterface $entity_type): ?Route {
    if ($entity_type->hasLinkTemplate('run')) {
      $entity_type_id = $entity_type->id();

      $route = new Route($entity_type->getLinkTemplate('run'));
      $route->setDefault('_controller', JobController::class . '::run');
      $route->setRequirement('_entity_access', "{$entity_type_id}.run");
      $route->setOption('_admin_route', TRUE);

      return $route;
    }

    return NULL;
  }

}
