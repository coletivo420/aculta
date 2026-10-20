<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Transport\SecretProviderInterface;
use Drupal\aculta_mailer\Webhook\BrevoWebhook;
use Drupal\aculta_mailer\Webhook\DeliveryEventType;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Webhook do Brevo: autenticação por token de portador e mapeamento dos eventos.
 */
#[Group('aculta_mailer')]
final class BrevoWebhookTest extends UnitTestCase {

  /**
   * Webhook com o token informado (NULL: sem segredo configurado).
   */
  private function webhook(?string $token): BrevoWebhook {
    $secrets = new class($token) implements SecretProviderInterface {

      public function __construct(private readonly ?string $token) {}

      /**
       * {@inheritdoc}
       */
      public function value(string $name): ?string {
        return $name === 'BREVO_WEBHOOK_TOKEN' ? $this->token : NULL;
      }

    };
    return new BrevoWebhook($secrets);
  }

  /**
   * Requisição JSON com o cabeçalho de autorização informado.
   */
  private function request(array $body, ?string $authorization): Request {
    $request = Request::create('/aculta-mailer/webhook/brevo', 'POST', [], [], [], [], json_encode($body));
    if ($authorization !== NULL) {
      $request->headers->set('Authorization', $authorization);
    }
    return $request;
  }

  /**
   * Sem segredo configurado, toda requisição é recusada.
   */
  public function testRejectsWhenNoSecretIsConfigured(): void {
    $webhook = $this->webhook(NULL);
    $this->assertFalse($webhook->verify($this->request(['event' => 'delivered'], 'Bearer qualquer')));
    $this->assertFalse($this->webhook('')->verify($this->request(['event' => 'delivered'], 'Bearer ')));
  }

  /**
   * Só o token de portador exato autentica.
   */
  public function testVerifiesBearerToken(): void {
    $webhook = $this->webhook('segredo-de-teste');
    $body = ['event' => 'delivered'];
    $this->assertTrue($webhook->verify($this->request($body, 'Bearer segredo-de-teste')));
    $this->assertFalse($webhook->verify($this->request($body, 'Bearer outro')));
    $this->assertFalse($webhook->verify($this->request($body, 'segredo-de-teste')));
    $this->assertFalse($webhook->verify($this->request($body, NULL)));
  }

  /**
   * Hard bounce vira devolução, com o correlation ID da tag e o ID do provedor sem colchetes.
   */
  public function testParsesHardBounceWithCorrelationTag(): void {
    $events = $this->webhook('x')->parse($this->request([
      'event' => 'hard_bounce',
      'email' => 'exemplo@example.invalid',
      'message-id' => '<201798300811.5787683@relay.example.invalid>',
      'tags' => ['outra-tag', 'aculta-mail:0f0a6c2e-1111-4222-8333-944444444444'],
      'ts' => 1604933619,
    ], NULL));
    $this->assertCount(1, $events);
    $this->assertSame(DeliveryEventType::Bounced, $events[0]->type);
    $this->assertSame('0f0a6c2e-1111-4222-8333-944444444444', $events[0]->correlationId);
    $this->assertSame('201798300811.5787683@relay.example.invalid', $events[0]->providerMessageId);
    $this->assertSame('hard', $events[0]->code);
    $this->assertSame(1604933619, $events[0]->occurredAt);
  }

  /**
   * Mapeamento dos eventos aceitos; os demais são ignorados sem erro.
   */
  public function testMapsKnownEventsAndIgnoresOthers(): void {
    $webhook = $this->webhook('x');
    $expected = [
      'delivered' => [DeliveryEventType::Delivered, NULL],
      'deferred' => [DeliveryEventType::Deferred, 'deferred'],
      'soft_bounce' => [DeliveryEventType::Deferred, 'soft'],
      'invalid_email' => [DeliveryEventType::Bounced, 'invalid'],
      'blocked' => [DeliveryEventType::Bounced, 'blocked'],
      'spam' => [DeliveryEventType::Complained, 'spam'],
    ];
    foreach ($expected as $event => [$type, $code]) {
      $parsed = $webhook->parse($this->request(['event' => $event], NULL));
      $this->assertCount(1, $parsed, $event);
      $this->assertSame($type, $parsed[0]->type, $event);
      $this->assertSame($code, $parsed[0]->code, $event);
      $this->assertNull($parsed[0]->correlationId, $event);
    }
    $this->assertSame([], $webhook->parse($this->request(['event' => 'opened'], NULL)));
    $this->assertSame([], $webhook->parse($this->request(['event' => 'click'], NULL)));
  }

  /**
   * Corpo sem evento não é aceito.
   */
  public function testRejectsBodyWithoutEvent(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->webhook('x')->parse(Request::create('/aculta-mailer/webhook/brevo', 'POST', [], [], [], [], '{"email":"x"}'));
  }

}
