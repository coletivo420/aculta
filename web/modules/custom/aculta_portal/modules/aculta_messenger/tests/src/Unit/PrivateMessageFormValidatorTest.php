<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\aculta_messenger\Form\PrivateMessageFormValidator;
use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\aculta_messenger\Service\ConversationWriteLock;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the private-message block and flood safeguards.
 *
 * @group aculta_messenger
 */
#[Group('aculta_messenger')]
final class PrivateMessageFormValidatorTest extends UnitTestCase {

  /**
   * Tests the rate limit registers only valid submissions behavior.
   */
  public function testRateLimitChecksValidSubmissionsWithoutRegisteringBeforeSave(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects($this->once())
      ->method('isAllowed')
      ->with('aculta_messenger.private_message_send', 10, 3600, 'uid:42')
      ->willReturn(TRUE);
    $flood->expects($this->never())->method('register');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')->with('aculta_messenger.private_message_flood.42', 180.0)->willReturn(TRUE);
    $lock->expects(self::never())->method('release');

    $formState = $this->formState(FALSE);

    $validator = $this->validator($flood, 42, $lock);
    $form = [];
    $validator->validateRateLimit($form, $formState);
  }

  /**
   * Successful persistence registers one send in the rolling flood window.
   */
  public function testPersistedSubmissionRegistersFloodLimit(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects(self::once())->method('isAllowed')->willReturn(TRUE);
    $flood->expects(self::once())
      ->method('register')
      ->with('aculta_messenger.private_message_send', 3600, 'uid:42');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')->with('aculta_messenger.private_message_flood.42', 180.0)->willReturn(TRUE);
    $lock->expects(self::once())->method('release')->with('aculta_messenger.private_message_flood.42');
    $validator = $this->validator($flood, 42, $lock);
    $form = [];

    $validator->validateRateLimit($form, $this->formState(FALSE));
    $validator->registerRateLimit($form, new FormState());
  }

  /**
   * Existing-thread replies hold the shared lock until their save callback.
   */
  public function testExistingThreadReplyHoldsWriteLockUntilFloodRegistration(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects(self::once())->method('isAllowed')->willReturn(TRUE);
    $flood->expects(self::once())->method('register');
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('id')->willReturn('123');
    $freshThread = $this->createMock(PrivateMessageThreadInterface::class);
    $freshThread->method('id')->willReturn('123');
    $freshThread->method('getMembersId')->willReturn([42, 84]);
    $freshThread->method('isMember')->with(42)->willReturn(TRUE);
    $freshThread->method('access')->with('view', self::isInstanceOf(AccountProxyInterface::class), TRUE)->willReturn(AccessResult::allowed());
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('loadUnchanged')->with(123)->willReturn($freshThread);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->expects(self::once())->method('getStorage')->with('private_message_thread')->willReturn($storage);
    $lockCalls = [];
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::exactly(2))->method('acquire')->willReturnCallback(static function (string $name, float $lease) use (&$lockCalls): bool {
      $lockCalls[] = ['acquire', $name, $lease];
      return TRUE;
    });
    $lock->expects(self::exactly(2))->method('release')->willReturnCallback(static function (string $name) use (&$lockCalls): void {
      $lockCalls[] = ['release', $name];
    });
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);
    $sender = $this->createMock(UserInterface::class);
    $sender->method('isActive')->willReturn(TRUE);
    $sender->method('hasPermission')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(42)->willReturn($sender);
    $senderEntityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $senderEntityTypes->method('getStorage')->with('user')->willReturn($userStorage);
    $validator = new PrivateMessageFormValidator(
      $flood,
      $currentUser,
      $this->translation('blocked'),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(UserDataInterface::class),
      $lock,
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      new ConversationWriteLock($lock, $entityTypes),
      $senderEntityTypes,
    );
    $form = [];
    $formState = $this->formState(FALSE);
    $formState->set('thread', $thread);

    $validator->validateRateLimit($form, $formState);
    self::assertSame($freshThread, $formState->get('thread'));
    self::assertSame([
      ['acquire', 'aculta_messenger.private_message_flood.42', 180.0],
      ['acquire', 'aculta_messenger:conversation_write:123', 180.0],
    ], $lockCalls);

    $validator->registerRateLimit($form, $formState);
    self::assertSame([
      ['acquire', 'aculta_messenger.private_message_flood.42', 180.0],
      ['acquire', 'aculta_messenger:conversation_write:123', 180.0],
      ['release', 'aculta_messenger:conversation_write:123'],
      ['release', 'aculta_messenger.private_message_flood.42'],
    ], $lockCalls);
  }

  /**
   * Rechecks bilateral blocks after acquiring the fresh conversation lock.
   */
  public function testBlockAddedDuringValidationRejectsReplyAndReleasesLocks(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects(self::once())->method('isAllowed')->willReturn(TRUE);
    $flood->expects(self::never())->method('register');
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('id')->willReturn('123');
    $thread->method('getMembersId')->willReturn([42, 84]);
    $freshThread = $this->createMock(PrivateMessageThreadInterface::class);
    $freshThread->method('id')->willReturn('123');
    $freshThread->method('getMembersId')->willReturn([42, 84]);
    $freshThread->method('isMember')->with(42)->willReturn(TRUE);
    $freshThread->method('access')->with('view', self::isInstanceOf(AccountProxyInterface::class), TRUE)->willReturn(AccessResult::allowed());
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('loadUnchanged')->with(123)->willReturn($freshThread);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->expects(self::once())->method('getStorage')->with('private_message_thread')->willReturn($storage);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::exactly(2))->method('acquire')->willReturn(TRUE);
    $lock->expects(self::exactly(2))->method('release');
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);
    $banManager = $this->createMock(PrivateMessageBanManagerInterface::class);
    $banManager->method('isBanned')->willReturn(FALSE);
    $banManager->expects(self::exactly(2))
      ->method('isCurrentUserBannedByUser')
      ->with(84)
      ->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $validator = new PrivateMessageFormValidator(
      $flood,
      $currentUser,
      $this->translation('Não foi possível enviar a mensagem para esse destinatário.'),
      $banManager,
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(UserDataInterface::class),
      $lock,
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      new ConversationWriteLock($lock, $entityTypes),
      $this->senderEntityTypeManager(TRUE, TRUE),
    );
    $form = [];
    $formState = $this->formState(FALSE);
    $formState->set('thread', $thread);

    $validator->validateBlockedRecipients($form, $formState);
    self::assertFalse($formState->hasAnyErrors());
    $validator->validateRateLimit($form, $formState);

    self::assertSame('Não foi possível enviar a mensagem para esse destinatário.', $formState->getErrors()['members']);
  }

  /**
   * A sender removed after form build cannot submit to the stale thread.
   */
  public function testRemovedSenderIsRejectedAfterThreadReload(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects(self::once())->method('isAllowed')->willReturn(TRUE);
    $flood->expects(self::never())->method('register');
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('id')->willReturn('123');
    $freshThread = $this->createMock(PrivateMessageThreadInterface::class);
    $freshThread->method('id')->willReturn('123');
    $freshThread->method('isMember')->with(42)->willReturn(FALSE);
    $freshThread->expects(self::never())->method('access');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('loadUnchanged')->with(123)->willReturn($freshThread);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->expects(self::once())->method('getStorage')->with('private_message_thread')->willReturn($storage);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::exactly(2))->method('acquire')->willReturn(TRUE);
    $lock->expects(self::exactly(2))->method('release');
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);
    $validator = new PrivateMessageFormValidator(
      $flood,
      $currentUser,
      $this->translation('Não foi possível enviar uma mensagem para essa conversa.'),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(UserDataInterface::class),
      $lock,
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      new ConversationWriteLock($lock, $entityTypes),
      $this->senderEntityTypeManager(TRUE, TRUE),
    );
    $form = [];
    $formState = $this->formState(FALSE);
    $formState->set('thread', $thread);

    $validator->validateRateLimit($form, $formState);

    self::assertSame(
      'Não foi possível enviar uma mensagem para essa conversa.',
      $formState->getErrors()['message'],
    );
  }

  /**
   * An ineligible sender cannot reply to an existing thread.
   */
  #[DataProvider('ineligibleSenderCases')]
  public function testIneligibleSenderCannotReplyToExistingThread(bool $active, bool $permissions): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects(self::once())->method('isAllowed')->willReturn(TRUE);
    $flood->expects(self::never())->method('register');
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('id')->willReturn('123');
    $freshThread = $this->createMock(PrivateMessageThreadInterface::class);
    $freshThread->method('id')->willReturn('123');
    $freshThread->expects(self::never())->method('isMember');
    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->method('loadUnchanged')->with(123)->willReturn($freshThread);
    $threadEntityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $threadEntityTypes->method('getStorage')->with('private_message_thread')->willReturn($threadStorage);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::exactly(2))->method('acquire')->willReturn(TRUE);
    $lock->expects(self::exactly(2))->method('release');
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);
    $senderEntityTypes = $this->senderEntityTypeManager($active, $permissions);
    $validator = new PrivateMessageFormValidator(
      $flood,
      $currentUser,
      $this->translation('Não foi possível enviar uma mensagem para essa conversa.'),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(UserDataInterface::class),
      $lock,
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      new ConversationWriteLock($lock, $threadEntityTypes),
      $senderEntityTypes,
    );
    $form = [];
    $formState = $this->formState(FALSE);
    $formState->set('thread', $thread);

    $validator->validateRateLimit($form, $formState);

    self::assertSame(
      'Não foi possível enviar uma mensagem para essa conversa.',
      $formState->getErrors()['message'],
    );
  }

  /**
   * Sender states rejected at the submit boundary.
   */
  public static function ineligibleSenderCases(): array {
    return [
      'account deactivated after form build' => [FALSE, TRUE],
      'messaging permissions revoked after form build' => [TRUE, FALSE],
    ];
  }

  /**
   * Tests the private message format is always forced to plain text behavior.
   */
  public function testPrivateMessageFormatIsAlwaysForcedToPlainText(): void {
    $formState = new FormState();
    $formState->setValue(['message', 0, 'format'], 'full_html');
    $validator = $this->validator($this->createMock(FloodInterface::class), 42);
    $form = [];

    $validator->enforcePlainTextFormat($form, $formState);

    $this->assertSame('plain_text', $formState->getValue(['message', 0, 'format']));
  }

  /**
   * Tests the rate limit rejects without registering another attempt behavior.
   */
  public function testRateLimitRejectsWithoutRegisteringAnotherAttempt(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects($this->once())->method('isAllowed')->willReturn(FALSE);
    $flood->expects($this->never())->method('register');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')->willReturn(TRUE);
    $lock->expects(self::once())->method('release')->with('aculta_messenger.private_message_flood.42');

    $formState = $this->formState(FALSE);

    $validator = $this->validator($flood, 42, $lock);
    $form = [];
    $validator->validateRateLimit($form, $formState);
    $this->assertSame('Você atingiu o limite temporário de envio de mensagens. Tente novamente mais tarde.', $formState->getErrors()['message']);
  }

  /**
   * A concurrent submission cannot pass the flood check while it is locked.
   */
  public function testConcurrentSubmissionIsRejectedBeforeFloodCheck(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects(self::never())->method('isAllowed');
    $flood->expects(self::never())->method('register');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())
      ->method('acquire')
      ->with('aculta_messenger.private_message_flood.42', 180.0)
      ->willReturn(FALSE);
    $lock->expects(self::never())->method('release');
    $validator = $this->validator($flood, 42, $lock);
    $form = [];
    $formState = $this->formState(FALSE);

    $validator->validateRateLimit($form, $formState);

    self::assertArrayHasKey('message', $formState->getErrors());
  }

  /**
   * Flood backend errors release the in-flight lock for later requests.
   */
  public function testFloodBackendExceptionReleasesLock(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects(self::once())->method('isAllowed')->willThrowException(new \RuntimeException('Backend unavailable.'));
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->expects(self::once())->method('acquire')->willReturn(TRUE);
    $lock->expects(self::once())->method('release')->with('aculta_messenger.private_message_flood.42');
    $validator = $this->validator($flood, 42, $lock);
    $form = [];

    $this->expectException(\RuntimeException::class);
    $validator->validateRateLimit($form, $this->formState(FALSE));
  }

  /**
   * Tests invalid submissions do not consume the flood limit.
   */
  public function testPreviouslyInvalidSubmissionDoesNotConsumeFloodLimit(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects($this->never())->method('isAllowed');
    $flood->expects($this->never())->method('register');

    $formState = $this->formState(TRUE);

    $validator = $this->validator($flood, 42);
    $form = [];
    $validator->validateRateLimit($form, $formState);
  }

  /**
   * Tests the blocked recipient is rejected with generic error behavior.
   */
  public function testBlockedRecipientIsRejectedWithGenericError(): void {
    $flood = $this->createMock(FloodInterface::class);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('getMembersId')->willReturn([42, 84]);
    $banManager = $this->createMock(PrivateMessageBanManagerInterface::class);
    $banManager->expects($this->once())
      ->method('isCurrentUserBannedByUser')
      ->with(84)
      ->willReturn(TRUE);

    $formState = $this->formState(FALSE);
    $formState->set('thread', $thread);

    $validator = new PrivateMessageFormValidator(
      $flood,
      $currentUser,
      $this->translation('Não foi possível enviar a mensagem para esse destinatário.'),
      $banManager,
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(UserDataInterface::class),
      $this->createMock(LockBackendInterface::class),
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
        $this->writeLock(),
      $this->createMock(EntityTypeManagerInterface::class),
    );
    $form = [];
    $validator->validateBlockedRecipients($form, $formState);
    $this->assertSame('Não foi possível enviar a mensagem para esse destinatário.', $formState->getErrors()['members']);
  }

  /**
   * The preference does not disable replies in existing threads.
   */
  public function testFriendOnlyPreferenceDoesNotBlockRepliesToExistingThread(): void {
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('getMembersId')->willReturn([42, 84]);
    $banManager = $this->createMock(PrivateMessageBanManagerInterface::class);
    $banManager->method('isBanned')->willReturn(FALSE);
    $banManager->method('isCurrentUserBannedByUser')->willReturn(FALSE);
    $friendships = $this->createMock(FriendshipManagerInterface::class);
    $friendships->expects($this->never())->method('areFriendsWith');
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects($this->never())->method('get');
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);

    $formState = $this->formState(FALSE);
    $formState->set('pmt_entity', $thread);
    $formState->set('existing_conversation', TRUE);
    $validator = new PrivateMessageFormValidator(
      $this->createMock(FloodInterface::class),
      $currentUser,
      $this->translation('blocked'),
      $banManager,
      $friendships,
      $userData,
      $this->createMock(LockBackendInterface::class),
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      $this->writeLock(),
      $this->createMock(EntityTypeManagerInterface::class),
    );
    $form = [];

    $validator->validateBlockedRecipients($form, $formState);
    $validator->validateNewConversationPrivacy($form, $formState);

    $this->assertSame([], $formState->getErrors());
  }

  /**
   * The preference still applies when creating a thread.
   */
  public function testFriendOnlyPreferenceStillRestrictsNewThreads(): void {
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('getMembersId')->willReturn([42, 84]);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);
    $currentUser->method('hasRole')->willReturn(FALSE);
    $friendships = $this->createMock(FriendshipManagerInterface::class);
    $friendships->expects($this->once())->method('areFriendsWith')->with(84)->willReturn(FALSE);
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->with('aculta_messenger', 84, 'message_privacy')->willReturn('friends');
    $formState = $this->formState(FALSE);
    $formState->set('pmt_entity', $thread);
    $validator = new PrivateMessageFormValidator(
      $this->createMock(FloodInterface::class),
      $currentUser,
      $this->translation('Não foi possível iniciar uma conversa com este destinatário.'),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $friendships,
      $userData,
      $this->createMock(LockBackendInterface::class),
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      $this->writeLock(),
      $this->createMock(EntityTypeManagerInterface::class),
    );
    $form = [];

    $validator->validateNewConversationPrivacy($form, $formState);

    $this->assertSame('Não foi possível iniciar uma conversa com este destinatário.', $formState->getErrors()['members']);
  }

  /**
   * Staff roles may start conversations despite a member's preference.
   */
  public function testAdministratorAndEditorCanStartFriendOnlyConversations(): void {
    foreach (['administrator', 'content_editor'] as $staffRole) {
      $thread = $this->createMock(PrivateMessageThreadInterface::class);
      $thread->method('getMembersId')->willReturn([42, 84]);
      $currentUser = $this->createMock(AccountProxyInterface::class);
      $currentUser->method('id')->willReturn(42);
      $currentUser->method('hasRole')->willReturnCallback(static fn(string $role): bool => $role === $staffRole);
      $friendships = $this->createMock(FriendshipManagerInterface::class);
      $friendships->expects($this->never())->method('areFriendsWith');
      $userData = $this->createMock(UserDataInterface::class);
      $userData->expects($this->never())->method('get');
      $formState = $this->formState(FALSE);
      $formState->set('pmt_entity', $thread);
      $validator = new PrivateMessageFormValidator(
        $this->createMock(FloodInterface::class),
        $currentUser,
        $this->translation('Não foi possível iniciar uma conversa com este destinatário.'),
        $this->createMock(PrivateMessageBanManagerInterface::class),
        $friendships,
        $userData,
        $this->createMock(LockBackendInterface::class),
        new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      $this->writeLock(),
        $this->createMock(EntityTypeManagerInterface::class),
      );
      $form = [];

      $validator->validateNewConversationPrivacy($form, $formState);

      self::assertSame([], $formState->getErrors(), $staffRole . ' may initiate the conversation.');
    }
  }

  /**
   * Staff exceptions do not bypass the recipient's bilateral block.
   */
  public function testStaffCannotStartConversationWithMemberWhoBlockedThem(): void {
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('getMembersId')->willReturn([42, 84]);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);
    $currentUser->method('hasRole')->willReturn(TRUE);
    $banManager = $this->createMock(PrivateMessageBanManagerInterface::class);
    $banManager->expects(self::once())->method('isBanned')->with(84)->willReturn(FALSE);
    $banManager->expects(self::once())->method('isCurrentUserBannedByUser')->with(84)->willReturn(TRUE);
    $friendships = $this->createMock(FriendshipManagerInterface::class);
    $friendships->expects(self::never())->method('areFriendsWith');
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::never())->method('get');
    $formState = $this->formState(FALSE);
    $formState->set('pmt_entity', $thread);
    $validator = new PrivateMessageFormValidator(
      $this->createMock(FloodInterface::class),
      $currentUser,
      $this->translation('Não foi possível enviar a mensagem para esse destinatário.'),
      $banManager,
      $friendships,
      $userData,
      $this->createMock(LockBackendInterface::class),
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      $this->writeLock(),
      $this->createMock(EntityTypeManagerInterface::class),
    );
    $form = [];

    $validator->validateBlockedRecipients($form, $formState);
    $validator->validateNewConversationPrivacy($form, $formState);

    self::assertSame('Não foi possível enviar a mensagem para esse destinatário.', $formState->getErrors()['members']);
  }

  /**
   * Tests the existing validation error skips recipient lookup behavior.
   */
  public function testExistingValidationErrorSkipsRecipientLookup(): void {
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->expects($this->never())->method('getMembersId');
    $banManager = $this->createMock(PrivateMessageBanManagerInterface::class);
    $banManager->expects($this->never())->method('isCurrentUserBannedByUser');
    $formState = $this->formState(TRUE);

    $validator = new PrivateMessageFormValidator(
      $this->createMock(FloodInterface::class),
      $this->createMock(AccountProxyInterface::class),
      $this->translation('Não foi possível enviar a mensagem para esse destinatário.'),
      $banManager,
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(UserDataInterface::class),
      $this->createMock(LockBackendInterface::class),
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      $this->writeLock(),
      $this->createMock(EntityTypeManagerInterface::class),
    );
    $form = [];
    $validator->validateBlockedRecipients($form, $formState);
  }

  /**
   * Provides the validator behavior.
   */
  private function validator(FloodInterface $flood, int $uid, ?LockBackendInterface $lock = NULL): PrivateMessageFormValidator {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn($uid);
    return new PrivateMessageFormValidator(
      $flood,
      $currentUser,
      $this->translation('Você atingiu o limite temporário de envio de mensagens. Tente novamente mais tarde.'),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(UserDataInterface::class),
      $lock ?? $this->createMock(LockBackendInterface::class),
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      $this->writeLock(),
      $this->createMock(EntityTypeManagerInterface::class),
    );
  }

  /**
   * Creates an idle per-thread write-lock fixture.
   */
  private function writeLock(): ConversationWriteLock {
    return new ConversationWriteLock(
      $this->createMock(LockBackendInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
    );
  }

  /**
   * Provides the translation behavior.
   */
  private function translation(string $message): TranslationInterface {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturn($message);
    return $translation;
  }

  /**
   * Provides the formState behavior.
   */
  private function formState(bool $hasErrors): FormStateInterface {
    $property = new \ReflectionProperty(FormState::class, 'anyErrors');
    $property->setAccessible(TRUE);
    $property->setValue(NULL, $hasErrors);
    return new FormState();
  }

  /**
   * Creates an entity manager returning a sender with selected eligibility.
   */
  private function senderEntityTypeManager(bool $active, bool $permissions): EntityTypeManagerInterface {
    $sender = $this->createMock(UserInterface::class);
    $sender->method('isActive')->willReturn($active);
    $sender->method('hasPermission')->willReturn($permissions);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($sender);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->with('user')->willReturn($storage);
    return $entityTypes;
  }

}
