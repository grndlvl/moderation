<?php
/**
 * @file
 * Contains \Drupal\moderation\Controller\DraftController.
 */

namespace Drupal\moderation\Controller;

use Drupal\moderation\ModerationInterface;
use Drupal\node\Controller\NodeController;
use Drupal\node\NodeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

}
