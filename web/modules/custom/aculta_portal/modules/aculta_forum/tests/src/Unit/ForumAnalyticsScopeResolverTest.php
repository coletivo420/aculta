<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Analytics\ForumAnalyticsScope;
use Drupal\aculta_forum\Analytics\ForumAnalyticsScopeResolver;
use Drupal\aculta_forum\RestrictedForum\RestrictedForumGroupResolverInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the centralized Forum analytics scope classification.
 */
#[Group('aculta_forum')]
final class ForumAnalyticsScopeResolverTest extends UnitTestCase {

  /**
   * A published forum topic in the forum purpose without Groups is public.
   */
  public function testPublicForumTopicIsPublic(): void {
    $resolver = $this->resolver([]);
    $scope = $resolver->resolveHost($this->topic('forum_aculta_org'));

    self::assertSame(ForumAnalyticsScope::PUBLIC_FORUM, $scope->scope);
    self::assertTrue($scope->isPublicForum());
  }

  /**
   * A direct Group restriction makes the topic restricted.
   */
  public function testDirectGroupRestrictionIsNotPublic(): void {
    $resolver = $this->resolver([42]);
    $scope = $resolver->resolveHost($this->topic('forum_aculta_org'));

    self::assertSame(ForumAnalyticsScope::RESTRICTED_FORUM, $scope->scope);
    self::assertFalse($scope->isPublicForum());
  }

  /**
   * Group resolution failures fail closed as unknown, never public.
   */
  public function testGroupResolutionFailureFailsClosed(): void {
    $groupResolver = $this->createMock(RestrictedForumGroupResolverInterface::class);
    $groupResolver->method('configuredGroupType')->willReturn('restricted_forum');
    $groupResolver->method('groupIdsForTopic')->willThrowException(new \RuntimeException('Group unavailable'));
    $resolver = new ForumAnalyticsScopeResolver(
      $groupResolver,
      $this->domainPurposeManager(),
      $this->createMock(Connection::class),
      $this->createMock(LoggerInterface::class),
    );
    $scope = $resolver->resolveHost($this->topic('forum_aculta_org'));

    self::assertSame(ForumAnalyticsScope::UNKNOWN, $scope->scope);
    self::assertFalse($scope->isPublicForum());
  }

  /**
   * A topic served by another purpose is classified contextually.
   */
  public function testTopicOutsideForumPurposeIsContextual(): void {
    $resolver = $this->resolver([]);
    self::assertSame(
      ForumAnalyticsScope::PUBLICATION,
      $resolver->resolveHost($this->topic('revista420_aculta_org'))->scope,
    );
  }

  /**
   * Contextual bundles are recognized even without a domain field.
   */
  public function testContextualBundlesAreClassified(): void {
    $resolver = $this->resolver([]);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('wiki_entry');
    $node->method('isNew')->willReturn(FALSE);

    self::assertSame(ForumAnalyticsScope::WIKI, $resolver->resolveHost($node)->scope);
  }

  /**
   * Unpublished forum topics never count as public.
   */
  public function testUnpublishedTopicIsNotPublic(): void {
    $resolver = $this->resolver([]);
    $scope = $resolver->resolveHost($this->topic('forum_aculta_org', FALSE));

    self::assertSame(ForumAnalyticsScope::UNKNOWN, $scope->scope);
    self::assertFalse($scope->isPublicForum());
  }

  /**
   * A comment inherits the scope of its host, including restrictions.
   */
  public function testCommentInheritsHostScope(): void {
    $resolver = $this->resolver([7]);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('isNew')->willReturn(FALSE);
    $comment->method('getCommentedEntity')->willReturn($this->topic('forum_aculta_org'));

    self::assertSame(ForumAnalyticsScope::RESTRICTED_FORUM, $resolver->resolve($comment)->scope);
  }

  /**
   * A comment without a reachable host is unknown.
   */
  public function testCommentWithoutHostIsUnknown(): void {
    $resolver = $this->resolver([]);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('isNew')->willReturn(FALSE);
    $comment->method('getCommentedEntity')->willReturn(NULL);

    self::assertSame(ForumAnalyticsScope::UNKNOWN, $resolver->resolve($comment)->scope);
  }

  /**
   * Unsupported bundles are unknown.
   */
  public function testUnsupportedBundleIsUnknown(): void {
    $resolver = $this->resolver([]);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');
    $node->method('isNew')->willReturn(FALSE);

    self::assertSame(ForumAnalyticsScope::UNKNOWN, $resolver->resolveHost($node)->scope);
  }

  /**
   * Builds the resolver with isolated collaborators.
   *
   * @param int[] $groupIds
   *   Group IDs returned for the topic.
   * @param \Drupal\aculta_forum\RestrictedForum\RestrictedForumGroupResolverInterface|null $groupResolver
   *   Optional preconfigured resolver for failure scenarios.
   */
  private function resolver(array $groupIds, ?RestrictedForumGroupResolverInterface $groupResolver = NULL): ForumAnalyticsScopeResolver {
    $groupResolver ??= $this->createMock(RestrictedForumGroupResolverInterface::class);
    $groupResolver->method('configuredGroupType')->willReturn('restricted_forum');
    $groupResolver->method('groupIdsForTopic')->willReturn($groupIds);

    return new ForumAnalyticsScopeResolver(
      $groupResolver,
      $this->domainPurposeManager(),
      $this->createMock(Connection::class),
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Creates a forum topic node mock with a domain source.
   */
  private function topic(string $domainId, bool $published = TRUE): NodeInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(FALSE);
    $field->method('getValue')->willReturn([['target_id' => $domainId]]);

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('isNew')->willReturn(FALSE);
    $topic->method('isPublished')->willReturn($published);
    $topic->method('hasField')->with('field_domain_source')->willReturn(TRUE);
    $topic->method('get')->with('field_domain_source')->willReturn($field);
    $topic->method('id')->willReturn(10);
    return $topic;
  }

  /**
   * Creates a real Domain purpose manager over isolated dependencies.
   */
  private function domainPurposeManager(): DomainPurposeManager {
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn(NULL);
    return new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

}
