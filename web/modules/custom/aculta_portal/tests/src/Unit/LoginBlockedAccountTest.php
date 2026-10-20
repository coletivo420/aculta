<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Form\PortalFormCallbacks;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\Form\UserLoginForm;
use Drupal\user\UserAuthenticationInterface;
use Drupal\user\UserFloodControlInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Blocked accounts must not be distinguishable from unknown ones on login.
 *
 * The assertions cover what the user sees (errors, flood state) and which
 * Core step runs, not only whether a method was called.
 */
#[Group('aculta_portal')]
final class LoginBlockedAccountTest extends UnitTestCase {

  private function callbacks(UserAuthenticationInterface $auth, UserFloodControlInterface $flood): PortalFormCallbacks {
    $floodConfig = $this->createMock(ImmutableConfig::class);
    $floodConfig->method('get')->willReturnMap([['ip_limit', 50], ['ip_window', 3600]]);
    $configs = $this->createMock(ConfigFactoryInterface::class);
    $configs->method('get')->with('user.flood')->willReturn($floodConfig);
    return new PortalFormCallbacks(
      $this->createMock(\Drupal\Core\Messenger\MessengerInterface::class),
      $this->createMock(\Drupal\Core\Session\AccountProxyInterface::class),
      $this->createMock(\Drupal\user\UserDataInterface::class),
      $this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class),
      $this->createMock(\Drupal\Core\StringTranslation\TranslationInterface::class),
      $auth,
      $flood,
      $configs,
      new \Drupal\aculta_portal\Email\EmailConfirmationRequester(
        $this->createMock(\Drupal\email_confirmer\EmailConfirmerManagerInterface::class),
        $this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class),
        $this->createMock(\Drupal\Core\Flood\FloodInterface::class),
        $this->createMock(\Drupal\Core\Lock\LockBackendInterface::class),
        $this->createMock(\Drupal\Core\Logger\LoggerChannelInterface::class),
        $this->createMock(\Drupal\aculta_mailer\Api\AcultaMailerInterface::class),
        $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class),
        new \Drupal\Component\Uuid\Php(),
        $this->createMock(\Drupal\Core\Routing\UrlGeneratorInterface::class),
      ),
      new \Drupal\aculta_portal\Account\RegistrationTerms(
        $this->createMock(\Drupal\agreement\AgreementHandlerInterface::class),
        $this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class),
      ),
      new \Drupal\aculta_portal\Account\RegistrationMailPolicy(
        $this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class),
        $this->createMock(\Drupal\aculta_mailer\Api\AcultaMailerInterface::class),
        $this->createMock(\Drupal\Core\Routing\UrlGeneratorInterface::class),
        $this->createMock(\Drupal\Component\Uuid\UuidInterface::class),
      ),
    );
  }

  /**
   * A form state with a name, a password and a form object.
   *
   * The state is a strict mock: any unexpected call to setErrorByName() or
   * set() fails the test.
   */
  private function formState(string $name, string $pass, FormInterface $form_object): FormStateInterface&\PHPUnit\Framework\MockObject\MockObject {
    $state = $this->createMock(FormStateInterface::class);
    $state->method('getValue')->willReturnMap([['name', $name], ['pass', $pass]]);
    $state->method('isValueEmpty')->willReturnMap([['name', $name === ''], ['pass', $pass === '']]);
    $state->method('getFormObject')->willReturn($form_object);
    return $state;
  }

  private function account(bool $blocked): UserInterface {
    $account = $this->createMock(UserInterface::class);
    $account->method('isBlocked')->willReturn($blocked);
    return $account;
  }

  private function auth(mixed $account): UserAuthenticationInterface {
    $auth = $this->createMock(UserAuthenticationInterface::class);
    $auth->method('lookupAccount')->willReturn($account);
    return $auth;
  }

  private function allowingFlood(): UserFloodControlInterface {
    $flood = $this->createMock(UserFloodControlInterface::class);
    $flood->method('isAllowed')->willReturn(TRUE);
    return $flood;
  }

  public function testBlockedAccountLeavesAuthenticationWithoutAnyError(): void {
    $form_object = $this->createMock(UserLoginForm::class);
    $form_object->expects($this->never())->method('validateAuthentication');
    $state = $this->formState('blocked@example.invalid', 'Wrong-Password-1!', $form_object);
    $state->expects($this->never())->method('setErrorByName');
    $state->expects($this->never())->method('set');

    $form = [];

    $this->callbacks($this->auth($this->account(TRUE)), $this->allowingFlood())
      ->validateLoginAuthentication($form, $state);
  }

  public function testBlockedAccountIsRejectedByIpFloodLikeAnyAccount(): void {
    // With the IP limit reached, Core stops before looking the account up. A
    // blocked account must stop the same way, or the flood response discloses it.
    $flood = $this->createMock(UserFloodControlInterface::class);
    $flood->method('isAllowed')->willReturn(FALSE);
    $form_object = $this->createMock(UserLoginForm::class);
    $form_object->expects($this->never())->method('validateAuthentication');
    $state = $this->formState('blocked@example.invalid', 'Wrong-Password-1!', $form_object);
    $state->expects($this->once())->method('set')->with('flood_control_triggered', 'ip');
    $state->expects($this->never())->method('setErrorByName');

    $form = [];

    $this->callbacks($this->auth($this->account(TRUE)), $flood)
      ->validateLoginAuthentication($form, $state);
  }

  public function testActiveAccountGoesThroughCoreAuthentication(): void {
    $form_object = $this->createMock(UserLoginForm::class);
    $form_object->expects($this->once())->method('validateAuthentication');
    $state = $this->formState('active@example.invalid', 'Wrong-Password-1!', $form_object);
    $state->expects($this->never())->method('set');

    $form = [];

    $this->callbacks($this->auth($this->account(FALSE)), $this->allowingFlood())
      ->validateLoginAuthentication($form, $state);
  }

  public function testUnknownAccountGoesThroughCoreAuthentication(): void {
    $form_object = $this->createMock(UserLoginForm::class);
    $form_object->expects($this->once())->method('validateAuthentication');
    $state = $this->formState('nobody@example.invalid', 'Wrong-Password-1!', $form_object);

    $form = [];

    $this->callbacks($this->auth(FALSE), $this->allowingFlood())
      ->validateLoginAuthentication($form, $state);
  }

  public function testEmptyPasswordSkipsFloodAndLookupLikeCore(): void {
    $flood = $this->createMock(UserFloodControlInterface::class);
    $flood->expects($this->never())->method('isAllowed');
    $auth = $this->createMock(UserAuthenticationInterface::class);
    $auth->expects($this->never())->method('lookupAccount');
    $form_object = $this->createMock(UserLoginForm::class);
    $form_object->expects($this->once())->method('validateAuthentication');

    $form = [];

    $this->callbacks($auth, $flood)->validateLoginAuthentication($form, $this->formState('blocked@example.invalid', '', $form_object));
  }

  public function testUnexpectedFormObjectNeverAuthenticates(): void {
    $form_object = $this->createMock(FormInterface::class);
    $state = $this->formState('active@example.invalid', 'Wrong-Password-1!', $form_object);
    $state->expects($this->never())->method('set');
    $state->expects($this->never())->method('setErrorByName');

    $form = [];

    $this->callbacks($this->auth($this->account(FALSE)), $this->allowingFlood())
      ->validateLoginAuthentication($form, $state);
    $this->addToAssertionCount(1);
  }

}
