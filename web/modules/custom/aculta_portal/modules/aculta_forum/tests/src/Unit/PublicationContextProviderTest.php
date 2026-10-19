<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Context\Provider\PublicationContextProvider;
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
 * Tests the publication discussion context access contract.
 */
#[Group('aculta_forum')]
final class PublicationContextProviderTest extends TestCase {

  /**
   * Only article and blog hosts with the configured field are supported.
   */
  public function testSupportsOnlyPublicationNodesWithTheSharedField(): void {
    $provider = new PublicationContextProvider();
    $article = $this->node('article', TRUE);
    $blog = $this->node('blog', TRUE);
    $withoutField = $this->node('article', FALSE);
    $topic = $this->node('aculta_forum_topic', TRUE);

    self::assertTrue($provider->supports($article));
    self::assertTrue($provider->supports($blog));
    self::assertFalse($provider->supports($withoutField));
    self::assertFalse($provider->supports($topic));
    self::assertFalse($provider->supports($this->createMock(EntityInterface::class)));
  }

  /**
   * Host and field access plus Comment permissions shape the context.
   */
  public function testContextUsesHostAccessAndCommentPermissions(): void {
    $provider = new PublicationContextProvider();
    $article = $this->node('article', TRUE);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['status' => CommentingStatus::Open->value]]);
    $field->method('access')->willReturn(AccessResult::allowed()->addCacheTags(['node:article']));
    $article->method('get')->with('field_aculta_discussion')->willReturn($field);
    $article->method('uuid')->willReturn('article-uuid');
    $article->method('access')->with('view', $this->isInstanceOf(AccountInterface::class), TRUE)
      ->willReturn(AccessResult::allowed()->addCacheTags(['node:article']));
    $url = $this->createMock(Url::class);
    $article->method('toUrl')->with('canonical')->willReturn($url);

    $account = $this->account(['post comments']);
    $context = $provider->getContext($article, $account);

    self::assertSame('publication', $context->getType());
    self::assertSame('publication:node:article-uuid', $context->getStableId());
    self::assertTrue($context->getReadAccess()->isAllowed());
    self::assertTrue($context->getWriteAccess()->isAllowed());
    self::assertFalse($context->getModerationAccess()->isAllowed());
    self::assertContains('user.permissions', $context->getWriteAccess()->getCacheContexts());
    self::assertContains('node:article', $context->getCacheTags());
    self::assertSame($url, $context->getCanonicalUrl());
  }

  /**
   * Closed fields and missing Comment permissions deny writing.
   */
  public function testClosedFieldAndMissingPermissionDenyWriting(): void {
    $provider = new PublicationContextProvider();
    $closed = $this->publicationContextNode(CommentingStatus::Closed->value);
    $closedContext = $provider->getContext($closed, $this->account(['post comments']));
    self::assertFalse($closedContext->getWriteAccess()->isAllowed());

    $open = $this->publicationContextNode(CommentingStatus::Open->value);
    $openContext = $provider->getContext($open, $this->account([]));
    self::assertFalse($openContext->getWriteAccess()->isAllowed());
  }

  /**
   * Host and field access restrictions cannot be bypassed by permissions.
   */
  public function testHostAndFieldAccessRestrictionsArePreserved(): void {
    $provider = new PublicationContextProvider();
    $node = $this->node('article', TRUE);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['status' => CommentingStatus::Open->value]]);
    $field->method('access')->willReturn(AccessResult::forbidden()->addCacheTags(['node:article']));
    $node->method('get')->with('field_aculta_discussion')->willReturn($field);
    $node->method('uuid')->willReturn('article-uuid');
    $node->method('access')->willReturn(AccessResult::forbidden()->addCacheTags(['node:article']));
    $node->method('toUrl')->willReturn($this->createMock(Url::class));

    $context = $provider->getContext($node, $this->account(['post comments', 'administer comments']));

    self::assertFalse($context->getReadAccess()->isAllowed());
    self::assertFalse($context->getWriteAccess()->isAllowed());
    self::assertFalse($context->getModerationAccess()->isAllowed());
  }

  /**
   * New Nodes cannot become persisted discussion contexts.
   */
  public function testRejectsUnpersistedPublication(): void {
    $provider = new PublicationContextProvider();
    $node = $this->node('article', TRUE, TRUE);

    $this->expectException(\LogicException::class);
    $provider->getContext($node, $this->account([]));
  }

  /**
   * Creates a mock publication with the requested Comment status.
   */
  private function publicationContextNode(int $status): NodeInterface {
    $node = $this->node('article', TRUE);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['status' => $status]]);
    $field->method('access')->willReturn(AccessResult::allowed());
    $node->method('get')->with('field_aculta_discussion')->willReturn($field);
    $node->method('uuid')->willReturn('article-uuid');
    $node->method('access')->willReturn(AccessResult::allowed());
    $node->method('toUrl')->willReturn($this->createMock(Url::class));
    return $node;
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
