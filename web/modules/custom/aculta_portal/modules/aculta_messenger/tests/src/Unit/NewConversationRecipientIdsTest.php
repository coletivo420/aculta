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

}
