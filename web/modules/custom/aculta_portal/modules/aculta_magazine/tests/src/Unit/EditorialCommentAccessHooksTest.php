<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\aculta_forum\Context\DiscussionContextInterface;
use Drupal\aculta_forum\Context\DiscussionContextProviderInterface;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_magazine\Access\EditorialCapability;
use Drupal\aculta_magazine\Hook\EditorialCommentAccessHooks;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests contextual comment moderation capability and Growroom isolation. */
final class EditorialCommentAccessHooksTest extends UnitTestCase {

  /**
   * Denies direct moderation when the role is not selected in configuration.
   */
  public function testUnconfiguredRoleCannotUseDirectCommentModeration(): void {
    $comment = $this->comment('revista');
    $account = $this->account(['authenticated', 'content_editor'], TRUE);
    $hook = $this->hook(['administrator']);

    self::assertTrue($hook->entityAccess($comment, 'approve', $account)->isForbidden());
  }

  /**
   * Retains the native Comment moderation permission check.
   */
  public function testConfiguredRoleStillNeedsNativeCommentPermission(): void {
    $comment = $this->comment('revista');
    $account = $this->account(['authenticated', 'content_editor'], FALSE);
    $hook = $this->hook(['content_editor'], FALSE);

    self::assertFalse($hook->entityAccess($comment, 'approve', $account)->isAllowed());
  }

  /**
   * Leaves Growroom comments to the public Forum context owner.
   */
  public function testGrowroomCommentIsNotHandledAsMagazineContext(): void {
    $comment = $this->comment('aculta_forum_topic');
    $account = $this->account(['authenticated', 'content_editor'], TRUE);
    $hook = $this->hook(['content_editor']);

    self::assertTrue($hook->entityAccess($comment, 'approve', $account)->isNeutral());
  }

  /**
   * Builds the access hook with a single publication-context provider.
   */
  private function hook(array $roles, bool $nativeModerationAllowed = TRUE): EditorialCommentAccessHooks {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('comments_roles')->willReturn($roles);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('aculta_magazine.settings')->willReturn($config);
    $context = $this->createMock(DiscussionContextInterface::class);
    $context->method('getType')->willReturn('publication');
    $context->method('getModerationAccess')->willReturn($nativeModerationAllowed ? AccessResult::allowed() : AccessResult::forbidden());
    $context->method('getCacheContexts')->willReturn([]);
    $context->method('getCacheTags')->willReturn([]);
    $context->method('getCacheMaxAge')->willReturn(0);
    $provider = new class($context) implements DiscussionContextProviderInterface {

      public function __construct(private readonly DiscussionContextInterface $context) {}

      /**
       * Restricts this test provider to Revista hosts only.
       */
      public function supports(EntityInterface $entity): bool {
        return $entity instanceof NodeInterface && $entity->bundle() === 'revista';
      }

      /**
       * Returns the fixture publication context.
       */
      public function getContext(EntityInterface $entity, AccountInterface $account): DiscussionContextInterface {
        return $this->context;
      }

    };
    return new EditorialCommentAccessHooks(new EditorialCapability($factory), new DiscussionContextResolver([$provider]));
  }

  /**
   * Creates a Comment mock hosted by the requested Node bundle.
   */
  private function comment(string $bundle): CommentInterface {
    $host = $this->createMock(NodeInterface::class);
    $host->method('bundle')->willReturn($bundle);
    $host->method('getCacheContexts')->willReturn([]);
    $host->method('getCacheTags')->willReturn([]);
    $host->method('getCacheMaxAge')->willReturn(0);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getFieldName')->willReturn('field_aculta_discussion');
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('getCacheContexts')->willReturn([]);
    $comment->method('getCacheTags')->willReturn([]);
    $comment->method('getCacheMaxAge')->willReturn(0);
    return $comment;
  }

  /**
   * Creates an account with configured roles and Comment permissions.
   */
  private function account(array $roles, bool $administerComments): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('getRoles')->willReturn($roles);
    $account->method('hasPermission')->willReturnCallback(static fn (string $permission): bool => $permission === 'administer comments' && $administerComments);
    return $account;
  }

}
