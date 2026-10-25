<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Form\AccountOtherDataForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies context-specific return URLs without duplicating CPF/CNPJ storage.
 */
#[CoversClass(AccountOtherDataForm::class)]
#[Group('aculta_portal')]
final class AccountOtherDataStudioReturnTest extends UnitTestCase {

  /**
   * The legacy form continues returning to its original owner route.
   */
  public function testLegacyReturnKeepsOriginalRoute(): void {
    $form = $this->form('aculta_portal.my_data_other');
    self::assertSame('aculta_portal.my_data_other', $this->returnRouteName($form));
  }

  /**
   * Studio submissions return to the same private Studio documents page.
   */
  public function testStudioReturnUsesOwnRoute(): void {
    $form = $this->form('aculta_studio.account_documents');
    self::assertSame('aculta_studio.account_documents', $this->returnRouteName($form));
  }

  /**
   * An unrelated route cannot choose a privileged destination.
   */
  public function testUnknownContextFallsBackToOriginalRoute(): void {
    $form = $this->form('aculta_studio.profile');
    self::assertSame('aculta_portal.my_data_other', $this->returnRouteName($form));
  }

  /**
   * A legitimate checkout return stays ahead of both editing contexts.
   */
  public function testCheckoutDestinationTakesPriority(): void {
    foreach (['aculta_studio.account_documents', 'aculta_portal.my_data_other'] as $route) {
      $form = $this->form($route, '/checkout/19/order_information');
      self::assertSame('base:checkout/19/order_information', $this->returnUrl($form)->getUri());
    }
  }

  /**
   * External or protocol-relative destinations never leave the account host.
   */
  public function testUnsafeCheckoutDestinationsAreIgnored(): void {
    foreach (['https://example.org/checkout/19', '//example.org/checkout/19', '/other/19'] as $destination) {
      $form = $this->form('aculta_studio.account_documents', $destination);
      self::assertSame('aculta_studio.account_documents', $this->returnRouteName($form));
    }
  }

  /**
   * Instantiates the original owner form with only its real dependencies.
   */
  private function form(string $routeName, string $destination = ''): AccountOtherDataForm {
    $request = Request::create('/example');
    if ($destination !== '') {
      $request->query->set('destination', $destination);
    }
    $request->attributes->set('_route', $routeName);
    $stack = new RequestStack();
    $stack->push($request);

    return new AccountOtherDataForm(
      NULL,
      $this->createMock(AccountInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(MessengerInterface::class),
      $stack,
    );
  }

  /**
   * Inspects the private routing decision without executing persistence.
   */
  private function returnRouteName(AccountOtherDataForm $form): string {
    return $this->returnUrl($form)->getRouteName();
  }

  private function returnUrl(AccountOtherDataForm $form): \Drupal\Core\Url {
    return (new \ReflectionMethod(AccountOtherDataForm::class, 'returnUrl'))->invoke($form);
  }

}
