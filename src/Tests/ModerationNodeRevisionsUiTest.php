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

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'moderation', 'block'];

  protected $nid_with_revisions = 0;

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

    $this->createRevisions();
  }

  protected function createRevisions() {
    $this->drupalLogin($this->editor);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = $this->randomMachineName(8);
    $edit['body[0][value]'] = $this->randomMachineName(16);
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $node = $this->drupalGetNodeByTitle($edit['title[0][value]']);
    $edit['title[0][value]'] = $this->randomMachineName(8);
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save as draft'));

    $this->nid_with_revisions = $node->id();
  }

  function testRevisionsLinkIsOnNodeViewPage() {
    $this->drupalGet('node/' . $this->nid_with_revisions);
    $this->assertLink(t('Revisions'));
  }

  function testRevisionsPageIsAccessible() {
    $this->drupalGet('node/' . $this->nid_with_revisions . '/revisions');
    $this->assertResponse(200, t('Revisions overview page is available.'));
  }

}
