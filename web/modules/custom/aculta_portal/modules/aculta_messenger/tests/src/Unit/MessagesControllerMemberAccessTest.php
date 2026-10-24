<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
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
   * Creates a controller with only the current-account dependency initialized.
   */
  private function controllerFor(AccountInterface $account): MessagesController {
    $controller = (new \ReflectionClass(MessagesController::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty(MessagesController::class, 'account'))->setValue($controller, $account);
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
