<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Webhook;

/**
 * Evento de etiqueta recebido pelo webhook do Melhor Envio, já validado.
 *
 * Campos conforme a documentação (`docs.melhorenvio.com.br/docs/webhooks`). Não guarda o corpo bruto nem dados
 * pessoais do destinatário.
 */
final class MelhorEnvioWebhookEvent {

  /**
   * Eventos de etiqueta documentados.
   */
  public const EVENTS = [
    'order.created', 'order.pending', 'order.released', 'order.generated', 'order.received', 'order.posted',
    'order.delivered', 'order.cancelled', 'order.undelivered', 'order.paused', 'order.suspended',
  ];

  /**
   * Cria o evento com os campos já validados.
   */
  public function __construct(
    public readonly string $event,
    public readonly string $labelId,
    public readonly string $status,
  ) {}

  /**
   * Interpreta o corpo JSON do webhook.
   *
   * @throws \InvalidArgumentException
   *   Quando o JSON não tem a forma documentada, o evento não é conhecido ou o id da etiqueta não é um UUID.
   */
  public static function fromJson(string $body): self {
    $data = json_decode($body, TRUE);
    if (!is_array($data) || !isset($data['event'], $data['data']) || !is_array($data['data'])) {
      throw new \InvalidArgumentException('Corpo do webhook fora do formato documentado.');
    }
    $event = (string) $data['event'];
    if (!in_array($event, self::EVENTS, TRUE)) {
      throw new \InvalidArgumentException('Evento de webhook desconhecido.');
    }
    $labelId = (string) ($data['data']['id'] ?? '');
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $labelId) !== 1) {
      throw new \InvalidArgumentException('Id de etiqueta inválido.');
    }
    $status = (string) ($data['data']['status'] ?? '');
    if ($status === '') {
      throw new \InvalidArgumentException('Status ausente.');
    }
    return new self($event, $labelId, $status);
  }

}
