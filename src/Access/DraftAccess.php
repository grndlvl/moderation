<?php
/**
 * @file
 * Contains \Drupal\moderation\Access\DraftAccess.
 */

namespace Drupal\moderation\Access;

use Drupal\moderation\ModerationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides an access checker for node revisions.
 *
 * @todo: Abstract and add an interface so other entities can inherit it.
 */
class DraftAccess implements AccessInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * The node access control handler.
   *
   * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface
   */
  protected $nodeAccess;

  /**
   * The moderation service.
   *
   * @var \Drupal\moderation\ModerationInterface
   */
  protected $moderation;

  /**
   * Constructs a new DraftAccess.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager, ModerationInterface $moderation) {
    $this->nodeStorage = $entity_manager->getStorage('node');
    $this->nodeAccess = $entity_manager->getAccessControlHandler('node');
    $this->moderation = $moderation;
  }

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, AccountInterface $account, NodeInterface $node = NULL) {
    $access_control_handler = \Drupal::service('access_check.node.revision');

    if ($draft_revision = $this->moderation->getDraftRevisionId($node)) {
      $node = $this->nodeStorage->loadRevision($draft_revision);
    }

    // Check that the user has the ability to update the node, and that the node
    // has a draft.
    return AccessResult::allowedIf($access_control_handler->checkAccess($node, $account, 'view') && (boolean) $draft_revision)->addCacheableDependency($node);
  }

}
