<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_forum\Hook\HistoricForumFlagAccessHooks;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests that Growroom archive content stays out of the report flow.
 */
#[Group('aculta_forum')]
final class HistoricForumFlagAccessHooksTest extends UnitTestCase {

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
    (new HistoricForumFlagAccessHooks($manager, $currentUser))->preventHistoricalFlagging($flagging);
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
    (new HistoricForumFlagAccessHooks($manager, $currentUser))->preventHistoricalFlagging($flagging);
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
    (new HistoricForumFlagAccessHooks($manager, $currentUser))->preventHistoricalFlagging($flagging);
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
    (new HistoricForumFlagAccessHooks($manager, $currentUser))->preventHistoricalFlagging($flagging);
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
    (new HistoricForumFlagAccessHooks($manager, $currentUser))->preventHistoricalFlagging($flagging);
  }

  /**
   * Creates the hook with mocked Runtime dependencies.
   */
  private function hook(): HistoricForumFlagAccessHooks {
    return new HistoricForumFlagAccessHooks(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(AccountProxyInterface::class),
    );
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
   */
  private function node(string $bundle, bool $historical): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $node->method('hasField')->with('field_autor_legado')->willReturn(TRUE);
    $node->method('get')->with('field_autor_legado')->willReturn($this->marker($historical));
    $this->cacheable($node);
    return $node;
  }

  /**
   * Creates a field item list reporting the requested emptiness state.
   */
  private function marker(bool $populated): FieldItemListInterface {
    $marker = $this->createMock(FieldItemListInterface::class);
    $marker->method('isEmpty')->willReturn(!$populated);
    return $marker;
  }

  /**
   * Gives a mocked entity cacheability metadata.
   */
  private function cacheable(object $entity): void {
    $entity->method('getCacheTags')->willReturn([]);
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
  }

}
