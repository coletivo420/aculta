<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Form\ConversationRelationshipForm;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests authorization for member-owned conversation actions.
 */
#[CoversClass(ConversationRelationshipForm::class)]
#[Group('aculta_messenger')]
final class ConversationRelationshipFormTest extends UnitTestCase {

  /**
   * The first clear action only displays confirmation; it does not mutate.
   */
  public function testClearActionDoesNotMutateBeforeConfirmation(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(42);
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('isMember')->with(42)->willReturn(TRUE);
    $thread->expects(self::never())->method('access');
    $thread->expects(self::never())->method('clearAccountHistory');
    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->method('loadUnchanged')->with(123)->willReturn($thread);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::once())->method('getStorage')
      ->with('private_message_thread')->willReturn($threadStorage);
    $form = new ConversationRelationshipForm(
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $account,
      $entities,
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(CsrfTokenGenerator::class),
    );
    (new \ReflectionProperty(FormBase::class, 'stringTranslation'))
      ->setValue($form, $this->getStringTranslationStub());

    $state = new FormState();
    $state->set('thread_id', 123);
    $state->setTriggeringElement(['#name' => 'clear']);
    $state->setUserInput(['clear' => 'Limpar histórico']);
    (new \ReflectionMethod($form, 'submitGroup'))->invoke($form, $state);

    self::assertSame('confirm', $state->get('clear_step'));
  }

  /**
   * A member whose thread access was revoked cannot clear history.
   */
  public function testClearHistoryRechecksViewAccessBeforeMutation(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(42);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $sender = $this->activeUser(42);

    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('isMember')->with(42)->willReturn(TRUE);
    $thread->expects(self::once())->method('access')
      ->with('view', $sender, TRUE)
      ->willReturn(AccessResult::forbidden());
    $thread->expects(self::never())->method('clearAccountHistory');

    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->expects(self::once())->method('loadUnchanged')->with(123)->willReturn($thread);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects(self::once())->method('loadUnchanged')->with(42)->willReturn($sender);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['private_message_thread', $threadStorage],
      ['user', $userStorage],
    ]);

    $form = new ConversationRelationshipForm(
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $account,
      $entities,
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(CsrfTokenGenerator::class),
    );
    $translation = new \ReflectionProperty(FormBase::class, 'stringTranslation');
    $translation->setValue($form, $this->getStringTranslationStub());

    $state = new FormState();
    $state->set('thread_id', 123);
    $state->setTriggeringElement(['#name' => 'clear_confirm']);
    $state->setUserInput(['clear_confirm' => 'Confirmar limpeza do histórico']);
    $submitGroup = new \ReflectionMethod($form, 'submitGroup');
    $submitGroup->invoke($form, $state);

    self::assertSame(
      'Não foi possível concluir a ação. Tente novamente.',
      $state->get('message'),
    );
  }

  /**
   * A deactivated account cannot clear history through an existing session.
   */
  public function testInactiveAccountCannotClearHistory(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(42);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('isMember')->with(42)->willReturn(TRUE);
    $thread->expects(self::never())->method('access');
    $thread->expects(self::never())->method('clearAccountHistory');
    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->method('loadUnchanged')->with(123)->willReturn($thread);
    $user = $this->createMock(UserInterface::class);
    $user->method('isActive')->willReturn(FALSE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects(self::once())->method('loadUnchanged')->with(42)->willReturn($user);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['private_message_thread', $threadStorage],
      ['user', $userStorage],
    ]);
    $form = new ConversationRelationshipForm(
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $account,
      $entities,
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(CsrfTokenGenerator::class),
    );
    (new \ReflectionProperty(FormBase::class, 'stringTranslation'))
      ->setValue($form, $this->getStringTranslationStub());

    $state = new FormState();
    $state->set('thread_id', 123);
    $state->setTriggeringElement(['#name' => 'clear_confirm']);
    $state->setUserInput(['clear_confirm' => 'Confirmar limpeza do histórico']);
    (new \ReflectionMethod($form, 'submitGroup'))->invoke($form, $state);

    self::assertSame(
      'Não foi possível concluir a ação. Tente novamente.',
      $state->get('message'),
    );
  }

  /**
   * An authorized member clears their own history and invalidates cached lists.
   */
  public function testAuthorizedMemberCanClearHistoryAndInvalidateCache(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(42);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $sender = $this->activeUser(42);
    $message = $this->createMock(PrivateMessageInterface::class);
    $message->method('getCacheTags')->willReturn(['private_message:456']);
    $messages = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $messages->expects(self::once())->method('referencedEntities')->willReturn([$message]);

    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->expects(self::exactly(2))->method('isMember')->with(42)->willReturn(TRUE);
    $thread->expects(self::once())->method('access')
      ->with('view', $sender, TRUE)
      ->willReturn(AccessResult::allowed());
    $thread->expects(self::once())->method('clearAccountHistory')->with($sender);
    $thread->method('getCacheTags')->willReturn(['private_message_thread:123']);
    $thread->expects(self::once())->method('get')->with('private_messages')->willReturn($messages);

    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->expects(self::once())->method('loadUnchanged')->with(123)->willReturn($thread);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects(self::once())->method('loadUnchanged')->with(42)->willReturn($sender);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['private_message_thread', $threadStorage],
      ['user', $userStorage],
    ]);
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects(self::once())
      ->method('invalidateTags')
      ->with(['private_message_thread:123', 'private_message:456']);
    $form = new ConversationRelationshipForm(
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $account,
      $entities,
      $invalidator,
      $this->createMock(CsrfTokenGenerator::class),
    );
    $form->setStringTranslation($this->getStringTranslationStub());

    $state = new FormState();
    $state->set('group', TRUE);
    $state->set('thread_id', 123);
    $state->setTriggeringElement(['#name' => 'clear_confirm']);
    $state->setUserInput(['clear_confirm' => 'Confirmar limpeza do histórico']);
    $build = [];
    $form->submitForm($build, $state);

    self::assertSame('Histórico limpo para você.', $state->get('message'));
  }

  /**
   * Revoked messaging permission blocks history clearing in an active session.
   */
  public function testRevokedPermissionPreventsHistoryClearing(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(42);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $sender = $this->createMock(UserInterface::class);
    $sender->method('id')->willReturn('42');
    $sender->method('isActive')->willReturn(TRUE);
    $sender->method('hasPermission')->willReturnCallback(
      static fn(string $permission): bool => $permission !== 'use private messaging system',
    );
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->expects(self::once())->method('isMember')->with(42)->willReturn(TRUE);
    $thread->expects(self::never())->method('access');
    $thread->expects(self::never())->method('clearAccountHistory');
    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->method('loadUnchanged')->with(123)->willReturn($thread);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadUnchanged')->with(42)->willReturn($sender);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['private_message_thread', $threadStorage],
      ['user', $userStorage],
    ]);
    $invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $invalidator->expects(self::never())->method('invalidateTags');
    $form = new ConversationRelationshipForm(
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $account,
      $entities,
      $invalidator,
      $this->createMock(CsrfTokenGenerator::class),
    );
    $form->setStringTranslation($this->getStringTranslationStub());

    $state = new FormState();
    $state->set('group', TRUE);
    $state->set('thread_id', 123);
    $state->setTriggeringElement(['#name' => 'clear_confirm']);
    $state->setUserInput(['clear_confirm' => 'Confirmar limpeza do histórico']);
    $build = [];
    $form->submitForm($build, $state);

    self::assertSame(
      'Não foi possível concluir a ação. Tente novamente.',
      $state->get('message'),
    );
  }

  /**
   * A profile action cannot create a thread by forging the hidden clear action.
   */
  public function testProfileCannotCreateThreadWithForgedClearAction(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(42);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $target = $this->activeUser(84);
    $users = $this->createMock(EntityStorageInterface::class);
    $users->expects(self::once())->method('load')->with(84)->willReturn($target);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::once())->method('getStorage')->with('user')->willReturn($users);
    $form = new ConversationRelationshipForm(
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $account,
      $entities,
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(CsrfTokenGenerator::class),
    );
    (new \ReflectionProperty(FormBase::class, 'stringTranslation'))
      ->setValue($form, $this->getStringTranslationStub());

    $state = new FormState();
    $state->set('profile', TRUE);
    $state->set('target', 84);
    $state->setTriggeringElement(['#name' => 'clear_confirm']);
    $state->setUserInput(['clear_confirm' => 'Confirmar limpeza do histórico']);
    $build = [];
    $form->submitForm($build, $state);

    self::assertSame('Não foi possível concluir a ação. Tente novamente.', $state->get('message'));
  }

  /**
   * Clear-history controls render only in the conversation group menu.
   */
  public function testClearHistoryControlsAreLimitedToGroupMenu(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(42);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $target = $this->activeUser(84);
    $users = $this->createMock(EntityStorageInterface::class);
    $users->method('load')->with(84)->willReturn($target);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('user')->willReturn($users);
    $friendships = $this->createMock(FriendshipManagerInterface::class);
    $friendships->method('areFriendsWith')->with(84)->willReturn(FALSE);
    $friendships->method('getRelationshipWith')->with(84)->willReturn(NULL);
    $bans = $this->createMock(PrivateMessageBanManagerInterface::class);
    $bans->method('isBanned')->with(84)->willReturn(FALSE);
    $form = new ConversationRelationshipForm(
      $friendships,
      $bans,
      $account,
      $entities,
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(CsrfTokenGenerator::class),
    );
    (new \ReflectionProperty(FormBase::class, 'stringTranslation'))
      ->setValue($form, $this->getStringTranslationStub());

    $profileState = new FormState();
    $profileState->set('profile', TRUE);
    $profileState->set('return_path', '/members/84');
    $profile = $form->buildForm([], $profileState, 84);
    self::assertArrayNotHasKey('clear', $profile['actions']);
    self::assertArrayNotHasKey('clear_confirm', $profile['actions']);

    $groupState = new FormState();
    $groupState->set('group', TRUE);
    $group = $form->buildForm([], $groupState);
    self::assertArrayHasKey('clear', $group['actions']);
  }

  /**
   * A removed participant cannot change relationship state.
   */
  public function testMemberActionRechecksThreadMembershipBeforeMutation(): void {
    $this->assertMemberActionDenied(FALSE, TRUE);
  }

  /**
   * A member whose thread access was revoked cannot change relationship state.
   */
  public function testMemberActionRechecksThreadAccessBeforeMutation(): void {
    $this->assertMemberActionDenied(TRUE, FALSE);
  }

  /**
   * Fails member actions when the target or thread is no longer eligible.
   */
  private function assertMemberActionDenied(bool $targetIsMember, bool $viewAllowed): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(42);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $actor = $this->createMock(UserInterface::class);
    $actor->method('isActive')->willReturn(TRUE);
    $actor->method('hasPermission')->willReturn(TRUE);
    $target = $this->createMock(UserInterface::class);
    $target->method('id')->willReturn('84');
    $target->method('isActive')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->willReturnMap([
      [42, $actor],
      [84, $target],
    ]);
    $userStorage->method('loadUnchanged')->willReturnMap([
      [42, $actor],
      [84, $target],
    ]);

    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('isMember')->willReturnCallback(static fn(int|string $uid): bool => (int) $uid === 42
      || ($targetIsMember && (int) $uid === 84));
    $thread->expects($targetIsMember ? self::once() : self::never())
      ->method('access')->with('view', $actor, TRUE)
      ->willReturn($viewAllowed ? AccessResult::allowed() : AccessResult::forbidden());
    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->expects(self::once())->method('loadUnchanged')->with(123)->willReturn($thread);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnMap([
      ['user', $userStorage],
      ['private_message_thread', $threadStorage],
    ]);
    $banManager = $this->createMock(PrivateMessageBanManagerInterface::class);
    $banManager->expects(self::never())->method('banUser');
    $form = new ConversationRelationshipForm(
      $this->createMock(FriendshipManagerInterface::class),
      $banManager,
      $account,
      $entities,
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->createMock(CsrfTokenGenerator::class),
    );
    (new \ReflectionProperty(FormBase::class, 'stringTranslation'))
      ->setValue($form, $this->getStringTranslationStub());

    $state = new FormState();
    $state->set('member_box', TRUE);
    $state->set('thread_id', 123);
    $state->set('target', 84);
    $state->setTriggeringElement(['#name' => 'ban']);
    $state->setUserInput(['ban' => 'Bloquear']);
    $build = [];
    $form->submitForm($build, $state);

    self::assertSame(
      'Não foi possível concluir a ação. Tente novamente.',
      $state->get('message'),
    );
  }

  /**
   * Creates an active account with the permissions required for messaging.
   */
  private function activeUser(int $uid): UserInterface {
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn((string) $uid);
    $user->method('isActive')->willReturn(TRUE);
    $user->method('hasPermission')->willReturn(TRUE);
    return $user;
  }

}
