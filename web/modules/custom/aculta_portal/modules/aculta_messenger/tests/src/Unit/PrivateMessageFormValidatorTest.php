<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\aculta_messenger\Form\PrivateMessageFormValidator;
use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\user\UserDataInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers the private-message block and flood safeguards.
 *
 * @group aculta_messenger
 */
#[Group('aculta_messenger')]
final class PrivateMessageFormValidatorTest extends UnitTestCase {

  public function testRateLimitRegistersOnlyValidSubmissions(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects($this->once())
      ->method('isAllowed')
      ->with('aculta_messenger.private_message_send', 10, 3600, 'uid:42')
      ->willReturn(TRUE);
    $flood->expects($this->once())
      ->method('register')
      ->with('aculta_messenger.private_message_send', 3600, 'uid:42');

    $formState = $this->formState(FALSE);

    $validator = $this->validator($flood, 42);
    $form = [];
    $validator->validateRateLimit($form, $formState);
  }

  public function testPrivateMessageFormatIsAlwaysForcedToPlainText(): void {
    $formState = new FormState();
    $formState->setValue(['message', 0, 'format'], 'full_html');
    $validator = $this->validator($this->createMock(FloodInterface::class), 42);
    $form = [];

    $validator->enforcePlainTextFormat($form, $formState);

    $this->assertSame('plain_text', $formState->getValue(['message', 0, 'format']));
  }

  public function testRateLimitRejectsWithoutRegisteringAnotherAttempt(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects($this->once())->method('isAllowed')->willReturn(FALSE);
    $flood->expects($this->never())->method('register');

    $formState = $this->formState(FALSE);

    $validator = $this->validator($flood, 42);
    $form = [];
    $validator->validateRateLimit($form, $formState);
    $this->assertSame('Você atingiu o limite temporário de envio de mensagens. Tente novamente mais tarde.', $formState->getErrors()['message']);
  }

  public function testPreviouslyInvalidSubmissionDoesNotConsumeFloodLimit(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->expects($this->never())->method('isAllowed');
    $flood->expects($this->never())->method('register');

    $formState = $this->formState(TRUE);

    $validator = $this->validator($flood, 42);
    $form = [];
    $validator->validateRateLimit($form, $formState);
  }

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
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
    );
    $form = [];
    $validator->validateBlockedRecipients($form, $formState);
    $this->assertSame('Não foi possível enviar a mensagem para esse destinatário.', $formState->getErrors()['members']);
  }

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
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
    );
    $form = [];
    $validator->validateBlockedRecipients($form, $formState);
  }

  private function validator(FloodInterface $flood, int $uid): PrivateMessageFormValidator {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn($uid);
    return new PrivateMessageFormValidator(
      $flood,
      $currentUser,
      $this->translation('Você atingiu o limite temporário de envio de mensagens. Tente novamente mais tarde.'),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(UserDataInterface::class),
      new ConversationPolicy($this->getConfigFactoryStub(['aculta_messenger.settings' => []])),
    );
  }

  private function translation(string $message): TranslationInterface {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturn($message);
    return $translation;
  }

  private function formState(bool $hasErrors): FormStateInterface {
    $property = new \ReflectionProperty(FormState::class, 'anyErrors');
    $property->setAccessible(TRUE);
    $property->setValue(NULL, $hasErrors);
    return new FormState();
  }

}
