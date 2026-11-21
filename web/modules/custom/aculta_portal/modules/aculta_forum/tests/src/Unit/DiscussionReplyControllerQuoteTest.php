<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Controller\DiscussionReplyController;
use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginClassification;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\aculta_forum\Service\DiscussionReplyAccess;
use Drupal\aculta_forum\Service\DiscussionReplyFormBuilder;
use Drupal\aculta_forum\Service\ForumQuoteBuilder;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;

/**
 * Checks that untrusted quote IDs cannot read comments from another context.
 */
#[Group('aculta_forum')]
final class DiscussionReplyControllerQuoteTest extends UnitTestCase {

  /**
   * A Comment on a different host must not be read or quoted.
   */
  public function testQuoteFromAnotherHostIsIgnored(): void {
    $source = $this->sourceComment(99, TRUE);
    $source->expects(self::never())->method('get');

    $this->assertSame(['#markup' => 'composer sem citação'], $this->composer($source));
  }

  /**
   * Comment access must be checked even when the host ID matches.
   */
  public function testQuoteWithoutSourceViewAccessIsIgnored(): void {
    $source = $this->sourceComment(23, FALSE);
    $source->expects(self::never())->method('get');

    $this->assertSame(['#markup' => 'composer sem citação'], $this->composer($source));
  }

  /**
   * Builds a controller with a readable destination but untrusted quote ID.
   *
   * @return array<mixed>
   *   The composer render array.
   */
  private function composer(CommentInterface $source): array {
    $host = $this->createMock(EntityInterface::class);
    $host->method('getEntityTypeId')->willReturn('node');
    $host->method('id')->willReturn('23');

    $hostStorage = $this->createMock(EntityStorageInterface::class);
    $hostStorage->method('load')->willReturn($host);
    $commentStorage = $this->createMock(EntityStorageInterface::class);
    $commentStorage->method('load')->willReturn($source);

    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnCallback(
      static fn (string $type): EntityStorageInterface => $type === 'comment' ? $commentStorage : $hostStorage,
    );

    $account = $this->createMock(AccountProxyInterface::class);
    $builder = $this->createMock(DiscussionReplyFormBuilder::class);
    $builder->expects(self::once())->method('buildReplyForm')
      ->with($host, $account, NULL, NULL)
      ->willReturn(['#markup' => 'composer sem citação']);

    $controller = new DiscussionReplyController(
      $manager,
      $builder,
      $this->createMock(DiscussionReplyAccess::class),
      $this->originResolver(),
      new ForumQuoteBuilder(),
      $account,
      $this->createMock(KeyValueFactoryInterface::class),
    );
    return $controller->composer('node', '23', Request::create('/discussoes/compositor/node/23?citar=42'));
  }

  /**
   * Creates a resolver for the contextual host in the quote fixture.
   */
  private function originResolver(): DiscussionOriginResolverInterface {
    $resolver = $this->createMock(DiscussionOriginResolverInterface::class);
    $resolver->method('classify')->willReturn(
      DiscussionOriginClassification::from(DiscussionOrigin::CONTEXTUAL, new CacheableMetadata()),
    );
    return $resolver;
  }

  /**
   * Creates a published shared Comment with a specified source host and ACL.
   */
  private function sourceComment(int $hostId, bool $viewAllowed): CommentInterface&MockObject {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getFieldName')->willReturn('field_aculta_discussion');
    $comment->method('getCommentedEntityTypeId')->willReturn('node');
    $comment->method('getCommentedEntityId')->willReturn($hostId);
    $comment->method('isPublished')->willReturn(TRUE);
    $comment->method('access')->willReturn($viewAllowed);
    return $comment;
  }

}
