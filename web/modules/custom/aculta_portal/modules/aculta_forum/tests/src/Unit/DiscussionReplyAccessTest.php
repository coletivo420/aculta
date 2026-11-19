<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Context\DiscussionContextInterface;
use Drupal\aculta_forum\Context\DiscussionContextProviderInterface;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Service\DiscussionReplyAccess;
use Drupal\comment\CommentingStatus;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DiscussionReplyAccess::class)]
#[Group('aculta_forum')]
final class DiscussionReplyAccessTest extends UnitTestCase {

  /**
   * A historical Growroom host is never writable through Comment APIs.
   */
  public function testHistoricalTopicCannotReceiveStandardComments(): void {
    $host = $this->createMock(ContentEntityInterface::class);
    $host->method('getCacheContexts')->willReturn([]);
    $host->method('getCacheTags')->willReturn([]);
    $host->method('getCacheMaxAge')->willReturn(-1);
    $host->method('hasField')->willReturnCallback(
      static fn (string $name): bool => $name === 'field_autor_legado',
    );
    $archive = $this->createMock(FieldItemListInterface::class);
    $archive->method('isEmpty')->willReturn(FALSE);
    $host->method('get')->with('field_autor_legado')->willReturn($archive);

    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->expects(self::never())->method('supports');

    $access = new DiscussionReplyAccess(
      new DiscussionContextResolver([$provider]),
      $this->createMock(EntityTypeManagerInterface::class),
    );
    self::assertFalse($access->checkAccess($host, $this->createMock(AccountInterface::class))->isAllowed());
  }

  public function testCheckAccessForbiddenWhenNoContextResolved(): void {
    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->method('supports')->willReturn(FALSE);

    $resolver = new DiscussionContextResolver([$provider]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $host = $this->createMock(ContentEntityInterface::class);
    $host->method('getCacheContexts')->willReturn([]);
    $host->method('getCacheTags')->willReturn([]);
    $host->method('getCacheMaxAge')->willReturn(-1);

    $account = $this->createMock(AccountInterface::class);

    $access = new DiscussionReplyAccess($resolver, $entityTypeManager);
    $result = $access->checkAccess($host, $account);

    $this->assertFalse($result->isAllowed());
  }

  public function testCheckAccessAllowedWhenContextReadAndWriteAllowed(): void {
    $host = $this->createMock(ContentEntityInterface::class);
    $host->method('getCacheContexts')->willReturn([]);
    $host->method('getCacheTags')->willReturn([]);
    $host->method('getCacheMaxAge')->willReturn(-1);

    $account = $this->createMock(AccountInterface::class);

    $context = $this->createMock(DiscussionContextInterface::class);
    $context->method('getReadAccess')->willReturn(AccessResult::allowed());
    $context->method('getWriteAccess')->willReturn(AccessResult::allowed());
    $context->method('getCacheContexts')->willReturn([]);
    $context->method('getCacheTags')->willReturn([]);
    $context->method('getCacheMaxAge')->willReturn(-1);

    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->method('supports')->with($host)->willReturn(TRUE);
    $provider->method('getContext')->with($host, $account)->willReturn($context);

    $resolver = new DiscussionContextResolver([$provider]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $fieldItem = (object) ['status' => CommentingStatus::Open->value];
    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->method('__get')->with('status')->willReturn($fieldItem->status);
    $fieldItemList->method('isEmpty')->willReturn(FALSE);

    $host->method('hasField')->willReturnCallback(static fn (string $name): bool => $name === 'field_aculta_discussion');
    $host->method('get')->with('field_aculta_discussion')->willReturn($fieldItemList);

    $access = new DiscussionReplyAccess($resolver, $entityTypeManager);
    $result = $access->checkAccess($host, $account);

    $this->assertTrue($result->isAllowed());
  }

  public function testCheckAccessForbiddenWhenDiscussionClosed(): void {
    $host = $this->createMock(ContentEntityInterface::class);
    $host->method('getCacheContexts')->willReturn([]);
    $host->method('getCacheTags')->willReturn([]);
    $host->method('getCacheMaxAge')->willReturn(-1);

    $account = $this->createMock(AccountInterface::class);

    $context = $this->createMock(DiscussionContextInterface::class);
    $context->method('getReadAccess')->willReturn(AccessResult::allowed());
    $context->method('getWriteAccess')->willReturn(AccessResult::allowed());
    $context->method('getCacheContexts')->willReturn([]);
    $context->method('getCacheTags')->willReturn([]);
    $context->method('getCacheMaxAge')->willReturn(-1);

    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->method('supports')->with($host)->willReturn(TRUE);
    $provider->method('getContext')->with($host, $account)->willReturn($context);

    $resolver = new DiscussionContextResolver([$provider]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $fieldItem = (object) ['status' => CommentingStatus::Closed->value];
    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->method('__get')->with('status')->willReturn($fieldItem->status);
    $fieldItemList->method('isEmpty')->willReturn(FALSE);

    $host->method('hasField')->willReturnCallback(static fn (string $name): bool => $name === 'field_aculta_discussion');
    $host->method('get')->with('field_aculta_discussion')->willReturn($fieldItemList);

    $access = new DiscussionReplyAccess($resolver, $entityTypeManager);
    $result = $access->checkAccess($host, $account);

    $this->assertFalse($result->isAllowed());
  }

}
