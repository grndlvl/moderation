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
use Drupal\node\Access\NodeRevisionAccessCheck;
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
   * The revision access check service.
   *
   * @var \Drupal\node\Access\NodeRevisionAccessCheck
   */
  protected $revision_access_handler;

  /**
   * Constructs a new DraftAccess.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager, ModerationInterface $moderation, NodeRevisionAccessCheck $revision_access_handler) {
    $this->nodeStorage = $entity_manager->getStorage('node');
    $this->nodeAccess = $entity_manager->getAccessControlHandler('node');
    $this->moderation = $moderation;
    $this->revision_access_handler = $revision_access_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, AccountInterface $account, NodeInterface $node = NULL) {
    if ($draft_revision = $this->moderation->getDraftRevisionId($node)) {
      $node = $this->nodeStorage->loadRevision($draft_revision);
    }

    $operation = $route->getRequirement('_access_node_draft');
    return AccessResult::allowedIf((boolean) $draft_revision)
      ->andIf(AccessResult::allowedIf($this->revision_access_handler->checkAccess($node, $account, $operation)))
      ->cachePerUser()
      ->addCacheableDependency($node);
  }
}
