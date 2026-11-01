<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_wiki\Unit;

use Drupal\aculta_forum_wiki\Context\WikiDiscussionContextProvider;
use Drupal\comment\CommentingStatus;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the wiki discussion context access contract.
 */
#[Group('aculta_forum_wiki')]
final class WikiDiscussionContextProviderTest extends TestCase {

  /**
   * Only verbete hosts with the configured field are supported.
   */
  public function testSupportsOnlyWikiEntriesWithTheSharedField(): void {
    $provider = new WikiDiscussionContextProvider();
    $entry = $this->node('wiki_entry', TRUE);
    $revista = $this->node('revista', TRUE);
    $article = $this->node('article', TRUE);
    $withoutField = $this->node('wiki_entry', FALSE);
    $topic = $this->node('aculta_forum_topic', TRUE);

    self::assertTrue($provider->supports($entry));
    self::assertFalse($provider->supports($revista));
    self::assertFalse($provider->supports($article));
    self::assertFalse($provider->supports($withoutField));
    self::assertFalse($provider->supports($topic));
    self::assertFalse($provider->supports($this->createMock(EntityInterface::class)));
  }

  /**
   * Host and field access plus Comment permissions shape the context.
   */
  public function testContextUsesHostAccessAndCommentPermissions(): void {
    $provider = new WikiDiscussionContextProvider();
    $entry = $this->node('wiki_entry', TRUE);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['status' => CommentingStatus::Open->value]]);
    $field->method('access')->willReturn(AccessResult::allowed()->addCacheTags(['node:wiki_entry']));
    $entry->method('get')->with('field_aculta_discussion')->willReturn($field);
    $entry->method('uuid')->willReturn('verbete-uuid');
    $entry->method('access')->with('view', $this->isInstanceOf(AccountInterface::class), TRUE)
      ->willReturn(AccessResult::allowed()->addCacheTags(['node:wiki_entry']));
    $url = $this->createMock(Url::class);
    $entry->method('toUrl')->with('canonical')->willReturn($url);

    $account = $this->account(['post comments']);
    $context = $provider->getContext($entry, $account);

    self::assertSame('wiki', $context->getType());
    self::assertSame('wiki:node:verbete-uuid', $context->getStableId());
    self::assertTrue($context->getReadAccess()->isAllowed());
    self::assertTrue($context->getWriteAccess()->isAllowed());
    self::assertFalse($context->getModerationAccess()->isAllowed());
    self::assertContains('user.permissions', $context->getWriteAccess()->getCacheContexts());
    self::assertContains('node:wiki_entry', $context->getCacheTags());
    self::assertSame($url, $context->getCanonicalUrl());
  }

  /**
   * Closed fields and missing Comment permissions deny writing.
   */
  public function testClosedFieldAndMissingPermissionDenyWriting(): void {
    $provider = new WikiDiscussionContextProvider();
    $closed = $this->contextEntry(CommentingStatus::Closed->value);
    $closedContext = $provider->getContext($closed, $this->account(['post comments']));
    self::assertFalse($closedContext->getWriteAccess()->isAllowed());

    $open = $this->contextEntry(CommentingStatus::Open->value);
    $openContext = $provider->getContext($open, $this->account([]));
    self::assertFalse($openContext->getWriteAccess()->isAllowed());
  }

  /**
   * Host and field access restrictions cannot be bypassed by permissions.
   */
  public function testHostAndFieldAccessRestrictionsArePreserved(): void {
    $provider = new WikiDiscussionContextProvider();
    $entry = $this->node('wiki_entry', TRUE);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['status' => CommentingStatus::Open->value]]);
    $field->method('access')->willReturn(AccessResult::forbidden()->addCacheTags(['node:wiki_entry']));
    $entry->method('get')->with('field_aculta_discussion')->willReturn($field);
    $entry->method('uuid')->willReturn('verbete-uuid');
    $entry->method('access')->willReturn(AccessResult::forbidden()->addCacheTags(['node:wiki_entry']));
    $entry->method('toUrl')->willReturn($this->createMock(Url::class));

    $context = $provider->getContext($entry, $this->account(['post comments', 'administer comments']));

    self::assertFalse($context->getReadAccess()->isAllowed());
    self::assertFalse($context->getWriteAccess()->isAllowed());
    self::assertFalse($context->getModerationAccess()->isAllowed());
  }

  /**
   * New Nodes cannot become persisted discussion contexts.
   */
  public function testRejectsUnpersistedWikiEntry(): void {
    $provider = new WikiDiscussionContextProvider();
    $entry = $this->node('wiki_entry', TRUE, TRUE);

    $this->expectException(\LogicException::class);
    $provider->getContext($entry, $this->account([]));
  }

  /**
   * Creates a mock verbete with the requested Comment status.
   */
  private function contextEntry(int $status): NodeInterface {
    $entry = $this->node('wiki_entry', TRUE);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['status' => $status]]);
    $field->method('access')->willReturn(AccessResult::allowed());
    $entry->method('get')->with('field_aculta_discussion')->willReturn($field);
    $entry->method('uuid')->willReturn('verbete-uuid');
    $entry->method('access')->willReturn(AccessResult::allowed());
    $entry->method('toUrl')->willReturn($this->createMock(Url::class));
    return $entry;
  }

  /**
   * Creates a Node mock with optional field support.
   */
  private function node(string $bundle, bool $hasField, bool $isNew = FALSE): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $node->method('hasField')->willReturnCallback(static fn (string $field): bool => $field === 'field_aculta_discussion' && $hasField);
    $node->method('isNew')->willReturn($isNew);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheTags')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(3600);
    return $node;
  }

  /**
   * Creates an account mock with the supplied permissions.
   */
  private function account(array $permissions): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(static fn (string $permission): bool => in_array($permission, $permissions, TRUE));
    return $account;
  }

}
