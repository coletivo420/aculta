<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Drupal\aculta_messenger\Controller\MessagesController;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Protects per-member conversation actions from manipulated user parameters.
 */
#[CoversClass(MessagesController::class)]
#[Group('aculta_messenger')]
final class MessagesControllerMemberAccessTest extends UnitTestCase {

  /**
   * Rejects a stranger or the current member as a member action target.
   */
  public function testMemberActionsRejectSelfAndNonMemberTargets(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(42);
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->method('access')->with('view', $account, TRUE)->willReturn(AccessResult::allowed());
    $thread->method('isMember')->willReturnCallback(static fn(int|string $uid): bool => in_array((int) $uid, [42, 84], TRUE));
    $controller = $this->controllerFor($account);

    $stranger = $this->user(99);
    try {
      $controller->drawerMemberRelationship($thread, $stranger);
      self::fail('A manipulated user parameter outside the thread must be hidden.');
    }
    catch (NotFoundHttpException) {
      self::assertTrue(TRUE);
    }
    try {
      $controller->memberOptions($thread, $stranger);
      self::fail('A manipulated user parameter outside the thread must not open its options.');
    }
    catch (NotFoundHttpException) {
      self::assertTrue(TRUE);
    }

    $self = $this->user(42);
    try {
      $controller->drawerMemberRelationship($thread, $self);
      self::fail('A member cannot act on their own per-member relationship box.');
    }
    catch (NotFoundHttpException) {
      self::assertTrue(TRUE);
    }
    try {
      $controller->memberOptions($thread, $self);
      self::fail('A member cannot open their own per-member relationship box.');
    }
    catch (NotFoundHttpException) {
      self::assertTrue(TRUE);
    }
  }

  /**
   * Rejects a direct reply call when the account is not a thread member.
   */
  public function testReplyHandlerRejectsNonMemberEvenIfRouteAccessIsBypassed(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(99);
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->expects(self::once())->method('access')->with('view', $account, TRUE)->willReturn(AccessResult::allowed());
    $thread->expects(self::once())->method('isMember')->with(99)->willReturn(FALSE);
    $controller = $this->controllerFor($account);

    $this->expectException(NotFoundHttpException::class);
    $controller->drawerReply($thread);
  }

  /**
   * Does not process a cross-site reply request even for a thread member.
   */
  public function testReplyHandlerNoopsCrossSiteRequestBeforeMutation(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(42);
    $thread = $this->createMock(PrivateMessageThreadInterface::class);
    $thread->expects(self::once())->method('access')->with('view', $account, TRUE)->willReturn(AccessResult::allowed());
    $thread->expects(self::once())->method('isMember')->with(42)->willReturn(TRUE);
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/mensagens/conversa/12/responder', 'POST', server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']));
    $controller = $this->controllerFor($account, $requestStack);

    $response = $controller->drawerReply($thread);

    self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    self::assertEqualsCanonicalizing(['private', 'no-store'], explode(', ', (string) $response->headers->get('Cache-Control')));
  }

  /**
   * Creates a controller with only the current-account dependency initialized.
   */
  private function controllerFor(AccountInterface $account, ?RequestStack $requestStack = NULL): MessagesController {
    $controller = (new \ReflectionClass(MessagesController::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty(MessagesController::class, 'account'))->setValue($controller, $account);
    if ($requestStack !== NULL) {
      (new \ReflectionProperty(MessagesController::class, 'requestStack'))->setValue($controller, $requestStack);
    }
    return $controller;
  }

  /**
   * Creates a route target account stub.
   */
  private function user(int $uid): UserInterface {
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn((string) $uid);
    return $user;
  }

}
