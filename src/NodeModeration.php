<?php
/**
 * @file
 * Contains \Drupal\moderation\NodeModeration.
 */

namespace Drupal\moderation;

use Drupal\moderation\ModerationInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\RevisionableInterface;

class NodeModeration implements ModerationInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function hasDraft(RevisionableInterface $entity) {
    return (boolean) $this->getDraftRevisionId($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDraftRevisionId(RevisionableInterface  $entity) {
    $current_revision = $entity->getRevisionId();
    $entity_storage = $this->entityTypeManager->getStorage('node');
    $vids = $entity_storage->revisionIds($entity);

    // Filter out vids less than or equal to current revision.
    $filtered = array_filter($vids, function ($vid) use ($current_revision) {
      return $vid > $current_revision;
    });

    return array_pop($filtered) ?: 0;
  }

}
