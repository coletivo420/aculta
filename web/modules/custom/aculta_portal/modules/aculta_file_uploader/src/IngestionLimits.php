<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

/**
 * Limites de tamanho da ingestão de arquivos recebidos de fora, em megabytes.
 *
 * Cobre o relatório DMARC (anexo de e-mail), a mensagem IMAP e o pacote H5P. Os
 * padrões e os tetos ficam no código: um valor salvo acima do teto, ou inválido,
 * volta ao padrão. Não é o limite de upload por usuário, que é `UploadPolicy`.
 *
 * Sem dependência do Drupal, para testar sem bootstrap.
 */
final class IngestionLimits {

  /**
   * Bytes em um megabyte (MiB).
   */
  public const MIB = 1048576;

  /**
   * Teto do XML descompactado de um relatório DMARC.
   */
  public const DMARC_MAX_REPORT_MB = 'dmarc_max_report_mb';

  /**
   * Teto de download de uma mensagem IMAP.
   */
  public const IMAP_MAX_MESSAGE_MB = 'imap_max_message_mb';

  /**
   * Teto descompactado de uma entrada de pacote H5P.
   */
  public const H5P_MAX_ENTRY_MB = 'h5p_max_entry_mb';

  /**
   * Teto descompactado total de um pacote H5P.
   */
  public const H5P_MAX_TOTAL_MB = 'h5p_max_total_mb';

  /**
   * Padrões em megabytes.
   */
  public const DEFAULTS = [
    self::DMARC_MAX_REPORT_MB => 8,
    self::IMAP_MAX_MESSAGE_MB => 25,
    self::H5P_MAX_ENTRY_MB => 32,
    self::H5P_MAX_TOTAL_MB => 256,
  ];

  /**
   * Tetos fixos em megabytes: o painel não passa deles.
   */
  public const CEILINGS = [
    self::DMARC_MAX_REPORT_MB => 32,
    self::IMAP_MAX_MESSAGE_MB => 50,
    self::H5P_MAX_ENTRY_MB => 128,
    self::H5P_MAX_TOTAL_MB => 512,
  ];

  /**
   * Valores em megabytes, já validados.
   *
   * @param array<string, int> $megabytes
   */
  private function __construct(private readonly array $megabytes) {}

  /**
   * Monta os limites a partir do que está salvo, aplicando padrão onde faltar ou errar.
   *
   * @param array<string, mixed> $stored
   *   Valores salvos. Ausência ou valor inválido usa o padrão.
   */
  public static function fromArray(array $stored): self {
    $values = [];
    foreach (self::DEFAULTS as $key => $default) {
      $value = $stored[$key] ?? NULL;
      $valid = is_int($value) || (is_string($value) && ctype_digit($value));
      $values[$key] = $valid && (int) $value >= 1 && (int) $value <= self::CEILINGS[$key]
        ? (int) $value
        : $default;
    }
    // Uma entrada não pode ser maior que o total do pacote; se for, valem os dois padrões H5P.
    if ($values[self::H5P_MAX_ENTRY_MB] > $values[self::H5P_MAX_TOTAL_MB]) {
      $values[self::H5P_MAX_ENTRY_MB] = self::DEFAULTS[self::H5P_MAX_ENTRY_MB];
      $values[self::H5P_MAX_TOTAL_MB] = self::DEFAULTS[self::H5P_MAX_TOTAL_MB];
    }
    return new self($values);
  }

  /**
   * Limites com todos os padrões.
   */
  public static function defaults(): self {
    return new self(self::DEFAULTS);
  }

  /**
   * Valor em megabytes de uma chave.
   */
  public function megabytes(string $key): int {
    return $this->megabytes[$key];
  }

  /**
   * Valor em bytes de uma chave.
   */
  public function bytes(string $key): int {
    return $this->megabytes[$key] * self::MIB;
  }

}
