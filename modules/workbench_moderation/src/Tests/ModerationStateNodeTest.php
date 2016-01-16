<?php

/**
 * @file
 * Contains \Drupal\workbench_moderation\Tests\ModerationStateNodeTest.
 */

namespace Drupal\workbench_moderation\Tests;

use Drupal\Core\Url;

/**
 * Tests general content moderation workflow for nodes..
 *
 * @group workbench_moderation
 */
class ModerationStateNodeTest extends ModerationStateTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
    $this->createContentTypeFromUI('Moderated content', 'moderated_content', TRUE, [
      'draft',
      'needs_review',
      'published'
    ], 'draft');
    $this->grantUserPermissionToCreateContentOfType($this->adminUser, 'moderated_content');
  }

  /**
   * Tests creating content.
   */
  public function testCreatingContent() {
    $this->drupalPostForm('node/add/moderated_content', [
      'title[0][value]' => 'moderated content',
    ], t('Save as Draft'));
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'title' => 'moderated content',
      ]);

    if (!$nodes) {
      $this->fail('Test node was not saved correctly.');
      return;
    }

    $node = reset($nodes);

    $path = 'node/' . $node->id() . '/edit';
    // Set up needs review revision.
    $this->drupalPostForm($path, [], t('Save and transition to Needs Review'));
    // Set up published revision.
    $this->drupalPostForm($path, [], t('Save and transition to Published'));
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
    /* @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
    $this->assertTrue($node->isPublished());
  }

  /**
   * Tests edit form destinations.
   */
  public function testFormSaveDestination() {
    // Create new moderated content in draft.
    $this->drupalPostForm('node/add/moderated_content', [
      'title[0][value]' => 'Some moderated content',
      'body[0][value]' => 'First version of the content.',
    ], t('Save as Draft'));

    $node = $this->drupalGetNodeByTitle('Some moderated content');
    $edit_path = sprintf('node/%d/edit', $node->id());

    // After saving, we should be at the canonical URL and viewing the first
    // revision.
    $this->assertUrl(Url::fromRoute('entity.node.canonical', ['node' => $node->id()]));
    $this->assertText('First version of the content.');

    // Make a new forward revision; after saving, we should be on the "Latest
    // version" tab.
    $this->drupalPostForm($edit_path, [
      'body[0][value]' => 'Second version of the content.',
    ], t('Save and transition to Needs Review'));
    $this->assertUrl(Url::fromRoute('entity.node.latest_version', ['node' => $node->id()]));
    $this->assertText('Second version of the content.');

    // Make a new published revision; after saving, we should be at the
    // canonical URL.
    $this->drupalPostForm($edit_path, [
      'body[0][value]' => 'Third version of the content.',
    ], t('Save and transition to Published'));
    $this->assertUrl(Url::fromRoute('entity.node.canonical', ['node' => $node->id()]));
    $this->assertText('Third version of the content.');

    // Make a new forward revision; after saving, we should once again be on the
    // "Latest version" tab.
    $this->drupalPostForm($edit_path, [
      'body[0][value]' => 'Fourth version of the content.',
    ], t('Save and create new revision in Draft'));
    $this->assertUrl(Url::fromRoute('entity.node.latest_version', ['node' => $node->id()]));
    $this->assertText('Fourth version of the content.');
  }

}
