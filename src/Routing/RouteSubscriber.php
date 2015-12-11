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
        '_form' => '\Drupal\moderation\Form\NodeRevisionRevertForm',
      ));

      $this->setRevisionAccessRequirements($route);
    }
    if ($route = $collection->get('node.revision_delete_confirm')) {
      $route->setDefaults(array(
        '_form' => '\Drupal\moderation\Form\NodeRevisionDeleteForm',
      ));

      $this->setRevisionAccessRequirements($route);
    }
  }

  protected function setRevisionAccessRequirements(&$route) {
    $requirements = $route->getRequirements();
    if (isset($requirements['_access_node_revision'])) {
      $requirements['_access_moderation_node_revision'] = $requirements['_access_node_revision'];
      unset($requirements['_access_node_revision']);
    }
    $route->setRequirements($requirements);

    return $this;
  }

}
