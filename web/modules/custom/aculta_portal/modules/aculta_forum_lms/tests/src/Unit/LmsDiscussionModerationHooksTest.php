<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Unit;

use Drupal\aculta_forum\Context\DiscussionContextInterface;
use Drupal\aculta_forum\Context\DiscussionContextProviderInterface;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum_lms\Hook\LmsDiscussionModerationHooks;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Tests that only LMS contexts can delegate moderation to this adapter.
 */
#[Group('aculta_forum_lms')]
final class LmsDiscussionModerationHooksTest extends UnitTestCase {

  /**
   * The OOP hook injects the Forum's actual resolver service ID.
   */
  public function testResolverUsesTheForumServiceId(): void {
    $parameter = (new \ReflectionMethod(LmsDiscussionModerationHooks::class, '__construct'))
      ->getParameters()[0];
    $attributes = $parameter->getAttributes(Autowire::class);

    self::assertCount(1, $attributes);
    $autowire = $attributes[0]->newInstance();
    self::assertSame('aculta_forum.discussion_context_resolver', (string) $autowire->value);
  }

  /**
   * Checks that an Educator's LMS context can authorize moderation.
   */
  public function testEducatorContextCanAccessTheSharedModerationMarker(): void {
    $host = $this->createMock(NodeInterface::class);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('getCacheContexts')->willReturn([]);
    $comment->method('getCacheTags')->willReturn([]);
    $comment->method('getCacheMaxAge')->willReturn(-1);
    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('getEntity')->willReturn($comment);

    $field = $this->createMock(FieldDefinitionInterface::class);
    $field->method('getName')->willReturn('field_aculta_rejected');
    $account = $this->createMock(AccountInterface::class);

    $context = $this->createMock(DiscussionContextInterface::class);
    $context->method('getType')->willReturn('lms');
    $context->method('getModerationAccess')->willReturn(AccessResult::allowed());
    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->method('supports')->with($host)->willReturn(TRUE);
    $provider->method('getContext')->with($host, $account)->willReturn($context);

    $resolver = new DiscussionContextResolver([$provider]);
    $hook = new LmsDiscussionModerationHooks($resolver);

    self::assertTrue($hook->entityFieldAccess('edit', $field, $account, $items)->isAllowed());
  }

  /**
   * Checks that unrelated Comment fields stay outside this adapter.
   */
  public function testUnrelatedFieldsDoNotReceiveAdapterAccess(): void {
    $field = $this->createMock(FieldDefinitionInterface::class);
    $field->method('getName')->willReturn('comment_body');
    $hook = new LmsDiscussionModerationHooks(new DiscussionContextResolver([]));

    self::assertTrue($hook->entityFieldAccess('edit', $field, $this->createMock(AccountInterface::class))->isNeutral());
  }

}
