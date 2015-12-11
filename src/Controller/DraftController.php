<?php
/**
 * @file
 * Contains \Drupal\moderation\Controller\DraftController.
 */

namespace Drupal\moderation\Controller;

use Drupal\Core\Url;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\Controller\NodeController;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\moderation\ModerationInterface;

/**
 * Page controller for viewing node drafts.
 */
class DraftController extends NodeController {

  /**
   * The moderation service.
   *
   * @var \Drupal\moderation\ModerationInterface
   */
  protected $moderation;

  /**
   * Stores the current users revision permissions while building the revisions
   * overview table.
   */
  protected $revisionPermissions = array('revert' => FALSE, 'delete' => FALSE);

  /**
   * Determines if the current revision has translations while building the
   * revisions overview table.
   */
  protected $hasTranslations = FALSE;

  /**
   * Stores the current draft revision while building the revisions overview
   * Table.
   */
  protected $draftRevision;

  /**
   * Constructs a NodeController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\moderation\ModerationInterface.
   *   The moderation service.
   */
  public function __construct(DateFormatterInterface $date_formatter, RendererInterface $renderer, ModerationInterface $moderation) {
    parent::__construct($date_formatter, $renderer);
    $this->moderation = $moderation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('renderer'),
      $container->get('moderation.node_moderation')
    );
  }

  /**
   * Display current revision denoted as a draft.
   *
   * @param \Drupal\node\NodeInterface
   *   The current node.
   */
  public function show(NodeInterface $node) {
    return $this->revisionShow($this->moderation->getDraftRevisionId($node));
  }

  /**
   * Display the title of the draft.
   */
  public function draftPageTitle(NodeInterface $node) {
    return $this->revisionPageTitle($this->moderation->getDraftRevisionId($node));
  }

  /**
   * {@inheritdoc}
   */
  public function revisionOverview(NodeInterface $node) {
    $languages = $node->getTranslationLanguages();
    $this->hasTranslations = (count($languages) > 1);
    $this->draftRevision = $this->moderation->getDraftRevisionId($node);

    $langcode = $this->languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $langname = $this->languageManager()->getLanguageName($langcode);

    return array(
      '#title' => $this->hasTranslations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $node->label()]) : $this->t('Revisions for %title', ['%title' => $node->label()]),
      'node_revisions_table' => array(
        '#theme' => 'table',
        '#rows' => $this->getRevisionRows($node, $langcode),
        '#header' => $this->getRevisionHeaders(),
        '#attached' => array(
          'library' => array('node/drupal.node.admin'),
        ),
      )
    );
  }

  /**
   * Retreive the table headers for the revision overview page.
   *
   * @return array
   *  An array of headers to be used as the '#header' for theme_table().
   */
  protected function getRevisionHeaders() {
    return array($this->t('Revision'), $this->t('State'), $this->t('Operations'));
  }

  /**
   * Retreive the table rows for the revision overview page.
   *
   * @param NodeInterface $node
   *   The node for which to retrieve the revision row.
   * @param string $langcode
   *   The language code of the translation to get or
   *   LanguageInterface::LANGCODE_DEFAULT to get the data in default language.
   *
   * @return array
   *   A render array for each row of the revision overview table.
   */
  protected function getRevisionRows(NodeInterface $node, $langcode) {
    $rows = array();
    $account = $this->currentUser();
    $this->setRevisionPermissions($node, $account);

    $vids = $this->entityManager()->getStorage('node')->revisionIds($node);
    foreach (array_reverse($vids) as $vid) {
      $rows[] = $this->getRevisionRow($node, $vid, $langcode);
    }
    return $rows;
  }

  /**
   * Set the current user permissions for the user viewing the revision table.
   *
   * @param NodeInterface $node
   *   The node for which to retrieve the revision row.
   * @param AccountProxyInterface $account
   *   The user object for which to check and set the revision permissions.
   *
   * @return array
   *   An array that contains the specified users access for 'revert' and 'delete'.
   */
  protected function setRevisionPermissions(NodeInterface $node, AccountProxyInterface $account) {
    $type = $node->getType();
    $this->revisionPermissions = array(
      'revert' => (($account->hasPermission("revert $type revisions") || $account->hasPermission('revert all revisions') || $account->hasPermission('administer nodes')) && $node->access('update')),
      'delete' => (($account->hasPermission("delete $type revisions") || $account->hasPermission('delete all revisions') || $account->hasPermission('administer nodes')) && $node->access('delete')),
    );
  }

  /**
   * Retrieve a single revision row for the revisions overview table.
   *
   * @param NodeInterface $node
   *   The node for which to retrieve the revision row.
   * @param integer $vid
   *   The current revision id for which to retrieve the revision row.
   * @param string $langcode
   *   The language code of the translation to get or
   *   LanguageInterface::LANGCODE_DEFAULT to get the data in default language.
   *
   * @param array
   *   A render array with each column of the revision row.
   */
  protected function getRevisionRow(NodeInterface $node, $vid, $langcode) {
    $row = [];
    /** @var \Drupal\node\NodeInterface $revision */
    $revision = $this->entityManager()->getStorage('node')->loadRevision($vid);
    if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
      $row_class = $this->getRevisionRowClass($revision);
      $row[] = $this->getRevisionColumnRevision($revision, $node, $vid) + $row_class;
      $row[] = $this->getRevisionColumnStatus($revision) + $row_class;
      $row[] = $this->getRevisionColumnOperations($revision, $node, $langcode) + $row_class;
    }
    return $row;
  }

  /**
   * Retreive the row class for the specified revision.
   *
   * @param NodeInterface $revision
   *   The revision for which to retrieve the revision row class.
   *
   * @return array
   *   The class to append to the revision row.
   */
  protected function getRevisionRowClass(NodeInterface $revision) {
    $row_class = [];
    if ($this->draftRevision === $revision->getRevisionID()) {
      $row_class = ['class' => 'revision-current'];
    }
    elseif ($revision->isDefaultRevision()) {
      $row_class = ['class' => 'color-success'];
    }
    return $row_class;
  }

  /**
   * Retrieve the revision column content for the specified revision.
   *
   * @param NodeInterface $revision
   *   The revision for which to retrieve the revision column content.
   * @param NodeInterface $node
   *   The node for which to retrieve the revision column content.
   *
   * @return array
   *   The render array that contains the revision column content for the
   *   revision row.
   */
  protected function getRevisionColumnRevision(NodeInterface $revision, NodeInterface $node) {
    $username = [
      '#theme' => 'username',
      '#account' => $revision->getRevisionAuthor(),
    ];

    // Use revision link to link to revisions that are not active.
    $date = $this->dateFormatter->format($revision->revision_timestamp->value, 'short');
    if ($revision->id() != $node->getRevisionId()) {
      $link = $this->l($date, new Url('entity.node.revision', ['node' => $node->id(), 'node_revision' => $revision->getRevisionId()]));
    }
    else {
      $link = $node->link($date);
    }

    $column = $this->buildRevisionColumnRevision($revision, $link, $username);
    // @todo Simplify once https://www.drupal.org/node/2334319 lands.
    $this->renderer->addCacheableDependency($column['data'], $username);
    return $column;
  }

  /**
   * Build the revision column content for the specified revision.
   *
   * @param NodeInterface $revision
   *   The revision for which to retrieve the revision column content.
   * @param string $link
   *   The date link for the revision.
   * @param array $username
   *   The render array for the author of the revision.
   *
   * @return array
   *   The render array that contains the revision column content for the
   *   revision row.
   */
  protected function buildRevisionColumnRevision(NodeInterface $revision, $link, $username) {
    return [
      'data' => [
        '#type' => 'inline_template',
        '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
        '#context' => [
          'date' => $link,
          'username' => $this->renderer->renderPlain($username),
          'message' => ['#markup' => $revision->revision_log->value, '#allowed_tags' => Xss::getHtmlTagList()],
        ],
      ],
    ];
  }

  /**
   * Retrieve the status column content for the specified revision.
   *
   * @param NodeInterface $revision
   *   The revision for which to retrieve the status column content.
   *
   * @return array
   *   The render array that contains the status column content for the
   *   revision row.
   */
  protected function getRevisionColumnStatus(NodeInterface $revision) {
    $text = '';

    if ($this->draftRevision === $revision->getRevisionID()) {
      $text = $this->t('Draft');
    }
    elseif ($revision->isDefaultRevision()) {
      $text = $this->t('Current');
    }
    elseif ($revision->isPublished()) {
      $text = $this->t('Archived from published');
    }

    return [
      'data' => [
        '#markup' => $text,
      ],
    ];
  }

  /**
   * Retrieve the operations column content for the specified revision.
   *
   * @param NodeInterface $revision
   *   The revision for which to retrieve the operations column content.
   * @param NodeInterface $node
   *   The node for which to retrieve the operations column content.
   * @param string $langcode
   *   The language code of the translation to get or
   *   LanguageInterface::LANGCODE_DEFAULT to get the data in default language.
   *
   * @return array
   *   The render array that contains the status column content for the
   *   revision row.
   */
  protected function getRevisionColumnOperations(NodeInterface $revision, NodeInterface $node, $langcode) {
    $vid = $revision->getRevisionId();

    $links = [];
    if ($vid === $this->draftRevision || (!$this->draftRevision && $vid === $node->getRevisionId())) {
      $links['edit'] = $this->buildREvisionColumnOperationEdit($node);
    }
    elseif ($this->revisionPermissions['revert']) {
      $links['revert'] = $this->buildRevisionColumnOperationsRevert($revision, $node, $langcode);
    }

    if ($this->revisionPermissions['delete']) {
      $links['delete'] = $this->buildRevisionColumnOperationsDelete($revision, $node, $langcode);
    }

    $column = array('data' => array(
      '#type' => 'operations',
      '#links' => $links,
    ));

    return $column;
  }

  /**
   * Build the edit operations link for the specified revision.
   *
   * @param NodeInterface $node
   *   The node for which to build the edit operation link.
   *
   * @return array
   *   The render array contents for the edit link operation.
   */
  protected function buildREvisionColumnOperationEdit(NodeInterface $node) {
    return [
      'title' => $this->t('Edit'),
      'url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]),
    ];
  }

  /**
   * Build the revert operations link for the specified revision.
   *
   * @param NodeInterface $revision
   *   The revision for which to build the revert operation link.
   * @param NodeInterface $node
   *   The node for which to build the revert operation link.
   * @param string $langcode
   *   The language code of the translation to get or
   *   LanguageInterface::LANGCODE_DEFAULT to get the data in default language.
   *
   * @return array
   *   The render array contents for the revert link operation.
   */
  protected function buildRevisionColumnOperationsRevert(NodeInterface $revision, NodeInterface $node, $langcode) {
    return [
      'title' => $this->t('Revert'),
      'url' => $this->hasTranslations ?
        Url::fromRoute('node.revision_revert_translation_confirm', ['node' => $node->id(), 'node_revision' => $revision->getRevisionId(), 'langcode' => $langcode]) :
        Url::fromRoute('node.revision_revert_confirm', ['node' => $node->id(), 'node_revision' => $revision->getRevisionId()]),
    ];
  }

  /**
   * Build the revert operations link for the specified revision.
   *
   * @param NodeInterface $revision
   *   The revision for which to build the delete operation link.
   * @param NodeInterface $node
   *   The node for which to build the delete operation link.
   * @param string $langcode
   *   The language code of the translation to get or
   *   LanguageInterface::LANGCODE_DEFAULT to get the data in default language.
   *
   * @return array
   *   The render array contents for the delete link operation.
   */
  protected function buildRevisionColumnOperationsDelete(NodeInterface $revision, NodeInterface $node, $langcode) {
    return [
      'title' => $this->t('Delete'),
      'url' => Url::fromRoute('node.revision_delete_confirm', ['node' => $node->id(), 'node_revision' => $revision->getRevisionId()]),
    ];
  }

}
