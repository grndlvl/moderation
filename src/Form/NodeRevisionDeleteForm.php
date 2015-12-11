<?php

/**
 * @file
 * Contains \Drupal\moderation\Form\NodeRevisionDeleteForm.
 */

namespace Drupal\moderation\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Form\NodeRevisionDeleteForm as FormBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a node revision.
 */
class NodeRevisionDeleteForm extends FormBase  {

  /**
   * The moderation service.
   *
   * @var \Drupal\moderation\ModerationInterface
   */
  protected $moderation;

  /**
   * Constructs a new NodeRevisionDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_type_storage
   *   The node type storage.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(EntityStorageInterface $node_storage, EntityStorageInterface $node_type_storage, Connection $connection, ModerationInterface $moderation) {
    parent::__construct($node_storage, $node_type_storage, $connection);
    $this->moderation = $moderation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_manager = $container->get('entity.manager');
    return new static(
      $entity_manager->getStorage('node'),
      $entity_manager->getStorage('node_type'),
      $container->get('database'),
      $container->get('moderation.node_moderation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->revision->isDefaultRevision() && $draft_id = $this->getNewDraftRevision()) {
      $this->revision->isDefaultRevision(FALSE);
      $this->revision->save();
      $draft = $this->nodeStorage->loadRevision($draft_id);
      $draft->isDefaultRevision(TRUE);
      $draft->save();
    }

    parent::submitForm($form, $form_state);
  }

  public function getNewDraftRevision() {
    $draft_id = $this->moderation->getDraftRevisionId($this->revision);

    // When the current is the draft. Find the next most recent revision.
    if (empty($draft_id) || $draft_id === $this->revision->getRevisionId()) {
      $vids = $this->nodeStorage->revisionIds($this->revision);
      ksort($vids);
      array_pop($vids);
      $draft_id = array_pop($vids);
    }

    return ($draft_id === $this->revision->getRevisionId()) ? 0 : $draft_id;
  }

}
