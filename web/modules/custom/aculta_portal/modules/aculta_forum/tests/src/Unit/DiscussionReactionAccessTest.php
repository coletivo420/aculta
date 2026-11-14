<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Context\DiscussionContextInterface;
use Drupal\aculta_forum\Context\DiscussionContextProviderInterface;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Service\DiscussionReactionAccess;
use Drupal\comment\CommentInterface;
use Drupal\comment\CommentingStatus;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DiscussionReactionAccess::class)]
#[Group('aculta_forum')]
final class DiscussionReactionAccessTest extends UnitTestCase {

  public function testCheckAccessForbiddenWhenReactionsDisabled(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('reactions_enabled')->willReturn(FALSE);
    $config->method('getCacheContexts')->willReturn([]);
    $config->method('getCacheTags')->willReturn([]);
    $config->method('getCacheMaxAge')->willReturn(-1);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_forum.settings')->willReturn($config);

    $resolver = new DiscussionContextResolver([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $access = new DiscussionReactionAccess($resolver, $entityTypeManager, $configFactory);

    $comment = $this->createMock(CommentInterface::class);
    $account = $this->createMock(AccountInterface::class);

    $result = $access->checkAccess($comment, $account);
    $this->assertFalse($result->isAllowed());
  }

  public function testCheckAccessForbiddenForAnonymous(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('reactions_enabled')->willReturn(TRUE);
    $config->method('getCacheContexts')->willReturn([]);
    $config->method('getCacheTags')->willReturn([]);
    $config->method('getCacheMaxAge')->willReturn(-1);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_forum.settings')->willReturn($config);

    $resolver = new DiscussionContextResolver([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $access = new DiscussionReactionAccess($resolver, $entityTypeManager, $configFactory);

    $comment = $this->createMock(CommentInterface::class);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(FALSE);

    $result = $access->checkAccess($comment, $account);
    $this->assertFalse($result->isAllowed());
  }

  public function testCheckAccessForbiddenWithoutPermission(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('reactions_enabled')->willReturn(TRUE);
    $config->method('getCacheContexts')->willReturn([]);
    $config->method('getCacheTags')->willReturn([]);
    $config->method('getCacheMaxAge')->willReturn(-1);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_forum.settings')->willReturn($config);

    $resolver = new DiscussionContextResolver([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $access = new DiscussionReactionAccess($resolver, $entityTypeManager, $configFactory);

    $comment = $this->createMock(CommentInterface::class);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('hasPermission')->with('react to aculta discussions')->willReturn(FALSE);

    $result = $access->checkAccess($comment, $account);
    $this->assertFalse($result->isAllowed());
  }

  public function testCheckAccessForbiddenOnSelfContent(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('reactions_enabled')->willReturn(TRUE);
    $config->method('getCacheContexts')->willReturn([]);
    $config->method('getCacheTags')->willReturn([]);
    $config->method('getCacheMaxAge')->willReturn(-1);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_forum.settings')->willReturn($config);

    $resolver = new DiscussionContextResolver([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $access = new DiscussionReactionAccess($resolver, $entityTypeManager, $configFactory);

    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getOwnerId')->willReturn(42);
    $comment->method('getCacheTags')->willReturn([]);
    $comment->method('getCacheContexts')->willReturn([]);
    $comment->method('getCacheMaxAge')->willReturn(-1);

    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('hasPermission')->with('react to aculta discussions')->willReturn(TRUE);
    $account->method('id')->willReturn(42);

    $result = $access->checkAccess($comment, $account);
    $this->assertFalse($result->isAllowed());
  }

  public function testCheckAccessAllowedWhenAllConditionsMet(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('reactions_enabled')->willReturn(TRUE);
    $config->method('getCacheContexts')->willReturn([]);
    $config->method('getCacheTags')->willReturn([]);
    $config->method('getCacheMaxAge')->willReturn(-1);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_forum.settings')->willReturn($config);

    $host = $this->createMock(ContentEntityInterface::class);
    $host->method('getCacheContexts')->willReturn([]);
    $host->method('getCacheTags')->willReturn([]);
    $host->method('getCacheMaxAge')->willReturn(-1);
    $host->method('hasField')->with('field_aculta_discussion')->willReturn(TRUE);

    $fieldItem = (object) ['status' => CommentingStatus::Open->value];
    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->method('__get')->with('status')->willReturn($fieldItem->status);
    $fieldItemList->method('isEmpty')->willReturn(FALSE);
    $host->method('get')->with('field_aculta_discussion')->willReturn($fieldItemList);

    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('hasPermission')->with('react to aculta discussions')->willReturn(TRUE);
    $account->method('id')->willReturn(99);

    $comment = $this->createMock(CommentInterface::class);
    $comment->method('isPublished')->willReturn(TRUE);
    $comment->method('getOwnerId')->willReturn(42);
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('getCacheTags')->willReturn([]);
    $comment->method('getCacheContexts')->willReturn([]);
    $comment->method('getCacheMaxAge')->willReturn(-1);

    $context = $this->createMock(DiscussionContextInterface::class);
    $context->method('getReadAccess')->willReturn(AccessResult::allowed());
    $context->method('getCacheContexts')->willReturn([]);
    $context->method('getCacheTags')->willReturn([]);
    $context->method('getCacheMaxAge')->willReturn(-1);

    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->method('supports')->with($host)->willReturn(TRUE);
    $provider->method('getContext')->with($host, $account)->willReturn($context);

    $resolver = new DiscussionContextResolver([$provider]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $access = new DiscussionReactionAccess($resolver, $entityTypeManager, $configFactory);
    $result = $access->checkAccess($comment, $account);

    $this->assertTrue($result->isAllowed());
  }

}
