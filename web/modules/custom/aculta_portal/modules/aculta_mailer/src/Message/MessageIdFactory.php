<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Message;

use Drupal\Component\Uuid\UuidInterface;

/**
 * Gera Message-ID (RFC 5322) e correlation ID a partir de um UUID.
 *
 * O host do Message-ID é recebido por construtor. Ele vem do mapeamento de endereços por ambiente (environment_bound) e nunca é fixo no
 * código, para não criar endereço de produção em Runtime de teste.
 */
final class MessageIdFactory {

  private const HOST_PATTERN = '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/';

  public function __construct(
    private readonly UuidInterface $uuid,
    private readonly string $messageIdHost,
  ) {
    if (preg_match(self::HOST_PATTERN, $messageIdHost) !== 1) {
      throw new \InvalidArgumentException('Host do Message-ID inválido; informe um nome de domínio.');
    }
  }

  /**
   * Novo correlation ID (UUID v4 minúsculo).
   */
  public function newCorrelationId(): string {
    return $this->uuid->generate();
  }

  /**
   * Message-ID no formato `<correlation@host>`.
   */
  public function messageIdFor(string $correlationId): string {
    return sprintf('<%s@%s>', $correlationId, $this->messageIdHost);
  }

}
