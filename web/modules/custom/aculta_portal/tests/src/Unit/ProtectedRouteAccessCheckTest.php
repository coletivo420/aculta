<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Access\ProtectedRouteAccessCheck;
use Drupal\aculta_portal\Account\AcultaUserAgreementPolicy;
use Drupal\commerce_checkout\Controller\CheckoutController;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\aculta_user\Consent\AcultaUserAgreementLedger;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\PrivateKey;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies LMS participation uses Group access without email verification. */
#[Group('aculta_portal')]
final class ProtectedRouteAccessCheckTest extends UnitTestCase {

  /**
   * A current course member can start without confirming their email. */
  public function testCourseAccessDoesNotRequireEmailConfirmation(): void {
    $student = $this->createMock(AccountInterface::class);
    $student->method('id')->willReturn('123');
    $student->method('isAnonymous')->willReturn(FALSE);
    $student->method('hasPermission')->willReturn(FALSE);

    $terms = new AcultaUserAgreementPolicy($this->acceptedTermsLedger());

    $course = $this->createMock(GroupInterface::class);
    $course->expects(self::once())->method('access')->with('take', $student, TRUE)->willReturn(AccessResult::allowed()->cachePerUser());
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getParameter')->with('group')->willReturn($course);

    $checker = new ProtectedRouteAccessCheck(
      $this->createMock(ClassResolverInterface::class),
      $terms,
    );

    self::assertTrue($checker->course($routeMatch, $student)->isAllowed());
  }

  /**
   * An account without the Group take grant still cannot enter the course. */
  public function testCourseAccessStillRequiresGroupTakeAccess(): void {
    $student = $this->createMock(AccountInterface::class);
    $student->method('isAnonymous')->willReturn(FALSE);
    $student->method('hasPermission')->willReturn(FALSE);

    $terms = new AcultaUserAgreementPolicy($this->acceptedTermsLedger());
    $course = $this->createMock(GroupInterface::class);
    $course->method('access')->with('take', $student, TRUE)->willReturn(AccessResult::forbidden()->cachePerUser());
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getParameter')->with('group')->willReturn($course);

    $checker = new ProtectedRouteAccessCheck(
      $this->createMock(ClassResolverInterface::class),
      $terms,
    );

    self::assertFalse($checker->course($routeMatch, $student)->isAllowed());
  }

  /**
   * Visitante anônimo com carrinho na sessão chega ao passo de login.
   *
   * O Commerce permite o anônimo pelo carrinho; e-mail e termos ainda não se
   * aplicam, e os neutros não podem anular esse acesso (neutro é negado).
   */
  public function testAnonymousCheckoutKeepsCommerceAccess(): void {
    $checker = $this->checkoutChecker(AccessResult::allowed()->cachePerUser());

    $result = $checker->checkout($this->checkoutRouteMatch(), $this->anonymousAccount());

    self::assertTrue($result->isAllowed());
  }

  /**
   * Anônimo cujo carrinho o Commerce não reconhece continua barrado. */
  public function testAnonymousCheckoutWithoutCartIsDenied(): void {
    $checker = $this->checkoutChecker(AccessResult::forbidden()->cachePerUser());

    $result = $checker->checkout($this->checkoutRouteMatch(), $this->anonymousAccount());

    self::assertTrue($result->isForbidden());
  }

  /**
   * Authenticated buyers can check out even when their email is unconfirmed. */
  public function testAuthenticatedCheckoutDoesNotRequireConfirmedEmail(): void {
    $checker = $this->checkoutChecker(AccessResult::allowed()->cachePerUser());
    $student = $this->createMock(AccountInterface::class);
    $student->method('isAnonymous')->willReturn(FALSE);
    $student->method('hasPermission')->willReturn(FALSE);
    $student->method('id')->willReturn('7');

    self::assertTrue($checker->checkout($this->checkoutRouteMatch(), $student)->isAllowed());
  }

  /**
   * Accepted terms are still required for authenticated checkout. */
  public function testAuthenticatedCheckoutStillRequiresAcceptedTerms(): void {
    $checker = $this->checkoutChecker(
      AccessResult::allowed()->cachePerUser(),
      'revoked',
    );
    $student = $this->createMock(AccountInterface::class);
    $student->method('isAnonymous')->willReturn(FALSE);
    $student->method('hasPermission')->willReturn(FALSE);

    self::assertTrue($checker->checkout($this->checkoutRouteMatch(), $student)->isForbidden());
  }

  /**
   * Anonymous buyers pass through when Commerce allows the cart. */
  public function testCheckoutStartAllowsAnonymousCartFlow(): void {
    $checker = new ProtectedRouteAccessCheck(
      $this->createMock(ClassResolverInterface::class),
      new AcultaUserAgreementPolicy($this->acceptedTermsLedger()),
    );

    self::assertTrue($checker->checkoutStart($this->anonymousAccount())->isAllowed());
  }

  /**
   * Checkout checker with the Commerce result replaced by a fixture. */
  private function checkoutChecker(AccessResult $commerceAccess, string $termsAction = 'accepted'): ProtectedRouteAccessCheck {
    $controller = $this->createMock(CheckoutController::class);
    $controller->method('checkAccess')->willReturn($commerceAccess);
    $classResolver = $this->createMock(ClassResolverInterface::class);
    $classResolver->method('getInstanceFromDefinition')
      ->with(CheckoutController::class)
      ->willReturn($controller);

    return new ProtectedRouteAccessCheck(
      $classResolver,
      new AcultaUserAgreementPolicy($this->acceptedTermsLedger($termsAction)),
    );
  }

  /**
   * Returns the checkout form route match used by Commerce access tests.
   */
  private function checkoutRouteMatch(): RouteMatchInterface {
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    return $routeMatch;
  }

  /**
   * Returns a visitor account.
   */
  private function anonymousAccount(): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(TRUE);
    return $account;
  }

  /**
   * Um ledger real cuja consulta informa o aceite mais recente como "accepted".
   *
   * O ledger é final e não pode ser duplicado por mock; a consulta ao banco é
   * substituída pela infraestrutura mockada.
   */
  private function acceptedTermsLedger(string $action = 'accepted'): AcultaUserAgreementLedger {
    $select = $this->createMock(SelectInterface::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('orderBy')->willReturnSelf();
    $select->method('range')->willReturnSelf();
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchField')->willReturn($action);
    $select->method('execute')->willReturn($statement);
    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($select);

    return new AcultaUserAgreementLedger(
      $database,
      new RequestStack(),
      $this->createMock(TimeInterface::class),
      $this->createMock(PrivateKey::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(AliasManagerInterface::class),
      $this->createMock(EventDispatcherInterface::class),
    );
  }

}
