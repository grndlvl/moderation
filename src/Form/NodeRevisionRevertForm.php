<?php

/**
 * @file
 * Contains \Drupal\node\Form\NodeRevisionRevertForm.
 */

namespace Drupal\moderation\Form;

use Drupal\node\Form\NodeRevisionRevertForm as FormBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for reverting a node revision.
 */
class NodeRevisionRevertForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  protected function prepareRevertedRevision(NodeInterface $revision, FormStateInterface $form_state) {
    $revision->setNewRevision();
    $revision->setChangedTime(REQUEST_TIME);
    $revision->isDefaultRevision(FALSE);
    $revision->setPublished(FALSE);

    return $revision;
  }

}
