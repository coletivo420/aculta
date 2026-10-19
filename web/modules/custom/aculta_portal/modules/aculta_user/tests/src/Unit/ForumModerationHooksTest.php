<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_user\Hook\ForumModerationHooks;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Checks confirmation-gated moderation for new public Forum topics.
 */
#[Group('aculta_user')]
final class ForumModerationHooksTest extends TestCase {

  /**
   * Confirmed editors can update and delete another author's topic.
   */
  public function testConfirmedEditorCanModerateAnotherAuthorsNewTopic(): void {
    $hook = $this->hook(TRUE);
    $editor = $this->account(10, ['content_editor', 'email_confirmed']);
    $topic = $this->topic(20);

    foreach (['update', 'delete'] as $operation) {
      $result = $hook->entityAccess($topic, $operation, $editor);
      self::assertInstanceOf(AccessResult::class, $result);
      self::assertTrue($result->isAllowed());
      self::assertContains('user.roles', $result->getCacheContexts());
      self::assertContains('node:20', $result->getCacheTags());
    }
  }

  /**
   * Editors without a confirmed email cannot moderate another author's topic.
   */
  public function testUnconfirmedEditorCannotModerateAnotherAuthorsNewTopic(): void {
    $hook = $this->hook(FALSE);
    $editor = $this->account(10, ['content_editor']);
    $result = $hook->entityAccess($this->topic(20), 'update', $editor);

    self::assertInstanceOf(AccessResult::class, $result);
    self::assertTrue($result->isForbidden());
    self::assertContains('user.roles', $result->getCacheContexts());
  }

  /**
   * The email-confirmed role alone does not replace the confirmation marker.
   */
  public function testConfirmationRoleAloneDoesNotAuthorizeModeration(): void {
    $hook = $this->hook(FALSE);
    $editor = $this->account(10, ['content_editor', 'email_confirmed']);
    $result = $hook->entityAccess($this->topic(20), 'delete', $editor);

    self::assertInstanceOf(AccessResult::class, $result);
    self::assertTrue($result->isForbidden());
  }

  /**
   * Own-topic and unrelated-bundle access remains with the normal handlers.
   */
  public function testOwnTopicAndUnrelatedBundleRemainNeutral(): void {
    $hook = $this->hook(FALSE);
    $editor = $this->account(10, ['content_editor']);

    self::assertNull($hook->entityAccess($this->topic(10), 'update', $editor));

    $unrelated = $this->createMock(NodeInterface::class);
    $unrelated->method('bundle')->willReturn('article');
    self::assertNull($hook->entityAccess($unrelated, 'update', $editor));
  }

  /**
   * The existing administrative bypass remains neutral in this hook.
   */
  public function testAdministratorBypassRemainsUnaffected(): void {
    $hook = $this->hook(FALSE);
    $administrator = $this->account(1, ['administrator'], TRUE);

    self::assertNull($hook->entityAccess($this->topic(20), 'delete', $administrator));
  }

  /**
   * Imported Growroom topics remain frozen even for an administrator.
   */
  public function testHistoricGrowroomTopicCannotBeChangedByAdministrator(): void {
    $hook = $this->hook(FALSE);
    $administrator = $this->account(1, ['administrator'], TRUE);
    $topic = $this->historicTopic();

    foreach (['update', 'delete'] as $operation) {
      $result = $hook->entityAccess($topic, $operation, $administrator);
      self::assertInstanceOf(AccessResult::class, $result);
      self::assertTrue($result->isForbidden());
    }
  }

  /**
   * Imported Growroom comments cannot be edited, deleted, or approved.
   */
  public function testHistoricGrowroomCommentsCannotBeModerated(): void {
    $hook = $this->hook(FALSE);
    $editor = $this->account(10, ['content_editor', 'email_confirmed']);
    $comment = $this->historicComment();

    foreach (['update', 'delete', 'approve'] as $operation) {
      $result = $hook->entityAccess($comment, $operation, $editor);
      self::assertInstanceOf(AccessResult::class, $result);
      self::assertTrue($result->isForbidden());
    }
  }

  /**
   * Drupal Forum contrib entities are not Growroom archive imports.
   */
  public function testContributedForumEntitiesAreNotFrozenByArchiveMarker(): void {
    $hook = $this->hook(FALSE);
    $administrator = $this->account(1, ['administrator'], TRUE);
    $author = $this->createMock(FieldItemListInterface::class);
    $author->method('isEmpty')->willReturn(FALSE);

    $forumTopic = $this->createMock(NodeInterface::class);
    $forumTopic->method('bundle')->willReturn('forum');
    $forumTopic->method('hasField')->with('field_autor_legado')->willReturn(TRUE);
    $forumTopic->method('get')->with('field_autor_legado')->willReturn($author);
    self::assertNull($hook->entityAccess($forumTopic, 'update', $administrator));

    $forumComment = $this->createMock(CommentInterface::class);
    $forumComment->method('bundle')->willReturn('comment_forum');
    $forumComment->method('hasField')->with('field_autor_legado')->willReturn(TRUE);
    $forumComment->method('get')->with('field_autor_legado')->willReturn($author);
    self::assertNull($hook->entityAccess($forumComment, 'approve', $administrator));
  }

  /**
   * Imported historic topics have their discussion closed on every save.
   */
  public function testHistoricTopicPresaveClosesItsDiscussion(): void {
    $hook = $this->hook(FALSE);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([
      ['status' => 2, 'cid' => 0, 'comment_count' => 3],
    ]);
    $field->expects(self::once())->method('setValue')->with([
      ['status' => 1, 'cid' => 0, 'comment_count' => 3],
    ]);

    $hook->closeGrowroomHistoricDiscussion($this->historicTopic($field));
  }

  /**
   * Core bulk moderation hides archive rows and rejects tampered submissions.
   */
  public function testCommentAdminOverviewCannotModerateHistoricComments(): void {
    $comment = $this->historicComment();
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnCallback(static fn (int|string $id) => (int) $id === 30 ? $comment : NULL);
    $storage->method('loadMultiple')->with([30])->willReturn([30 => $comment]);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('comment')->willReturn($storage);
    $hook = $this->hook(FALSE, $manager);

    $form = ['comments' => ['#options' => [30 => [], 31 => []]]];
    $form_state = $this->createMock(FormStateInterface::class);
    $hook->commentAdminOverviewAlter($form, $form_state, 'comment_admin_overview');
    self::assertArrayNotHasKey(30, $form['comments']['#options']);
    self::assertArrayHasKey(31, $form['comments']['#options']);
    self::assertContains([$hook, 'validateCommentAdminOverviewSelection'], $form['#validate']);

    $form_state->method('getValue')->with('comments', [])->willReturn([30]);
    $form_state->expects(self::once())->method('setErrorByName')->with(
      'comments',
      'O acervo histórico do Growroom não pode ser moderado.',
    );
    $hook->validateCommentAdminOverviewSelection($form, $form_state);
  }

  /**
   * Builds the hook using the actual confirmation policy.
   */
  private function hook(bool $confirmed, ?EntityTypeManagerInterface $entityTypeManager = NULL): ForumModerationHooks {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturn($confirmed);
    $policy = new EmailConfirmationPolicy(
      $userData,
      $this->createMock(EntityTypeManagerInterface::class),
    );
    return new ForumModerationHooks(
      $policy,
      $entityTypeManager ?? $this->createMock(EntityTypeManagerInterface::class),
    );
  }

  /**
   * Creates an imported comment attached to a historic topic.
   */
  private function historicComment(): CommentInterface {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('hasField')->with('field_autor_legado')->willReturn(FALSE);
    $comment->method('getCommentedEntity')->willReturn($this->historicTopic());
    $comment->method('getCacheTags')->willReturn(['comment:30']);
    $comment->method('getCacheContexts')->willReturn([]);
    $comment->method('getCacheMaxAge')->willReturn(-1);
    return $comment;
  }

  /**
   * Creates an account mock with the roles and bypass permission under test.
   *
   * @param int $uid
   *   Account ID.
   * @param string[] $roles
   *   The account roles.
   * @param bool $bypass
   *   Whether the account has the core node access bypass.
   */
  private function account(int $uid, array $roles, bool $bypass = FALSE): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn((string) $uid);
    $account->method('getRoles')->willReturn($roles);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => $permission === 'bypass node access' && $bypass,
    );
    return $account;
  }

  /**
   * Builds a new Forum topic owned by a different account.
   */
  private function topic(int $ownerId): NodeInterface {
    $owner = $this->createMock(UserInterface::class);
    $owner->method('id')->willReturn((string) $ownerId);

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('getOwner')->willReturn($owner);
    $topic->method('hasField')->with('field_autor_legado')->willReturn(FALSE);
    $topic->method('id')->willReturn('20');
    $topic->method('uuid')->willReturn('topic-uuid');
    $topic->method('getEntityTypeId')->willReturn('node');
    $topic->method('getCacheTags')->willReturn(['node:20']);
    $topic->method('getCacheContexts')->willReturn([]);
    $topic->method('getCacheMaxAge')->willReturn(-1);
    return $topic;
  }

  /**
   * Creates an imported topic marked with a historic Growroom author.
   */
  private function historicTopic(?FieldItemListInterface $discussion = NULL): NodeInterface {
    $authorReference = $this->createMock(FieldItemListInterface::class);
    $authorReference->method('isEmpty')->willReturn(FALSE);
    $discussion ??= $this->createMock(FieldItemListInterface::class);

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('hasField')->willReturnCallback(
      static fn (string $field_name): bool => in_array($field_name, ['field_autor_legado', 'field_aculta_discussion'], TRUE),
    );
    $topic->method('get')->willReturnCallback(
      static fn (string $field_name): FieldItemListInterface => $field_name === 'field_autor_legado' ? $authorReference : $discussion,
    );
    $topic->method('id')->willReturn('20');
    $topic->method('uuid')->willReturn('historic-topic-uuid');
    $topic->method('getEntityTypeId')->willReturn('node');
    $topic->method('getCacheTags')->willReturn(['node:20']);
    $topic->method('getCacheContexts')->willReturn([]);
    $topic->method('getCacheMaxAge')->willReturn(-1);
    return $topic;
  }

}
