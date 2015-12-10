<?php

/**
 * @file
 * Contains \Drupal\node\Access\NodeRevisionAccessCheck.
 */

namespace Drupal\moderation\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\node\Access\NodeRevisionAccessCheck as NodeRevisionAccessCheckBase;
use Symfony\Component\Routing\Route;

/**
 * Provides an access checker for node revisions.
 *
 * @ingroup node_access
 */
class NodeRevisionAccessCheck extends NodeRevisionAccessCheckBase {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(NodeInterface $node, AccountInterface $account, $op = 'view') {
    return parent::checkAccess($node, $account, $op);
  }

}
