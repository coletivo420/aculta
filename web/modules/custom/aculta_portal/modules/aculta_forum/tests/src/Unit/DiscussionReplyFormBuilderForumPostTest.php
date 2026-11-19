<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Service\DiscussionReplyAccess;
use Drupal\aculta_forum\Service\DiscussionReplyFormBuilder;
use Drupal\aculta_forum\Service\DiscussionThreadDepthResolver;
use Drupal\aculta_forum\Service\ForumQuoteBuilder;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that current Growroom replies use revisionable ForumPosts.
 */
#[CoversClass(DiscussionReplyFormBuilder::class)]
#[Group('aculta_forum')]
final class DiscussionReplyFormBuilderForumPostTest extends UnitTestCase {

  /**
   * A Growroom reply builds the public ForumPost form and never a Comment.
   */
  public function testForumTopicBuildsForumPostSubmissionForm(): void {
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getEntityTypeId')->willReturn('node');
    $topic->method('id')->willReturn('73');
    $topic->method('label')->willReturn('Tópico de teste');
    $account = $this->createMock(AccountInterface::class);
    $post = $this->createMock(EntityInterface::class);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())
      ->method('create')
      ->with(['topic' => '73', 'parent' => NULL])
      ->willReturn($post);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())
      ->method('getStorage')
      ->willReturnCallback(static function (string $entityType) use ($storage): EntityStorageInterface {
        self::assertSame('aculta_forum_post', $entityType);
        return $storage;
      });
    $entityFormBuilder = $this->createMock(EntityFormBuilderInterface::class);
    $entityFormBuilder->expects(self::once())
      ->method('getForm')
      ->with($post, 'public_submission')
      ->willReturn([
        'body' => [
          'widget' => [0 => ['value' => ['#default_value' => NULL]]],
        ],
      ]);
    $replyAccess = $this->createMock(DiscussionReplyAccess::class);
    $replyAccess->expects(self::once())
      ->method('checkAccess')
      ->with($topic, $account, NULL)
      ->willReturn(AccessResult::allowed());

    $builder = new DiscussionReplyFormBuilder(
      new DiscussionContextResolver([]),
      $entityTypeManager,
      $entityFormBuilder,
      $replyAccess,
      new ForumQuoteBuilder(),
      $this->createMock(DiscussionThreadDepthResolver::class),
    );

    $result = $builder->buildReplyForm($topic, $account, NULL, 'Trecho citado');

    self::assertSame('editando', $result['#status']);
    self::assertSame('Tópico de teste', $result['#host_title']);
    self::assertIsArray($result['#form'] ?? NULL);
    $form = $result['#form'];
    self::assertIsArray($form['body'] ?? NULL);
    $body = $form['body'];
    self::assertIsArray($body['widget'] ?? NULL);
    $widget = $body['widget'];
    self::assertIsArray($widget[0] ?? NULL);
    $firstWidget = $widget[0];
    self::assertIsArray($firstWidget['value'] ?? NULL);
    $value = $firstWidget['value'];
    self::assertSame('Trecho citado', $value['#value'] ?? NULL);
    self::assertIsArray($form['#attributes'] ?? NULL);
    $attributes = $form['#attributes'];
    self::assertSame('#aculta-reply-dialog-body', $attributes['data-hx-target'] ?? NULL);
  }

}
