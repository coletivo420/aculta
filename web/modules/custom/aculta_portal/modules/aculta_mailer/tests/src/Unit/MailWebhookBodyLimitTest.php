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

  public function testSmallEventBodyIsAccepted(): void {
    $body = json_encode(['event' => 'delivered', 'message-id' => '<abc@example.invalid>']);
    $this->assertTrue(MailWebhookController::bodyWithinLimit(Request::create('/x', 'POST', [], [], [], [], $body)));
  }

  public function testBodyAboveTheLimitIsRefused(): void {
    $body = str_repeat('a', MailWebhookController::MAX_BODY_BYTES + 1);
    $this->assertFalse(MailWebhookController::bodyWithinLimit(Request::create('/x', 'POST', [], [], [], [], $body)));
  }

  public function testDeclaredLengthAboveTheLimitIsRefusedWithoutReadingTheBody(): void {
    $request = Request::create('/x', 'POST', [], [], [], ['CONTENT_LENGTH' => (string) (MailWebhookController::MAX_BODY_BYTES + 1)], '');
    $this->assertFalse(MailWebhookController::bodyWithinLimit($request));
  }

  public function testMalformedDeclaredLengthIsRefused(): void {
    $request = Request::create('/x', 'POST', [], [], [], ['CONTENT_LENGTH' => '12abc'], '{}');
    $this->assertFalse(MailWebhookController::bodyWithinLimit($request));
  }

}
