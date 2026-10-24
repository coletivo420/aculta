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
   * Instantiates the original owner form with only its real dependencies.
   */
  private function form(string $routeName): AccountOtherDataForm {
    $request = Request::create('/example');
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
    $result = (new \ReflectionMethod(AccountOtherDataForm::class, 'returnUrl'))->invoke($form);
    return $result->getRouteName();
  }

}
