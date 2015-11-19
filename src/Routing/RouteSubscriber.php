<?php

/**
 * @file
 * Contains \Drupal\moderation\Routing\RouteSubscriber.
 */

namespace Drupal\moderation\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.node.version_history')) {
      $route->setDefaults(array(
        '_controller' => '\Drupal\moderation\Controller\DraftController::revisionOverview',
      ));
    }
    if ($route = $collection->get('node.revision_revert_confirm')) {
      $route->setDefaults(array(
        '_form' => '\Drupal\moderation\Form\NodeRevisionRevertForm'
      ));
    }
  }

}
