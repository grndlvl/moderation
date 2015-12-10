<?php

/**
 * @file
 * Contains \Drupal\moderation\Tests\NodeRevisionsUiTest.
 */

namespace Drupal\moderation\Tests;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\Tests\AssertButtonsTrait;
use Drupal\node\Tests\NodeTestBase;

/**
 * Tests the UI for controlling node revision behavior.
 *
 * @group node
 */
class ModerationNodeRevisionsUiTest extends NodeTestBase {

  use AssertButtonsTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'moderation', 'block'];

  protected $nid_with_revisions = 0;

  protected $field_values = array();

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');

    // Use revisions by default.
    $this->nodeType = NodeType::load('article');
    $this->nodeType->setNewRevision(TRUE);
    $this->nodeType->save();

    // Set unpublished by default.
    $fields = \Drupal::entityManager()->getFieldDefinitions('node', 'article');
    $fields['status']->getConfig('article')
      ->setDefaultValue(FALSE)
      ->save();

    $this->editor = $this->drupalCreateUser([
      'administer nodes',
      'create article content',
      'edit any article content',
      'view article revisions',
      'access user profiles',
    ]);

    $this->setFieldValues();
    $this->createRevisions();
  }

  protected function setFieldValues() {
    $this->field_values['inital_revision'] = [
      'title[0][value]' => $this->randomMachineName(8),
      'body[0][value]' => $this->randomMachineName(16),
    ];
    $this->field_values['draft_revision'] = [
      'title[0][value]' => $this->randomMachineName(8),
      'body[0][value]' => $this->randomMachineName(16),
    ];
    return $this;
  }

  protected function createRevisions() {
    $this->drupalLogin($this->editor);

    // Create a node.
    $edit = $this->field_values['inital_revision'];
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);
    $edit = $this->field_values['draft_revision'];
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save as draft'));

    $this->nid_with_revisions = $node->id();
  }

  public function testRevisionsLinkIsOnNodeViewPage() {
    $this->drupalGet('node/' . $this->nid_with_revisions);
    $this->assertLink(t('Revisions'));
  }

  public function testRevisionsPageIsAccessible() {
    $this->drupalGet('node/' . $this->nid_with_revisions . '/revisions');
    $this->assertResponse(200, t('Revisions overview page is available.'));
  }

  public function testRevisionsPageHasCurrentAndDraft() {
    $node = Node::load($this->nid_with_revisions);

    $this->drupalGet('node/' . $this->nid_with_revisions . '/revisions');
    $this->assertRaw(t('Draft'), 'Has a revision set to Draft.');
    $this->assertLinkByHref("node/{$this->nid_with_revisions}/edit");
    $this->assertRaw(t('Current'), 'Has a currently published revision.');
    $this->assertLinkByHref("node/{$this->nid_with_revisions}/revisions/{$node->getRevisionId()}/revert");
  }

}
