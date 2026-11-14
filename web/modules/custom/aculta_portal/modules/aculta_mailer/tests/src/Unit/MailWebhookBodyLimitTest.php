<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Controller\MailWebhookController;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Teto do corpo do webhook: corpos grandes são recusados antes de qualquer leitura de assinatura ou evento.
 */
#[Group('aculta_mailer')]
final class MailWebhookBodyLimitTest extends UnitTestCase {

  /**
   * Confirma que um corpo pequeno de evento é aceito.
   */
  public function testSmallEventBodyIsAccepted(): void {
    $body = json_encode(['event' => 'delivered', 'message-id' => '<abc@example.invalid>']);
    $this->assertTrue(MailWebhookController::bodyWithinLimit(Request::create('/x', 'POST', [], [], [], [], $body)));
  }

  /**
   * Confirma que um corpo acima do limite é recusado.
   */
  public function testBodyAboveTheLimitIsRefused(): void {
    $body = str_repeat('a', MailWebhookController::MAX_BODY_BYTES + 1);
    $this->assertFalse(MailWebhookController::bodyWithinLimit(Request::create('/x', 'POST', [], [], [], [], $body)));
  }

  /**
   * Confirma que um comprimento declarado acima do limite é recusado sem ler o corpo.
   */
  public function testDeclaredLengthAboveTheLimitIsRefusedWithoutReadingTheBody(): void {
    $request = Request::create('/x', 'POST', [], [], [], ['CONTENT_LENGTH' => (string) (MailWebhookController::MAX_BODY_BYTES + 1)], '');
    $this->assertFalse(MailWebhookController::bodyWithinLimit($request));
  }

  /**
   * Confirma que um comprimento declarado malformado é recusado.
   */
  public function testMalformedDeclaredLengthIsRefused(): void {
    $request = Request::create('/x', 'POST', [], [], [], ['CONTENT_LENGTH' => '12abc'], '{}');
    $this->assertFalse(MailWebhookController::bodyWithinLimit($request));
  }

}
