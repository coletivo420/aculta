<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Webhook;

use Drupal\aculta_mailer\Transport\SecretProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Webhook transacional do Brevo.
 *
 * O Brevo não assina os eventos. A autenticação é o token de portador configurado no próprio webhook (`auth` com
 * `type: bearer`), guardado em BREVO_WEBHOOK_TOKEN. Sem token configurado, todo pedido é recusado.
 *
 * O correlation ID vem na tag `aculta-mail:<id>`, que o envio grava com o cabeçalho `X-Tag` (ver LegacyMessageConverter).
 */
final class BrevoWebhook implements ProviderWebhookInterface {

  /**
   * Prefixo da tag que leva o correlation ID até o evento.
   */
  public const TAG_PREFIX = 'aculta-mail:';

  /**
   * Evento do Brevo => tipo normalizado e código curto. Eventos fora da lista são ignorados.
   */
  private const EVENTS = [
    'delivered' => [DeliveryEventType::Delivered, NULL],
    'deferred' => [DeliveryEventType::Deferred, 'deferred'],
    'soft_bounce' => [DeliveryEventType::Deferred, 'soft'],
    'hard_bounce' => [DeliveryEventType::Bounced, 'hard'],
    'invalid_email' => [DeliveryEventType::Bounced, 'invalid'],
    'blocked' => [DeliveryEventType::Bounced, 'blocked'],
    'spam' => [DeliveryEventType::Complained, 'spam'],
  ];

  public function __construct(
    private readonly ?SecretProviderInterface $secrets = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'brevo';
  }

  /**
   * {@inheritdoc}
   */
  public function verify(Request $request): bool {
    $token = (string) ($this->secrets?->value('BREVO_WEBHOOK_TOKEN') ?? '');
    if ($token === '') {
      return FALSE;
    }
    $header = (string) $request->headers->get('Authorization', '');
    return hash_equals('Bearer ' . $token, $header);
  }

  /**
   * {@inheritdoc}
   */
  public function parse(Request $request): array {
    $data = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($data) || !isset($data['event']) || !is_string($data['event'])) {
      throw new \InvalidArgumentException('Evento do Brevo sem o campo event.');
    }
    if (!isset(self::EVENTS[$data['event']])) {
      return [];
    }
    [$type, $code] = self::EVENTS[$data['event']];
    $providerId = isset($data['message-id']) && is_string($data['message-id']) ? trim($data['message-id'], '<> ') : NULL;
    $occurredAt = isset($data['ts']) && is_numeric($data['ts']) ? (int) $data['ts'] : time();
    $event = new ProviderEvent(
      type: $type,
      correlationId: $this->correlationId($data['tags'] ?? []),
      providerMessageId: $providerId,
      code: $code,
      occurredAt: $occurredAt,
    );
    return [$event];
  }

  /**
   * Correlation ID da tag `aculta-mail:<id>`, ou NULL quando o envio não a informou.
   */
  private function correlationId(mixed $tags): ?string {
    foreach ((array) $tags as $tag) {
      if (is_string($tag) && str_starts_with($tag, self::TAG_PREFIX)) {
        return substr($tag, strlen(self::TAG_PREFIX));
      }
    }
    return NULL;
  }

}
