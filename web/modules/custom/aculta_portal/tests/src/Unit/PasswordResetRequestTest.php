<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\EventSubscriber\AccountRouteSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The Core one-time reset token is the only exception to account isolation.
 */
#[Group('aculta_portal')]
final class PasswordResetRequestTest extends UnitTestCase {

  /**
   * Monta uma requisição com o token de redefinição e a sessão informada.
   *
   * @param string $token
   *   Token pass-reset-token da requisição.
   * @param array $session
   *   Dados a gravar na sessão.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   Requisição pronta para o teste.
   */
  private function request(string $token = '', array $session = []): Request {
    $request = Request::create('/user/5/edit', 'GET', ['pass-reset-token' => $token]);
    $session_object = new Session(new MockArraySessionStorage());
    $session_object->start();
    foreach ($session as $key => $value) {
      $session_object->set($key, $value);
    }
    $request->setSession($session_object);
    return $request;
  }

  /**
   * Aceita um token válido para a própria conta.
   */
  public function testValidTokenForOwnAccountIsAccepted(): void {
    $request = $this->request('abc123', ['pass_reset_5' => 'abc123']);
    $this->assertTrue(AccountRouteSubscriber::isValidCorePasswordResetRequest($request, 5, 5));
  }

  /**
   * Rejeita um token válido de outra conta.
   */
  public function testAnotherUsersTokenIsRejected(): void {
    // User 7 holds a valid token for account 5 but is not that account.
    $request = $this->request('abc123', ['pass_reset_5' => 'abc123']);
    $this->assertFalse(AccountRouteSubscriber::isValidCorePasswordResetRequest($request, 5, 7));
  }

  /**
   * Rejeita token incorreto ou ausente.
   */
  public function testWrongOrMissingTokenIsRejected(): void {
    $this->assertFalse(AccountRouteSubscriber::isValidCorePasswordResetRequest($this->request('wrong', ['pass_reset_5' => 'abc123']), 5, 5));
    $this->assertFalse(AccountRouteSubscriber::isValidCorePasswordResetRequest($this->request('', ['pass_reset_5' => 'abc123']), 5, 5));
    $this->assertFalse(AccountRouteSubscriber::isValidCorePasswordResetRequest($this->request('abc123'), 5, 5));
  }

  /**
   * Rejeita a conta anônima como alvo.
   */
  public function testAnonymousTargetIsRejected(): void {
    $request = $this->request('abc123', ['pass_reset_0' => 'abc123']);
    $this->assertFalse(AccountRouteSubscriber::isValidCorePasswordResetRequest($request, 0, 0));
  }

  /**
   * Rejeita uma requisição sem sessão.
   */
  public function testRequestWithoutSessionIsRejected(): void {
    $request = Request::create('/user/5/edit', 'GET', ['pass-reset-token' => 'abc123']);
    $this->assertFalse(AccountRouteSubscriber::isValidCorePasswordResetRequest($request, 5, 5));
  }

}
