<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer_test\Webhook;

use Drupal\aculta_mailer\Webhook\DeliveryEventType;
use Drupal\aculta_mailer\Webhook\ProviderEvent;
use Drupal\aculta_mailer\Webhook\ProviderWebhookInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provedor de teste: assinatura por cabeçalho fixo e corpo JSON. Só para os testes Kernel.
 */
final class TestWebhookProvider implements ProviderWebhookInterface {

  /**
   * Assinatura aceita pelo provedor de teste.
   */
  public const SIGNATURE = 'segredo-de-teste';

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'teste';
  }

  /**
   * {@inheritdoc}
   */
  public function verify(Request $request): bool {
    return hash_equals(self::SIGNATURE, (string) $request->headers->get('X-Teste-Assinatura'));
  }

  /**
   * {@inheritdoc}
   */
  public function parse(Request $request): array {
    $body = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($body) || !isset($body['events']) || !is_array($body['events'])) {
      throw new \InvalidArgumentException('Corpo sem a lista de eventos.');
    }
    $events = [];
    foreach ($body['events'] as $item) {
      $type = DeliveryEventType::tryFrom((string) ($item['type'] ?? ''));
      if ($type === NULL) {
        throw new \InvalidArgumentException('Tipo de evento desconhecido.');
      }
      $events[] = new ProviderEvent(
        $type,
        isset($item['correlation_id']) ? (string) $item['correlation_id'] : NULL,
        isset($item['provider_message_id']) ? (string) $item['provider_message_id'] : NULL,
        isset($item['code']) ? (string) $item['code'] : NULL,
        (int) ($item['at'] ?? 0),
      );
    }
    return $events;
  }

}
