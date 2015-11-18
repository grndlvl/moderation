<?php
/**
 * @file
 * Contains \Drupal\moderation\Form\NodeForm
 */

namespace Drupal\moderation\Form;

use Drupal\moderation\ModerationInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeForm as BaseNodeForm;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Override the node form.
 */
class NodeForm extends BaseNodeForm {

  /**
   * The moderation service.
   *
   * @var \Drupal\moderation\ModerationInterface
   */
  protected $moderation;

  /**
   * Track if this is a draft.
   *
   * @var bool
   */
  protected $isDraft = FALSE;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\moderation\ModerationInterface.
   *   The moderation service.
   */
  public function __construct(EntityManagerInterface $entity_manager, PrivateTempStoreFactory $temp_store_factory, ModerationInterface $moderation) {
    parent::__construct($entity_manager, $temp_store_factory);
    $this->moderation = $moderation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('user.private_tempstore'),
      $container->get('moderation.node_moderation')
    );
  }

  /**
   * Ensure proper node revision is used in the node form.
   *
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    parent::prepareEntity();

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getEntity();
    if (!$node->isNew() && $node->type->entity->isNewRevision() && $revision_id = $this->moderation->getDraftRevisionId($node)) {
      /** @var \Drupal\node\NodeStorage $storage */
      $storage = \Drupal::service('entity.manager')->getStorage('node');
      $this->entity = $storage->loadRevision($revision_id);
      $this->isDraft = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getEntity();
    if (!$node->isNew() && $node->type->entity->isNewRevision() && $node->isPublished()) {
      // Add a 'save as draft' action.
      $element['draft'] = $element['submit'];
      $element['draft']['#access'] = TRUE;
      $element['draft']['#dropbutton'] = 'save';
      $element['draft']['#value'] = $this->t('Save as draft');
      $element['draft']['#submit'][] = '::setRedirect';
      $element['draft']['#published_status'] = FALSE;
      $element['draft']['#is_draft'] = TRUE;

      // Put the draft button first.
      $element['draft']['#weight'] = -10;

      // If the user doesn't have 'administer nodes' permission, and this is
      // a published node in a type that defaults to being unpublished, then
      // only allow new drafts.
      if (!\Drupal::currentUser()->hasPermission('administer nodes') && $this->nodeTypeUnpublishedDefault()) {
        // We can't just set #access to false on submit as it's already hidden
        // by parent::actions(). Is there a better way to do this?
        if (isset($element['publish'])) {
          $element['publish']['#access'] = FALSE;
          unset($element['publish']['#dropbutton']);
        }
        if (isset($element['unpublish'])) {
          $element['unpublish']['#access'] = FALSE;
          unset($element['unpublish']['#dropbutton']);
        }
        unset($element['draft']['#dropbutton']);
      }
    }

    if ($this->isDraft && isset($element['unpublish'])) {
      $element['unpublish']['#submit'][] = '::setRedirect';
    }

    // If this is an existing draft, change the publish button text.
    if ($this->isDraft && isset($element['publish'])) {
      $element['publish']['#value'] = t('Save and publish');
    }

    return $element;
  }

  /**
   * Set default revision if this was previously a draft, and is now being
   * published.
   *
   * {@inheritdoc}
   */
  function updateStatus($entity_type_id, NodeInterface $node, array $form, FormStateInterface $form_state) {
    parent::updateStatus($entity_type_id, $node, $form, $form_state);

    $element = $form_state->getTriggeringElement();

    $is_published = (boolean) isset($element['#published_status']) ? $element['#published_status'] : FALSE;
    $is_draft_revision = (boolean) isset($element['#is_draft']) ? $element['#is_draft'] : $this->isDraft;
    $is_default_revision = ($is_published || !$is_draft_revision);

    $node->isDefaultRevision($is_default_revision);
  }

  /**
   * Set a redirect to the draft.
   */
  public function setRedirect(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect(
      'node.draft',
      ['node' => $this->getEntity()->id()]
    );
  }

  /**
   * Helper function to determine unpublished default for a node type.
   *
   * @return bool
   *   Returns TRUE if the current node type is set to unpublished by default.
   */
  protected function nodeTypeUnpublishedDefault() {
    $type = $this->getEntity()->getType();
    // @todo Make it possible to get default values without an entity.
    //   https://www.drupal.org/node/2318187
    $node = $this->entityManager->getStorage('node')->create(['type' => $type]);
    return !$node->isPublished();
  }

}
