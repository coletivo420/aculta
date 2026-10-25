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
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
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
    $threadStorage->method('load')->with(123)->willReturn($thread);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::once())->method('getStorage')
      ->with('private_message_thread')->willReturn($threadStorage);
    $form = new ConversationRelationshipForm(
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $account,
      $entities,
      $this->createMock(PrivateMessageServiceInterface::class),
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

    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('isMember')->with(42)->willReturn(TRUE);
    $thread->expects(self::once())->method('access')
      ->with('view', $account, TRUE)
      ->willReturn(AccessResult::forbidden());
    $thread->expects(self::never())->method('clearAccountHistory');

    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->expects(self::once())->method('load')->with(123)->willReturn($thread);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::once())->method('getStorage')
      ->with('private_message_thread')->willReturn($threadStorage);

    $form = new ConversationRelationshipForm(
      $this->createMock(FriendshipManagerInterface::class),
      $this->createMock(PrivateMessageBanManagerInterface::class),
      $account,
      $entities,
      $this->createMock(PrivateMessageServiceInterface::class),
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
    $thread->method('access')->with('view', $account, TRUE)->willReturn(AccessResult::allowed());
    $thread->expects(self::never())->method('clearAccountHistory');
    $threadStorage = $this->createMock(EntityStorageInterface::class);
    $threadStorage->method('load')->with(123)->willReturn($thread);
    $user = $this->createMock(UserInterface::class);
    $user->method('isActive')->willReturn(FALSE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects(self::once())->method('load')->with(42)->willReturn($user);
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
      $this->createMock(PrivateMessageServiceInterface::class),
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

}
