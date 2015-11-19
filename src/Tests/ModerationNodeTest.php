<?php

/**
 * @file
 * Contains Drupal\moderation\Tests\ModerationNodeTest.
 */

namespace Drupal\moderation\Tests;

use Drupal\node\Entity\NodeType;
use Drupal\node\Tests\AssertButtonsTrait;
use Drupal\node\Tests\NodeTestBase;

/**
 * Tests Creation and editing of forward revisions of a node.
 *
 * @group moderation
 */
class ModerationNodeTest extends NodeTestBase {

  use AssertButtonsTrait;

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser, $normalUser;

  /**
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $nodeType;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'moderation', 'block'];

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Use revisions by default.
    $this->nodeType = NodeType::load('article');
    $this->nodeType->setNewRevision(TRUE);
    $this->nodeType->save();

    // Set unpublished by default.
    $fields = \Drupal::entityManager()->getFieldDefinitions('node', 'article');
    $fields['status']->getConfig('article')
      ->setDefaultValue(FALSE)
      ->save();

    // Set up users.
    $this->adminUser = $this->drupalCreateUser(['bypass node access', 'administer nodes']);
    $this->normalUser = $this->drupalCreateUser(['create article content', 'edit own article content', 'view all revisions']);
  }

  /**
   * Checks node edit functionality.
   */
  function testBasicForwarRevisions() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('node/add/article');

    // For new content, the 'Save as draft' button should not be present.
    $this->assertButtons([t('Save as unpublished'), t('Save and publish')]);

    // Create an unpublished node.
    $unpublished_one_values = [
      // While standard to use randomName() for these values, human-readable
      // text is easier to debug in this context.
      'title[0][value]' => 'Unpublished one title',
      'body[0][value]' => 'Unpublished one body',
    ];
    $this->drupalPostForm('node/add/article', $unpublished_one_values, t('Save as unpublished'));
    $node = $this->drupalGetNodeByTitle($unpublished_one_values['title[0][value]']);

    // Verify that the admin user can see the node's page.
    $this->drupalGet('node/' . $node->id());
    $this->assertResponse(200, t('Draft is accessible to the admin user.'));

    // There should not be a draft tab since there is only a single unpublished
    // revision.
    $this->assertNoLink(t('Draft'));

    // Make another revision and publish.
    $this->drupalGet('node/' . $node->id() .'/edit');
    $this->assertButtons([t('Save and keep unpublished'), t('Save and publish')]);
    $published_one_values = [
      'title[0][value]' => 'Published one title',
      'body[0][value]' => 'Published one title',
    ];
    $this->drupalPostForm('node/' . $node->id() . '/edit', $published_one_values, t('Save and publish'));

    // Verify published values are on the node page.
    $this->drupalGet('node/' . $node->id());
    $this->assertText($published_one_values['title[0][value]'], 'Published title found');
    $this->assertText($published_one_values['body[0][value]'], t('Published body found'));

    // There should still be no draft tab since the latest revision is
    // published.
    $this->assertNoLink(t('Draft'));

    // Create a draft.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertButtons([t('Save as draft'), t('Save and keep published'), t('Save and unpublish')]);
    $draft_one_values = [
      'title[0][value]' => 'Draft one title',
      'body[0][value]' => 'Draft one body',
    ];
    $this->drupalPostForm('node/' . $node->id() . '/edit', $draft_one_values, t('Save as draft'));

    // The user should be redirected to the draft.
    $this->assertUrl('node/' . $node->id() . '/draft', [], 'User is redirected to view the draft after saving.');

    // Now there should be a draft tab.
    $this->assertLink(t('Draft'));

    // Verify that the admin user can see the node's page.
    $this->drupalGet('node/' . $node->id());
    $this->assertText($published_one_values['title[0][value]'], 'The published title stays the same when a new draft is created.');
    $this->assertText($published_one_values['body[0][value]'], 'The published body stays the same when a new draft is created.');

    // The draft should be loaded in the edit form.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertRaw($draft_one_values['title[0][value]'], 'The draft title is loaded on the edit form.');
    $this->assertRaw($draft_one_values['body[0][value]'], 'The draft body is loaded on the edit form.');
    $this->assertButtons([t('Save and keep unpublished'), t('Save and publish')]);
    $this->drupalPostForm('node/' . $node->id() . '/edit', $draft_one_values, t('Save and publish'));

    // Publish the draft.
    $this->drupalGet('node/' . $node->id());
    $this->assertText($draft_one_values['title[0][value]'], 'Published title found');
    $this->assertText($draft_one_values['body[0][value]'], 'Published body found');

    // For normal users (eg, users without the administer nodes permission), if
    // a content type is set to be unpublished by default, then on edits, only
    // allow new drafts to be created, rather than allowing the published node
    // to be updated.
    $this->drupalLogin($this->normalUser);
    $this->drupalGet('node/add/article');
    $this->assertButtons([t('Save')], FALSE);

    // Create a node and publish.
    $this->drupalPostForm('node/add/article', $published_one_values, t('Save'));
    $node = $this->drupalGetNodeByTitle($published_one_values['title[0][value]']);
    $node->setPublished(TRUE)->save();

    // Edit the node.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertButtons([t('Save as draft')], FALSE);
    $edit = [
      'title[0][value]' => 'Draft one title',
      'body[0][value]' => 'Draft one body',
    ];
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save as draft'));
    $this->assertLink(t('Draft'));

    // User with view, but not update, permissions, should not see the draft
    // tab.
    $this->drupalLogout();
    $this->drupalGet('node/' . $node->id());
    $this->assertNoLink(t('Draft'), 'The draft tab does not appear for users without update access.');

    // And should not be able to access it directly either.
    $this->drupalGet('node/' . $node->id() . '/draft');
    $this->assertResponse(403, 'Access is denied for the draft page for users without update access.');
  }

}

