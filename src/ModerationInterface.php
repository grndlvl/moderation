<?php
/**
 * @file
 * Contains \Drupal\moderation\ModerationInterface.
 */

namespace Drupal\moderation;

use Drupal\Core\Entity\RevisionableInterface;

interface ModerationInterface {

  /**
   * Retrieve the active draft id of the entity.
   *
   * @param Drupal\Core\Entity\RevisionableInterface $entity
   *   The revisionable entity object.
   *
   * @return boolean
   *   Returns TRUE if the the entity has an active draft revision or FALSE otherwise.
   */
  public function hasDraft(RevisionableInterface $entity);

  /**
   * Retrieve the active draft id of the entity.
   *
   * @param Drupal\Core\Entity\RevisionableInterface $entity
   *   The revisionable entity object.
   *
   * @return boolean
   *   Returns TRUE if the the entity has an active draft revision or FALSE otherwise.
   */
  public function getDraftRevisionId(RevisionableInterface  $entity);

}
