<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\comment\CommentingStatus;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves one Comment field storage can serve public topics and publications.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class SharedDiscussionFieldKernelTest extends KernelTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'comment',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['system', 'filter', 'node', 'comment']);

    foreach (['article', 'blog', 'aculta_forum_topic'] as $bundle) {
      NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
      $this->addDefaultCommentField(
        'node',
        $bundle,
        'field_aculta_discussion',
        CommentingStatus::Open,
        'aculta_discussion_post',
      );
    }
  }

  /**
   * One field storage can be attached to three Node bundles.
   */
  public function testSharedFieldStorageIsAttachedToEachNodeBundle(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('field_storage_config')
      ->load('node.field_aculta_discussion');

    self::assertNotNull($storage);
    self::assertSame('comment', $storage->getType());
    self::assertSame('aculta_discussion_post', $storage->getSetting('comment_type'));

    foreach (['article', 'blog', 'aculta_forum_topic'] as $bundle) {
      $field = $this->container->get('entity_type.manager')
        ->getStorage('field_config')
        ->load("node.$bundle.field_aculta_discussion");

      self::assertNotNull($field, sprintf('Comment field is attached to the %s bundle.', $bundle));
      self::assertSame($storage->id(), $field->getFieldStorageDefinition()->id());
    }
  }

}
