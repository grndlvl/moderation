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
    $has_translations = (count($languages) > 1);
    $langcode = $this->languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $langname = $this->languageManager()->getLanguageName($langcode);

    return array(
      '#title' => $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $node->label()]) : $this->t('Revisions for %title', ['%title' => $node->label()]),
      'node_revisions_table' => array(
        '#theme' => 'table',
        '#rows' => $this->getRevisionRows($node, $languages, $has_translations, $langcode, $langname),
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

  protected function getRevisionRows(NodeInterface $node, $languages, $has_translations, $langcode, $langname) {
    $rows = array();
    $account = $this->currentUser();
    $node_storage = $this->entityManager()->getStorage('node');
    $type = $node->getType();

    $revert_permission = (($account->hasPermission("revert $type revisions") || $account->hasPermission('revert all revisions') || $account->hasPermission('administer nodes')) && $node->access('update'));
    $delete_permission =  (($account->hasPermission("delete $type revisions") || $account->hasPermission('delete all revisions') || $account->hasPermission('administer nodes')) && $node->access('delete'));

    $latest_revision = TRUE;

    $vids = $node_storage->revisionIds($node);
    foreach (array_reverse($vids) as $vid) {
      $rows[] = $this->getRevisionRow($vid, $langcode, $has_translations, $node_storage, $revert_permission, $delete_permission, $node, $latest_revision);
    }
    return $rows;
  }

  protected function getRevisionRow($vid, $langcode, $has_translations, $node_storage, $revert_permission, $delete_permission, $node, &$latest_revision) {
    /** @var \Drupal\node\NodeInterface $revision */
    $revision = $node_storage->loadRevision($vid);
    if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
      $row[] = $this->getRevisionColumnRevision($revision, $node, $vid);
      $row[] = $this->getRevisionColumnStatus();
      $row[] = $this->getRevisionColumnOperations($latest_revision, $node, $has_translations, $vid, $langcode, $revert_permission, $delete_permission);
    }
    return $row;
  }

  protected function getRevisionColumnRevision($revision, $node, $vid) {
    $username = [
      '#theme' => 'username',
      '#account' => $revision->getRevisionAuthor(),
    ];

    // Use revision link to link to revisions that are not active.
    $date = $this->dateFormatter->format($revision->revision_timestamp->value, 'short');
    if ($vid != $node->getRevisionId()) {
      $link = $this->l($date, new Url('entity.node.revision', ['node' => $node->id(), 'node_revision' => $vid]));
    }
    else {
      $link = $node->link($date);
    }

    $column = [
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
    // @todo Simplify once https://www.drupal.org/node/2334319 lands.
    $this->renderer->addCacheableDependency($column['data'], $username);
    return $column;
  }

  protected function getRevisionColumnStatus() {
    return 'moo';
  }

  protected function getRevisionColumnOperations(&$latest_revision, $node, $has_translations, $vid, $langcode, $revert_permission, $delete_permission) {
    $column = array();
    if ($latest_revision) {
      $column = [
        'data' => [
          '#prefix' => '<em>',
          '#markup' => $this->t('Current revision'),
          '#suffix' => '</em>',
        ],
      ];
      $latest_revision = FALSE;
    }
    else {
      $links = [];
      if ($revert_permission) {
        $links['revert'] = [
          'title' => $this->t('Revert'),
          'url' => $has_translations ?
            Url::fromRoute('node.revision_revert_translation_confirm', ['node' => $node->id(), 'node_revision' => $vid, 'langcode' => $langcode]) :
            Url::fromRoute('node.revision_revert_confirm', ['node' => $node->id(), 'node_revision' => $vid]),
        ];
      }

      if ($delete_permission) {
        $links['delete'] = [
          'title' => $this->t('Delete'),
          'url' => Url::fromRoute('node.revision_delete_confirm', ['node' => $node->id(), 'node_revision' => $vid]),
        ];
      }

      $column = array('data' => array(
        '#type' => 'operations',
        '#links' => $links,
      ));
    }

    return $column;
  }
}
