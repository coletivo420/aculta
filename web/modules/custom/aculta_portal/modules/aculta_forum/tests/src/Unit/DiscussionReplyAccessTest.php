<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Context\DiscussionContextInterface;
use Drupal\aculta_forum\Context\DiscussionContextProviderInterface;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Origin\CoreDiscussionOriginClassifier;
use Drupal\aculta_forum\Origin\DiscussionOriginResolver;
use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginClassification;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\aculta_forum\Service\DiscussionReplyAccess;
use Drupal\comment\CommentingStatus;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests DiscussionReplyAccess behavior.
 */
#[CoversClass(DiscussionReplyAccess::class)]
#[Group('aculta_forum')]
final class DiscussionReplyAccessTest extends UnitTestCase {

  /**
   * An archive origin is never writable through Comment APIs.
   */
  public function testHistoricalTopicCannotReceiveStandardComments(): void {
    $host = $this->createMock(ContentEntityInterface::class);
    $host->method('getCacheContexts')->willReturn([]);
    $host->method('getCacheTags')->willReturn([]);
    $host->method('getCacheMaxAge')->willReturn(-1);
    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->expects(self::never())->method('supports');

    $access = new DiscussionReplyAccess(
      new DiscussionContextResolver([$provider]),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->originResolver(DiscussionOrigin::ARCHIVE),
    );
    self::assertFalse($access->checkAccess($host, $this->createMock(AccountInterface::class))->isAllowed());
  }

  /**
   * Tests that check access forbidden when no context resolved.
   */
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

    $access = new DiscussionReplyAccess($resolver, $entityTypeManager, $this->originResolver(DiscussionOrigin::CURRENT));
    $result = $access->checkAccess($host, $account);

    $this->assertFalse($result->isAllowed());
  }

  /**
   * Tests that check access allowed when context read and write allowed.
   */
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

    $access = new DiscussionReplyAccess($resolver, $entityTypeManager, $this->originResolver(DiscussionOrigin::CONTEXTUAL));
    $result = $access->checkAccess($host, $account);

    $this->assertTrue($result->isAllowed());
  }

  /**
   * A registered contextual host passes origin and reply access checks.
   */
  public function testRegisteredContextualHostCanReceiveReplies(): void {
    $host = $this->createMock(ContentEntityInterface::class);
    $host->method('getEntityTypeId')->willReturn('node');
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
    $contextResolver = new DiscussionContextResolver([$provider]);
    $originResolver = new DiscussionOriginResolver([
      new CoreDiscussionOriginClassifier($contextResolver),
    ]);
    $fieldItem = (object) ['status' => CommentingStatus::Open->value];
    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->method('__get')->with('status')->willReturn($fieldItem->status);
    $fieldItemList->method('isEmpty')->willReturn(FALSE);
    $host->method('hasField')->with('field_aculta_discussion')->willReturn(TRUE);
    $host->method('get')->with('field_aculta_discussion')->willReturn($fieldItemList);

    $access = new DiscussionReplyAccess(
      $contextResolver,
      $this->createMock(EntityTypeManagerInterface::class),
      $originResolver,
    );

    self::assertTrue($access->checkAccess($host, $account)->isAllowed());
  }

  /**
   * Tests that check access forbidden when discussion closed.
   */
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

    $access = new DiscussionReplyAccess($resolver, $entityTypeManager, $this->originResolver(DiscussionOrigin::CONTEXTUAL));
    $result = $access->checkAccess($host, $account);

    $this->assertFalse($result->isAllowed());
  }

  /**
   * Creates an origin resolver fixture.
   */
  private function originResolver(string $origin): DiscussionOriginResolverInterface {
    $resolver = $this->createMock(DiscussionOriginResolverInterface::class);
    $resolver->method('classify')->willReturn(
      DiscussionOriginClassification::from($origin, new CacheableMetadata()),
    );
    return $resolver;
  }

}
