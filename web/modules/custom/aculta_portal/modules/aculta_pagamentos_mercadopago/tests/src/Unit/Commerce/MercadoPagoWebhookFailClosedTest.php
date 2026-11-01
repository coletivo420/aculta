<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\Commerce;

use Drupal\aculta_pagamentos_mercadopago\Commerce\MercadoPago\WebhookGuard;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests fail-closed handling for Mercado Pago notifications.
 *
 * The suite uses a test secret and makes no external calls.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class MercadoPagoWebhookFailClosedTest extends UnitTestCase {

  private const FAKE_SECRET = 'test-only-secret-not-real';

  /**
   * Helper for the test cases.
   */
  private function post(array $headers = [], string $body = '{"data":{"id":"1"},"type":"payment"}'): Request {
    $request = Request::create('/integracoes/pagamentos/mercado_pago/notificacao?data.id=1&type=payment', 'POST', [], [], [], [], $body);
    foreach ($headers as $name => $value) {
      $request->headers->set($name, $value);
    }
    return $request;
  }

  /**
   * Helper for the test cases.
   */
  private function guard(): WebhookGuard {
    return new WebhookGuard();
  }

  /**
   * Helper for the test cases.
   */
  public function testDisabledGatewayIsUnavailable(): void {
    $response = $this->guard()->validateAndNormalize($this->post(), FALSE, self::FAKE_SECRET);
    $this->assertSame(503, $response?->getStatusCode());
  }

  /**
   * Helper for the test cases.
   */
  public function testMissingSecretIsUnavailable(): void {
    $this->assertSame(503, $this->guard()->validateAndNormalize($this->post(), TRUE, NULL)?->getStatusCode());
    $this->assertSame(503, $this->guard()->validateAndNormalize($this->post(), TRUE, '   ')?->getStatusCode());
  }

  /**
   * Helper for the test cases.
   */
  public function testGetIsNotAllowed(): void {
    $request = Request::create('/integracoes/pagamentos/mercado_pago/notificacao', 'GET');
    $response = $this->guard()->validateAndNormalize($request, TRUE, self::FAKE_SECRET);
    $this->assertSame(405, $response?->getStatusCode());
    $this->assertSame('POST', $response?->headers->get('Allow'));
  }

  /**
   * Helper for the test cases.
   */
  public function testMissingSignatureIsUnauthorized(): void {
    $response = $this->guard()->validateAndNormalize($this->post(['x-request-id' => 'req-1']), TRUE, self::FAKE_SECRET);
    $this->assertSame(401, $response?->getStatusCode());
  }

  /**
   * Helper for the test cases.
   */
  public function testMissingRequestIdIsUnauthorized(): void {
    $response = $this->guard()->validateAndNormalize($this->post(['x-signature' => 'ts=1,v1=00']), TRUE, self::FAKE_SECRET);
    $this->assertSame(401, $response?->getStatusCode());
  }

  /**
   * Builds a Mercado Pago signature header for the test request.
   *
   * The timestamp is current, so the outcome depends only on the HMAC.
   */
  private function signedHeaders(string $secret, string $hash_secret_override = ''): array {
    $ts = (string) (int) (microtime(TRUE) * 1000);
    $hash = hash_hmac('sha256', 'id:1;request-id:req-1;ts:' . $ts . ';', $hash_secret_override !== '' ? $hash_secret_override : $secret);
    return ['x-signature' => 'ts=' . $ts . ',v1=' . $hash, 'x-request-id' => 'req-1'];
  }

  /**
   * Helper for the test cases.
   */
  public function testCorrectlySignedCurrentNotificationPasses(): void {
    $response = $this->guard()->validateAndNormalize($this->post($this->signedHeaders(self::FAKE_SECRET)), TRUE, self::FAKE_SECRET);
    $this->assertNull($response, 'A valid signature must not be rejected by the guard.');
  }

  /**
   * Helper for the test cases.
   */
  public function testForgedSignatureWithCurrentTimestampIsRejected(): void {
    // Same timestamp and request id, hash computed with a different secret.
    $headers = $this->signedHeaders(self::FAKE_SECRET, 'another-test-secret');
    $response = $this->guard()->validateAndNormalize($this->post($headers), TRUE, self::FAKE_SECRET);
    $this->assertSame(401, $response?->getStatusCode());
  }

  /**
   * Helper for the test cases.
   */
  public function testUnsignedLegacyTopicNotificationIsRejected(): void {
    // Never forward unsigned legacy IPN with topic/id: no signature, no entry.
    $request = Request::create('/integracoes/pagamentos/mercado_pago/notificacao?topic=payment&id=1', 'POST');
    $response = $this->guard()->validateAndNormalize($request, TRUE, self::FAKE_SECRET);
    $this->assertSame(401, $response?->getStatusCode());
  }

  /**
   * Helper for the test cases.
   */
  public function testSignedHeadersWithLegacyTopicAreBadRequest(): void {
    $request = Request::create('/integracoes/pagamentos/mercado_pago/notificacao?topic=payment&id=1', 'POST');
    $request->headers->set('x-signature', 'ts=1700000000,v1=' . str_repeat('0', 64));
    $request->headers->set('x-request-id', 'req-1');
    $response = $this->guard()->validateAndNormalize($request, TRUE, self::FAKE_SECRET);
    $this->assertSame(400, $response?->getStatusCode());
  }

}
