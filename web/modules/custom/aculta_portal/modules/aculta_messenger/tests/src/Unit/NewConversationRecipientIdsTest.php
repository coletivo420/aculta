<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Form\NewConversationForm;
use Drupal\aculta_user\Identity\PublicMemberIdentityInterface;
use Drupal\aculta_messenger\Form\PrivateMessageFormValidator;
use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\aculta_messenger\Service\ConversationWriteLock;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\private_message\Mapper\PrivateMessageMapperInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests canonicalization of untrusted group-conversation recipients.
 */
#[CoversClass(NewConversationForm::class)]
#[Group('aculta_messenger')]
final class NewConversationRecipientIdsTest extends UnitTestCase {

  /**
   * Duplicate and malformed submitted IDs cannot repeat or alter recipients.
   */
  public function testRecipientIdsAreUniquePositiveIntegers(): void {
    self::assertSame(
      [42, 84, 105],
      NewConversationForm::normalizeRecipientIds([
        '42',
        84,
        '42',
        '105',
        '84',
        0,
        -1,
        '042',
        '84garbage',
        TRUE,
        12.5,
        ['id' => 7],
      ]),
    );
  }

  /**
   * Non-array form input produces no recipients.
   */
  public function testNonArrayInputProducesNoRecipients(): void {
    self::assertSame([], NewConversationForm::normalizeRecipientIds('42'));
    self::assertSame([], NewConversationForm::normalizeRecipientIds(NULL));
  }

  /**
   * Rejects structured message input before resolving recipients.
   */
  public function testArrayMessageInputIsRejected(): void {
    $form = (new \ReflectionClass(NewConversationForm::class))->newInstanceWithoutConstructor();
    $formState = new FormState();
    $formState->setValue('message', ['value' => 'forged']);
    $render = [];
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(static fn(string $message): string => $message);
    $originalContainer = $this->currentDrupalContainer();
    $container = new ContainerBuilder();
    $container->set('string_translation', $translation);
    \Drupal::setContainer($container);

    try {
      $form->validateForm($render, $formState);

      self::assertArrayHasKey('message', $formState->getErrors());
      self::assertSame(['value' => 'forged'], $formState->getValue('message'));
    }
    finally {
      if ($originalContainer instanceof ContainerInterface) {
        \Drupal::setContainer($originalContainer);
      }
      else {
        \Drupal::unsetContainer();
      }
    }
  }

  /**
   * Posted IDs are rechecked against account status and messaging permissions.
   */
  public function testPostedRecipientMustRemainEligible(): void {
    $state = (object) [
      'active' => TRUE,
      'permissions' => ['use private messaging system'],
    ];
    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('isActive')->willReturnCallback(static fn(): bool => $state->active);
    $recipient->method('hasPermission')->willReturnCallback(static fn(string $permission): bool => in_array($permission, $state->permissions, TRUE));
    $recipient->method('id')->willReturn('84');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->with(84)->willReturn($recipient);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->with('user')->willReturn($storage);
    $identity = $this->createMock(PublicMemberIdentityInterface::class);
    $identity->method('publicDisplayName')->with($recipient)->willReturn('public-84');
    $form = new NewConversationForm(
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(PrivateMessageServiceInterface::class),
      $this->createMock(PrivateMessageMapperInterface::class),
      new PrivateMessageFormValidator(
        $this->createMock(FloodInterface::class),
        $this->createMock(AccountProxyInterface::class),
        $this->createMock(TranslationInterface::class),
        $this->createMock(PrivateMessageBanManagerInterface::class),
        $this->createMock(FriendshipManagerInterface::class),
        $this->createMock(UserDataInterface::class),
        $this->createMock(LockBackendInterface::class),
        new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
        new ConversationWriteLock($this->createMock(LockBackendInterface::class), $entityTypes),
      ),
      $entityTypes,
      $identity,
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
    );
    $formState = new FormState();
    $formState->setUserInput(['members' => ['84']]);
    $recipients = new \ReflectionMethod(NewConversationForm::class, 'recipients');

    self::assertSame([], $recipients->invoke($form, $formState));

    $state->permissions[] = 'access user profiles';
    self::assertSame([$recipient], $recipients->invoke($form, $formState));

    $state->active = FALSE;
    self::assertSame([], $recipients->invoke($form, $formState));
  }

  /**
   * Rechecks friend-only privacy at submit time before creating a thread.
   */
  public function testNewThreadRevalidatesPrivacyBeforeContribCreatesIt(): void {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);
    $currentUser->method('hasRole')->willReturn(FALSE);
    $sender = $this->user(42);
    $recipient = $this->user(84);
    $candidateThread = $this->createMock(PrivateMessageThreadInterface::class);
    $candidateThread->method('getMembersId')->willReturn([42, 84]);
    $state = (object) ['privacy' => NULL];
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::exactly(2))->method('get')
      ->willReturnCallback(static fn(string $module, int $uid, string $key): ?string => $state->privacy);
    $friendships = $this->createMock(FriendshipManagerInterface::class);
    $friendships->expects(self::once())->method('areFriendsWith')->with(84)->willReturn(FALSE);
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturn('Não foi possível iniciar uma conversa com este destinatário.');
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $validator = new PrivateMessageFormValidator(
      $this->createMock(FloodInterface::class),
      $currentUser,
      $translation,
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $friendships,
      $userData,
      $this->createMock(LockBackendInterface::class),
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      new ConversationWriteLock($this->createMock(LockBackendInterface::class), $entityTypes),
    );
    $mapper = $this->createMock(PrivateMessageMapperInterface::class);
    $mapper->expects(self::once())->method('getThreadIdForMembers')->with([$sender, $recipient])->willReturn(FALSE);
    $privateMessageService = $this->createMock(PrivateMessageServiceInterface::class);
    $privateMessageService->expects(self::never())->method('getThreadForMembers');
    $newConversationForm = new NewConversationForm(
      $currentUser,
      $privateMessageService,
      $mapper,
      $validator,
      $entityTypes,
      $this->createMock(PublicMemberIdentityInterface::class),
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
    );
    $formState = new FormState();
    $anyErrors = new \ReflectionProperty(FormState::class, 'anyErrors');
    $anyErrors->setAccessible(TRUE);
    $anyErrors->setValue(NULL, FALSE);
    $formState->set('pmt_entity', $candidateThread);
    $initialForm = [];

    // The target was initially open to messages; they changed the setting
    // before the final submit handler reached the creation boundary.
    $validator->validateNewConversationPrivacy($initialForm, $formState);
    self::assertSame([], $formState->getErrors());
    $state->privacy = 'friends';

    $resolve = new \ReflectionMethod(NewConversationForm::class, 'resolveThreadForSubmission');
    self::assertNull($resolve->invoke($newConversationForm, [$sender, $recipient], $formState));
    self::assertTrue($formState->get('aculta_messenger_new_conversation_privacy_rejected'));
  }

  /**
   * A block added after validation cannot leave an empty persisted thread.
   */
  public function testSubmitRechecksBlocksBeforeCreatingNewThread(): void {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);
    $sender = $this->eligibleUser(42);
    $recipient = $this->eligibleUser(84);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnCallback(static fn(int $uid): ?UserInterface => match ($uid) {
      42 => $sender,
      84 => $recipient,
      default => NULL,
    });
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->with('user')->willReturn($storage);
    $identity = $this->createMock(PublicMemberIdentityInterface::class);
    $identity->method('publicDisplayName')->willReturn('membro');
    $candidateThread = $this->createMock(PrivateMessageThreadInterface::class);
    $candidateThread->method('getMembersId')->willReturn([42, 84]);
    $state = (object) ['blocked' => FALSE];
    $banManager = $this->createMock(PrivateMessageBanManagerInterface::class);
    $banManager->method('isBanned')->willReturn(FALSE);
    $banManager->method('isCurrentUserBannedByUser')
      ->with(84)
      ->willReturnCallback(static fn(): bool => $state->blocked);
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturn('Não foi possível enviar a mensagem para esse destinatário.');
    $validator = new PrivateMessageFormValidator(
      $this->createMock(FloodInterface::class),
      $currentUser,
      $translation,
      $banManager,
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(UserDataInterface::class),
      $this->createMock(LockBackendInterface::class),
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      new ConversationWriteLock($this->createMock(LockBackendInterface::class), $entityTypes),
    );
    $mapper = $this->createMock(PrivateMessageMapperInterface::class);
    $mapper->expects(self::never())->method('getThreadIdForMembers');
    $privateMessageService = $this->createMock(PrivateMessageServiceInterface::class);
    $privateMessageService->expects(self::never())->method('getThreadForMembers');
    $form = new NewConversationForm(
      $currentUser,
      $privateMessageService,
      $mapper,
      $validator,
      $entityTypes,
      $identity,
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
    );
    $formState = new FormState();
    $formState->setUserInput(['members' => ['84']]);
    $formState->setValue('message', 'Primeira mensagem');
    $formState->set('pmt_entity', $candidateThread);
    $formState->set('existing_conversation', FALSE);
    $render = [];

    // Initial validation succeeds; a block appears before Form API submits.
    $validator->validateBlockedRecipients($render, $formState);
    self::assertSame([], $formState->getErrors());
    $state->blocked = TRUE;

    $form->submitForm($render, $formState);

    self::assertSame(
      'Não foi possível enviar a mensagem para esse destinatário.',
      $formState->getErrors()['members'],
    );
  }

  /**
   * Existing conversations stay replyable after privacy settings change.
   */
  public function testExistingThreadDoesNotReapplyNewConversationPrivacy(): void {
    $existingThread = $this->createMock(PrivateMessageThreadInterface::class);
    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->expects(self::once())->method('load')->with(77)->willReturn($existingThread);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->expects(self::once())->method('getStorage')->with('private_message_thread')->willReturn($threadStorage);
    $mapper = $this->createMock(PrivateMessageMapperInterface::class);
    $mapper->expects(self::once())->method('getThreadIdForMembers')->willReturn(77);
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(42);
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::never())->method('get');
    $validator = new PrivateMessageFormValidator(
      $this->createMock(FloodInterface::class),
      $currentUser,
      $this->createMock(TranslationInterface::class),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $this->createMock(FriendshipManagerInterface::class),
      $userData,
      $this->createMock(LockBackendInterface::class),
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
      new ConversationWriteLock($this->createMock(LockBackendInterface::class), $entityTypes),
    );
    $newConversationForm = new NewConversationForm(
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(PrivateMessageServiceInterface::class),
      $mapper,
      $validator,
      $entityTypes,
      $this->createMock(PublicMemberIdentityInterface::class),
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
    );
    $formState = new FormState();
    $resolve = new \ReflectionMethod(NewConversationForm::class, 'resolveThreadForSubmission');

    self::assertSame($existingThread, $resolve->invoke($newConversationForm, [$this->user(42), $this->user(84)], $formState));
  }

  /**
   * Returns the original Drupal container when the test harness has one.
   */
  private function currentDrupalContainer(): ?ContainerInterface {
    try {
      return \Drupal::getContainer();
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * Creates a member account stub.
   */
  private function user(int $uid): UserInterface {
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn((string) $uid);
    return $user;
  }

  /**
   * Creates an active user with both native private-message permissions.
   */
  private function eligibleUser(int $uid): UserInterface {
    $user = $this->user($uid);
    $user->method('isActive')->willReturn(TRUE);
    $user->method('hasPermission')->willReturn(TRUE);
    return $user;
  }

}
