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
  public function access(Route $route, AccountInterface $account, $node_revision = NULL, NodeInterface $node = NULL) {
    if ($node_revision) {
      $node = $this->nodeStorage->loadRevision($node_revision);
    }
    $operation = $route->getRequirement('_access_moderation_node_revision');
    return AccessResult::allowedIf($node && $this->checkAccess($node, $account, $operation))->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(NodeInterface $node, AccountInterface $account, $op = 'view') {
    if ($op === 'update' && $op === 'delete') {
      $this->checkModerationAccess($node, $account, $op);
    }

    return parent::checkAccess($node, $account, $op);
  }

  protected function checkModerationAccess(NodeInterface $node, AccountInterface $account, $op) {
    $map = [
      'update' => 'revert all revisions',
      'delete' => 'delete all revisions',
    ];
    $bundle = $node->bundle();
    $type_map = [
      'update' => "revert $bundle revisions",
      'delete' => "delete $bundle revisions",
    ];

    if (!$node || !isset($map[$op]) || !isset($type_map[$op])) {
      // If there was no node to check against, or the $op was not one of the
      // supported ones, we return access denied.
      return $this;
    }

    $langcode = $node->language()->getId();
    $cid = $node->getRevisionId() . ':' . $langcode . ':' . $account->id() . ':' . $op;

    // Perform basic permission checks first.
    if (!$account->hasPermission($map[$op]) && !$account->hasPermission($type_map[$op]) && !$account->hasPermission('administer nodes')) {
      $this->access[$cid] = FALSE;
      return $this;
    }

    // This is basically this only part we wanted to change in the Core
    // NodeRevisionAccessCheck. In the case of moderation users SHOULD be able
    // to revert or delete the current revision.
    if ($node->isDefaultRevision() && $this->nodeStorage->countDefaultLanguageRevisions($node) == 1) {
      $this->access[$cid] = FALSE;
    }
    elseif ($account->hasPermission('administer nodes')) {
      $this->access[$cid] = TRUE;
    }
    else {
      // First check the access to the default revision and finally, if the
      // node passed in is not the default revision then access to that, too.
      $this->access[$cid] = $this->nodeAccess->access($this->nodeStorage->load($node->id()), $op, $account) && ($node->isDefaultRevision() || $this->nodeAccess->access($node, $op, $account));
    }
    return $this;
  }

}
