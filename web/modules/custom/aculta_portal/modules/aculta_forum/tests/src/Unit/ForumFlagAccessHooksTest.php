<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_forum\Hook\ForumFlagAccessHooks;
use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginClassification;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests that Growroom archive content stays out of the report flow.
 */
#[Group('aculta_forum')]
final class ForumFlagAccessHooksTest extends UnitTestCase {

  /**
   * A historical topic cannot be reported through either topic flag action.
   */
  public function testHistoricalTopicIsDeniedFlagAndUnflagActions(): void {
    $hook = $this->hook();
    $flag = $this->flag('sinalizar_topico');
    $topic = $this->node('aculta_forum_topic', TRUE);
    $account = $this->createMock(AccountInterface::class);

    self::assertTrue($hook->flagActionAccess('flag', $flag, $account, $topic)->isForbidden());
    self::assertTrue($hook->flagActionAccess('unflag', $flag, $account, $topic)->isForbidden());
  }

  /**
   * A current public topic remains reportable.
   */
  public function testCurrentTopicIsAllowed(): void {
    $hook = $this->hook();
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('report aculta forum content')->willReturn(TRUE);

    self::assertTrue($hook->flagActionAccess('flag', $this->flag('sinalizar_topico'), $account, $this->node('aculta_forum_topic', FALSE))->isAllowed());
  }

  /**
   * Forum report access requires the dedicated confirmed-participant grant.
   */
  public function testCurrentTopicRequiresReportPermission(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('report aculta forum content')->willReturn(FALSE);

    self::assertTrue($this->hook()
      ->flagActionAccess('flag', $this->flag('sinalizar_topico'), $account, $this->node('aculta_forum_topic', FALSE))
      ->isNeutral());
  }

  /**
   * Unclassified Forum content fails closed even with report permission.
   */
  public function testUnknownOriginCannotBeReported(): void {
    $topic = $this->node('aculta_forum_topic', FALSE);
    $resolver = $this->createMock(DiscussionOriginResolverInterface::class);
    $resolver->method('classify')->with($topic)->willReturn(
      DiscussionOriginClassification::from(DiscussionOrigin::UNKNOWN, $topic),
    );
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('report aculta forum content')->willReturn(TRUE);
    $hook = new ForumFlagAccessHooks(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(AccountProxyInterface::class),
      $resolver,
    );

    self::assertTrue($hook->flagActionAccess('flag', $this->flag('sinalizar_topico'), $account, $topic)->isForbidden());
  }

  /**
   * Current Comment access inherits tags from the Comment and topic host.
   */
  public function testCurrentCommentAccessIncludesHostCacheability(): void {
    $host = $this->node('aculta_forum_topic', FALSE, TRUE, ['node:forum-topic']);
    $comment = $this->comment($host, FALSE, ['comment:forum-reply']);
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('report aculta forum content')->willReturn(TRUE);

    $result = $this->hook()->flagActionAccess(
      'flag',
      $this->flag('sinalizar_comentario'),
      $account,
      $comment,
    );

    self::assertTrue($result->isAllowed());
    $cacheTags = CacheableMetadata::createFromObject($result)->getCacheTags();
    self::assertContains('comment:forum-reply', $cacheTags);
    self::assertContains('node:forum-topic', $cacheTags);
  }

  /**
   * Unpublished Forum content cannot be reported with a direct action.
   */
  public function testUnpublishedTargetsAreDenied(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('report aculta forum content')->willReturn(TRUE);
    $hook = $this->hook();

    $unpublishedTopic = $this->node('aculta_forum_topic', FALSE, FALSE);
    self::assertTrue($hook
      ->flagActionAccess('flag', $this->flag('sinalizar_topico'), $account, $unpublishedTopic)
      ->isForbidden());

    $comment = $this->comment($unpublishedTopic);
    self::assertTrue($hook
      ->flagActionAccess('flag', $this->flag('sinalizar_comentario'), $account, $comment)
      ->isForbidden());
  }

  /**
   * Comments inherit historical status from either their author marker or host.
   */
  public function testHistoricalCommentAndHistoricalHostAreDenied(): void {
    $hook = $this->hook();
    $account = $this->createMock(AccountInterface::class);
    $flag = $this->flag('sinalizar_comentario');

    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('hasField')->with('field_autor_legado')->willReturn(TRUE);
    $comment->method('get')->with('field_autor_legado')->willReturn($this->marker(TRUE));
    $comment->method('getCommentedEntity')->willReturn($this->node('aculta_forum_topic', FALSE));
    $this->cacheable($comment);
    self::assertTrue($hook->flagActionAccess('flag', $flag, $account, $comment)->isForbidden());

    $host = $this->node('aculta_forum_topic', TRUE);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('hasField')->with('field_autor_legado')->willReturn(FALSE);
    $comment->method('getCommentedEntity')->willReturn($host);
    $this->cacheable($comment);
    self::assertTrue($hook->flagActionAccess('unflag', $flag, $account, $comment)->isForbidden());
  }

  /**
   * Unrelated flag/entity combinations are left to the owning access policy.
   */
  public function testUnrelatedFlagAndEntityAreUnaffected(): void {
    $hook = $this->hook();
    $account = $this->createMock(AccountInterface::class);

    self::assertTrue($hook->flagActionAccess('flag', $this->flag('salvar_topico'), $account, $this->node('aculta_forum_topic', TRUE))->isNeutral());
    self::assertTrue($hook->flagActionAccess('flag', $this->flag('sinalizar_topico'), $account, $this->node('article', FALSE))->isNeutral());

    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getCommentedEntity')->willReturn($this->node('article', FALSE));
    self::assertTrue($hook->flagActionAccess('flag', $this->flag('sinalizar_comentario'), $account, $comment)->isNeutral());
  }

  /**
   * Direct Flagging saves cannot bypass the archive policy.
   */
  public function testDirectFlaggingSaveIsRejectedForHistoricTopic(): void {
    $topicStorage = $this->createMock(EntityStorageInterface::class);
    $topicStorage->method('load')->with('42')->willReturn($this->node('aculta_forum_topic', TRUE));
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('node')->willReturn($topicStorage);

    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('getFlagId')->willReturn('sinalizar_topico');
    $flagging->method('getFlaggableType')->willReturn('node');
    $flagging->method('getFlaggableId')->willReturn('42');
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('hasPermission')->with('report aculta forum content')->willReturn(TRUE);

    $this->expectException(AccessDeniedHttpException::class);
    (new ForumFlagAccessHooks($manager, $currentUser, $this->originResolver()))->preventHistoricalFlagging($flagging);
  }

  /**
   * Direct flag saves cannot use the Forum flag from another context.
   */
  public function testDirectCommentFlaggingSaveIsRejectedOutsidePublicForum(): void {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getCommentedEntity')->willReturn($this->node('article', FALSE));
    $commentStorage = $this->createMock(EntityStorageInterface::class);
    $commentStorage->method('load')->with('84')->willReturn($comment);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('comment')->willReturn($commentStorage);

    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('getFlagId')->willReturn('sinalizar_comentario');
    $flagging->method('getFlaggableType')->willReturn('comment');
    $flagging->method('getFlaggableId')->willReturn('84');
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('hasPermission')->with('report aculta forum content')->willReturn(TRUE);

    $this->expectException(AccessDeniedHttpException::class);
    (new ForumFlagAccessHooks($manager, $currentUser, $this->originResolver()))->preventHistoricalFlagging($flagging);
  }

  /**
   * Direct saves also require the dedicated report permission.
   */
  public function testDirectFlaggingSaveRequiresReportPermission(): void {
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('hasPermission')->with('report aculta forum content')->willReturn(FALSE);
    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('getFlagId')->willReturn('sinalizar_topico');

    $this->expectException(AccessDeniedHttpException::class);
    (new ForumFlagAccessHooks($manager, $currentUser, $this->originResolver()))->preventHistoricalFlagging($flagging);
  }

  /**
   * Direct Flag saves fail closed when their target does not exist.
   */
  public function testDirectFlaggingSaveRejectsMissingTarget(): void {
    $topicStorage = $this->createMock(EntityStorageInterface::class);
    $topicStorage->method('load')->with('404')->willReturn(NULL);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('node')->willReturn($topicStorage);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('hasPermission')->with('report aculta forum content')->willReturn(TRUE);
    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('getFlagId')->willReturn('sinalizar_topico');
    $flagging->method('getFlaggableType')->willReturn('node');
    $flagging->method('getFlaggableId')->willReturn('404');

    $this->expectException(AccessDeniedHttpException::class);
    (new ForumFlagAccessHooks($manager, $currentUser, $this->originResolver()))->preventHistoricalFlagging($flagging);
  }

  /**
   * Direct Flag saves cannot request arbitrary entity storage types.
   */
  public function testDirectFlaggingSaveRejectsUnexpectedEntityType(): void {
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects(self::never())->method('getStorage');
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('hasPermission')->with('report aculta forum content')->willReturn(TRUE);
    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('getFlagId')->willReturn('sinalizar_topico');
    $flagging->method('getFlaggableType')->willReturn('arbitrary_entity');

    $this->expectException(AccessDeniedHttpException::class);
    (new ForumFlagAccessHooks($manager, $currentUser, $this->originResolver()))->preventHistoricalFlagging($flagging);
  }

  /**
   * Creates the hook with mocked Runtime dependencies.
   */
  private function hook(): ForumFlagAccessHooks {
    return new ForumFlagAccessHooks(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(AccountProxyInterface::class),
      $this->originResolver(),
    );
  }

  /**
   * Creates the provider double used to test the generic origin contract.
   */
  private function originResolver(): DiscussionOriginResolverInterface {
    $resolver = $this->createMock(DiscussionOriginResolverInterface::class);
    $resolver->method('classify')->willReturnCallback(function (EntityInterface $entity): DiscussionOriginClassification {
      $archive = $this->hasHistoricAuthor($entity);
      if ($entity instanceof CommentInterface) {
        $host = $entity->getCommentedEntity();
        $archive = $archive || ($host instanceof NodeInterface && $this->hasHistoricAuthor($host));
      }
      return DiscussionOriginClassification::from($archive ? DiscussionOrigin::ARCHIVE : DiscussionOrigin::CURRENT, $entity);
    });
    return $resolver;
  }

  /**
   * Checks the fixture marker used by the fake origin provider.
   */
  private function hasHistoricAuthor(EntityInterface $entity): bool {
    return $entity instanceof FieldableEntityInterface
      && $entity->hasField('field_autor_legado')
      && !$entity->get('field_autor_legado')->isEmpty();
  }

  /**
   * Creates a Flag carrying the requested machine name.
   */
  private function flag(string $id): FlagInterface {
    $flag = $this->createMock(FlagInterface::class);
    $flag->method('id')->willReturn($id);
    return $flag;
  }

  /**
   * Creates a Node mock with the requested historical marker state.
   *
   * @param string $bundle
   *   The Node bundle.
   * @param bool $historical
   *   Whether the marker field is populated.
   * @param bool $published
   *   Whether the Node is published.
   * @param list<string> $cacheTags
   *   Cache tags to expose from the Node.
   */
  private function node(string $bundle, bool $historical, bool $published = TRUE, array $cacheTags = []): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $node->method('isPublished')->willReturn($published);
    $node->method('access')->willReturn(AccessResult::allowed());
    $node->method('hasField')->with('field_autor_legado')->willReturn(TRUE);
    $node->method('get')->with('field_autor_legado')->willReturn($this->marker($historical));
    $this->cacheable($node, $cacheTags);
    return $node;
  }

  /**
   * Creates a Comment mock with a configurable host and cacheability.
   *
   * @param \Drupal\node\NodeInterface $host
   *   The Comment host.
   * @param bool $historical
   *   Whether the marker field is populated.
   * @param list<string> $cacheTags
   *   Cache tags to expose from the Comment.
   */
  private function comment(NodeInterface $host, bool $historical = FALSE, array $cacheTags = []): CommentInterface {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('access')->willReturn(AccessResult::allowed());
    $comment->method('hasField')->with('field_autor_legado')->willReturn(TRUE);
    $comment->method('get')->with('field_autor_legado')->willReturn($this->marker($historical));
    $comment->method('getCommentedEntity')->willReturn($host);
    $this->cacheable($comment, $cacheTags);
    return $comment;
  }

  /**
   * Creates a field item list reporting the requested emptiness state.
   *
   * @return FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>&MockObject
   *   The mocked marker field list.
   */
  private function marker(bool $populated): FieldItemListInterface&MockObject {
    /** @var FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>&MockObject $marker */
    $marker = $this->createMock(FieldItemListInterface::class);
    $marker->method('isEmpty')->willReturn(!$populated);
    return $marker;
  }

  /**
   * Gives a mocked entity cacheability metadata.
   *
   * @param object&MockObject $entity
   *   The mocked entity.
   * @param list<string> $cacheTags
   *   The entity's cache tags.
   */
  private function cacheable(object $entity, array $cacheTags = []): void {
    $entity->method('getCacheTags')->willReturn($cacheTags);
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
  }

}
