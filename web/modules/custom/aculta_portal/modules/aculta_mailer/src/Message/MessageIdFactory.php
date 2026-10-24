<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Message;

use Drupal\Component\Uuid\UuidInterface;

/**
 * Gera Message-ID (RFC 5322) e correlation ID a partir de um UUID.
 *
 * O host do Message-ID vem do mapeamento de endereços por ambiente
 * (environment_bound), e nunca é fixo no código. Assim, o Runtime de teste
 * não cria um endereço de produção.
 */
final class MessageIdFactory {

  private const HOST_PATTERN = '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/';

  /**
   * Fixed UUID namespace for stable idempotency correlations.
   */
  private const IDEMPOTENCY_NAMESPACE = 'e2198ec4-3f89-5f0e-8c7d-5f937abe12c1';

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
   * Returns the same UUID v5 for the same logical idempotency key.
   *
   * The key should be a digest of the minimal identity of one logical delivery,
   * not user data or message content. This supports retrying an accepted batch
   * when a downstream acknowledgement failed.
   */
  public static function correlationIdFor(string $idempotencyKey): string {
    if ($idempotencyKey === '') {
      throw new \InvalidArgumentException('A chave de idempotência não pode estar vazia.');
    }
    $namespace = pack('H*', str_replace('-', '', self::IDEMPOTENCY_NAMESPACE));
    $bytes = substr(sha1($namespace . $idempotencyKey, TRUE), 0, 16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x50);
    $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf(
      '%s-%s-%s-%s-%s',
      substr($hex, 0, 8),
      substr($hex, 8, 4),
      substr($hex, 12, 4),
      substr($hex, 16, 4),
      substr($hex, 20, 12),
    );
  }

  /**
   * Message-ID no formato `<correlation@host>`.
   */
  public function messageIdFor(string $correlationId): string {
    return sprintf('<%s@%s>', $correlationId, $this->messageIdHost);
  }

}
