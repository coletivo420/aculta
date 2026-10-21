<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_messenger\Form\FriendRequestsForm;
use Drupal\aculta_user\Entity\AcultaFriendshipInterface;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers the received-request actions shown in the Messages tab.
 */
#[CoversClass(FriendRequestsForm::class)]
#[Group('aculta_messenger')]
final class FriendRequestsFormTest extends UnitTestCase {

  /**
   * Shows active requesters with explicit accept and decline actions.
   */
  public function testBuildFormListsIncomingRequests(): void {
    $request = $this->createMock(AcultaFriendshipInterface::class);
    $request->method('getRequesterId')->willReturn(7);
    $requester = $this->createMock(UserInterface::class);
    $requester->method('id')->willReturn('7');
    $requester->method('getDisplayName')->willReturn('Pessoa solicitante');
    $requester->method('isActive')->willReturn(TRUE);
    $form = $this->form([$request], [7 => $requester]);

    $built = $form->buildForm([], $this->createMock(FormStateInterface::class));

    self::assertSame('Pessoa solicitante', $built['member_7']['name']['#plain_text']);
    self::assertSame('accept_7', $built['member_7']['actions']['accept']['#name']);
    self::assertSame('decline_7', $built['member_7']['actions']['decline']['#name']);
    self::assertContains('user', $built['#cache']['contexts']);
  }

  /**
   * Revalidates that the current account is the request recipient on submit.
   */
  public function testSubmitAcceptsOnlyRequestAddressedToCurrentAccount(): void {
    $request = $this->createMock(AcultaFriendshipInterface::class);
    $request->method('getRecipientId')->willReturn(30);
    $request->method('getRequesterId')->willReturn(7);
    $requester = $this->createMock(UserInterface::class);
    $requester->method('isActive')->willReturn(TRUE);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(7)->willReturn($requester);
    $entityManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityManager->method('getStorage')->with('user')->willReturn($userStorage);
    $friendships = $this->createMock(FriendshipManagerInterface::class);
    $friendships->method('getRelationshipWith')->with(7)->willReturn($request);
    $friendships->expects(self::once())->method('accept')->with($request);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn('30');
    $form = new FriendRequestsForm($friendships, $entityManager, $account);
    $form->setStringTranslation($this->getStringTranslationStub());
    $form->setMessenger($this->createMock(MessengerInterface::class));
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getTriggeringElement')->willReturn(['#name' => 'accept_7']);
    $formState->expects(self::once())->method('setRebuild')->with(TRUE);

    $formArray = [];
    $form->submitForm($formArray, $formState);
  }

  /**
   * Creates the form and user storage needed to render incoming requests.
   *
   * @param \Drupal\aculta_user\Entity\AcultaFriendshipInterface[] $requests
   *   Incoming friendship requests.
   * @param \Drupal\user\UserInterface[] $requesters
   *   Requester entities keyed by their user ID.
   */
  private function form(array $requests, array $requesters): FriendRequestsForm {
    $friendships = $this->createMock(FriendshipManagerInterface::class);
    $friendships->method('getIncomingRequests')->willReturn($requests);
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('loadMultiple')->willReturn($requesters);
    $entityManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityManager->method('getStorage')->with('user')->willReturn($userStorage);
    $account = $this->createMock(AccountProxyInterface::class);
    $form = new FriendRequestsForm($friendships, $entityManager, $account);
    $form->setStringTranslation($this->getStringTranslationStub());
    return $form;
  }

}
