<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Message;

/**
 * Mensagem transacional imutável, criada pelo módulo que origina o evento.
 *
 * O contexto aceita escalares, NULL e listas ou linhas de escalares com no máximo dois níveis (por exemplo, uma lista
 * de linhas `tipo`/`titulo`/`quantidade`). Entidades e objetos são recusados: o template recebe apenas os valores que o
 * módulo de origem escolheu expor (ver docs/ARCHITECTURE.md, seção 4).
 */
final readonly class MailMessage {

  private const TYPE_PATTERN = '/^[a-z][a-z0-9_]{2,63}$/';

  private const LANGCODE_PATTERN = '/^[a-z]{2,3}(-[a-zA-Z0-9]{2,8})?$/';

  private const CORRELATION_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-'
    . '[45][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

  /**
   * Cria a mensagem validada.
   *
   * @param string $type
   *   Tipo do e-mail em snake_case, por exemplo `account_created`.
   * @param string $to
   *   Destinatário. Deve ser um endereço de e-mail válido.
   * @param string $langcode
   *   Idioma BCP 47 simplificado, por exemplo `pt-br`.
   * @param array<string, mixed> $context
   *   Valores autorizados para o template. Chaves em snake_case; ver a classe para o formato permitido.
   * @param string $correlationId
   *   UUID v4 ou v5 minúsculo do `MessageIdFactory`.
   *
   * @throws \InvalidArgumentException
   *   Quando algum campo viola o contrato.
   */
  public function __construct(
    public string $type,
    public string $to,
    public string $langcode,
    public array $context,
    public string $correlationId,
  ) {
    if (preg_match(self::TYPE_PATTERN, $type) !== 1) {
      throw new \InvalidArgumentException('Tipo de e-mail inválido: use snake_case de 3 a 64 caracteres.');
    }
    if (filter_var($to, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new \InvalidArgumentException('Destinatário de e-mail inválido.');
    }
    if (preg_match(self::LANGCODE_PATTERN, $langcode) !== 1) {
      throw new \InvalidArgumentException('Idioma de e-mail inválido.');
    }
    if (preg_match(self::CORRELATION_PATTERN, $correlationId) !== 1) {
      throw new \InvalidArgumentException('Correlation ID deve ser UUID v4 ou v5 minúsculo.');
    }
    foreach ($context as $key => $value) {
      if (!is_string($key) || preg_match(self::TYPE_PATTERN, $key) !== 1) {
        throw new \InvalidArgumentException('Chave de contexto inválida: use snake_case.');
      }
      $this->assertPlainValue($key, $value, 0);
    }
  }

  /**
   * Mensagem a partir do array guardado na fila; valida tudo de novo.
   *
   * @param array<string, mixed> $data
   *   Campos de toArray().
   */
  public static function fromArray(array $data): self {
    return new self(
      (string) ($data['type'] ?? ''),
      (string) ($data['to'] ?? ''),
      (string) ($data['langcode'] ?? ''),
      is_array($data['context'] ?? NULL) ? $data['context'] : [],
      (string) ($data['correlation_id'] ?? ''),
    );
  }

  /**
   * Indicates whether this correlation is deterministic and safe for retries.
   */
  public function hasIdempotentCorrelation(): bool {
    return $this->correlationId[14] === '5';
  }

  /**
   * Array serializável para a fila. Contém o destinatário e o contexto; nunca o corpo renderizado.
   *
   * @return array
   *   Campos da mensagem, em chaves de fila.
   */
  public function toArray(): array {
    return [
      'type' => $this->type,
      'to' => $this->to,
      'langcode' => $this->langcode,
      'context' => $this->context,
      'correlation_id' => $this->correlationId,
    ];
  }

  /**
   * Aceita escalar, NULL, ou lista/linha de escalares até o segundo nível; recusa objetos e níveis maiores.
   */
  private function assertPlainValue(string $key, mixed $value, int $depth): void {
    if ($value === NULL || is_scalar($value)) {
      return;
    }
    if (!is_array($value) || $depth >= 2) {
      throw new \InvalidArgumentException(sprintf('O contexto "%s" aceita somente escalares, NULL ou listas de escalares.', $key));
    }
    foreach ($value as $item) {
      $this->assertPlainValue($key, $item, $depth + 1);
    }
  }

}
