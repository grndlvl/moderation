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

  protected $revisionPermissions = array('revert' => FALSE, 'delete' => FALSE);

  protected $hasTranslations = FALSE;

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
   * Generates an overview table of older revisions of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node object.
   *
   * @return array
   *   An array as expected by drupal_render().
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

  protected function getRevisionHeaders() {
    return array($this->t('Revision'), $this->t('State'), $this->t('Operations'));
  }

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

  protected function setRevisionPermissions(NodeInterface $node, $account) {
    $type = $node->getType();
    $this->revisionPermissions = array(
      'revert' => (($account->hasPermission("revert $type revisions") || $account->hasPermission('revert all revisions') || $account->hasPermission('administer nodes')) && $node->access('update')),
      'delete' => (($account->hasPermission("delete $type revisions") || $account->hasPermission('delete all revisions') || $account->hasPermission('administer nodes')) && $node->access('delete')),
    );
  }

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

  protected function getRevisionRowClass($revision) {
    $row_class = [];
    if ($this->draftRevision === $revision->getRevisionID()) {
      $row_class = ['class' => 'revision-current'];
    }
    elseif ($revision->isDefaultRevision()) {
      $row_class = ['class' => 'color-success'];
    }
    return $row_class;
  }

  protected function getRevisionColumnRevision($revision, $node) {
    $username = [
      '#theme' => 'username',
      '#account' => $revision->getRevisionAuthor(),
    ];

    // Use revision link to link to revisions that are not active.
    $date = $this->dateFormatter->format($revision->revision_timestamp->value, 'short');
    if ($vid != $node->getRevisionId()) {
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

  protected function buildRevisionColumnRevision($revision, $link, $username) {
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

  protected function getRevisionColumnStatus($revision) {
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

  protected function getRevisionColumnOperations($revision, $node, $langcode) {
    $vid = $revision->getRevisionId();
    $column = array();

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

  protected function buildREvisionColumnOperationEdit($node) {
    return [
      'title' => $this->t('Edit'),
      'url' => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]),
    ];
  }

  protected function buildRevisionColumnOperationsRevert($revision, $node, $langcode) {
    return [
      'title' => $this->t('Set as draft'),
      'url' => $this->hasTranslations ?
        Url::fromRoute('node.revision_revert_translation_confirm', ['node' => $node->id(), 'node_revision' => $revision->getRevisionId(), 'langcode' => $langcode]) :
        Url::fromRoute('node.revision_revert_confirm', ['node' => $node->id(), 'node_revision' => $revision->getRevisionId()]),
    ];
  }

  protected function buildRevisionColumnOperationsDelete($revision, $node, $langcode) {
    return [
      'title' => $this->t('Delete'),
      'url' => Url::fromRoute('node.revision_delete_confirm', ['node' => $node->id(), 'node_revision' => $revision->getRevisionId()]),
    ];
  }

}
