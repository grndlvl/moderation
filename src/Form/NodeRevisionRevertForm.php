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

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Set as draft');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to set the revision from %revision-date as draft?', ['%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime())]);
  }

}
