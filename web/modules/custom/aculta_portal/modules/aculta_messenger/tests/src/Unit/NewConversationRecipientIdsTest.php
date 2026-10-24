<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Form\NewConversationForm;
use Drupal\aculta_messenger\Form\PrivateMessageFormValidator;
use Drupal\aculta_messenger\Service\ConversationPolicy;
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
      ),
      $entityTypes,
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

}
