<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Service;

use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;

/**
 * Valida o pedido de exportação em lote: ids, formato e nomes dos arquivos.
 *
 * Classe pura, sem entidades nem sistema de arquivos. O controlador carrega os códigos depois da validação.
 */
final class QrCodeBatchPlanner {

  /**
   * Limite de códigos por lote, para não gerar arquivos grandes demais numa só resposta. */
  public const MAX_IDS = 50;

  /**
   * Formatos aceitos no lote. */
  public const FORMATS = ['svg', 'png', 'webp', 'pdf'];

  /**
   * Converte "1,2,3" em lista de ids inteiros, sem repetição e na ordem informada.
   *
   * @return int[]
   *   Ids positivos, únicos.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   *   Quando a lista está vazia, tem valor que não é inteiro positivo ou passa do limite.
   */
  public function parseIds(string $ids): array {
    $parts = array_values(array_filter(array_map('trim', explode(',', $ids)), static fn (string $p): bool => $p !== ''));
    if ($parts === []) {
      throw new InvalidQrCodeException('Informe ao menos um código para o lote.');
    }
    $unique = [];
    foreach ($parts as $part) {
      if (!ctype_digit($part) || (int) $part < 1) {
        throw new InvalidQrCodeException('Os ids do lote devem ser inteiros positivos.');
      }
      $unique[(int) $part] = TRUE;
    }
    if (count($unique) > self::MAX_IDS) {
      throw new InvalidQrCodeException(sprintf('O lote aceita no máximo %d códigos.', self::MAX_IDS));
    }
    return array_keys($unique);
  }

  /**
   * Valida o formato pedido.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   *   Quando o formato não está em FORMATS.
   */
  public function assertFormat(string $format): string {
    if (!in_array($format, self::FORMATS, TRUE)) {
      throw new InvalidQrCodeException('Formato de lote não suportado.');
    }
    return $format;
  }

  /**
   * Nome do arquivo de um código dentro do ZIP: qr-<codigo>.<formato>.
   *
   * O código é o identificador público do QR Code, validado pela entidade. Aqui só se confere o padrão, para que
   * nenhum caractere de caminho entre no nome do arquivo.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   *   Quando o código tem caracteres fora de [0-9a-z].
   */
  public function entryName(string $code, string $format): string {
    if (preg_match('/^[0-9a-z]{1,64}$/', $code) !== 1) {
      throw new InvalidQrCodeException('Código inválido para nome de arquivo.');
    }
    return 'qr-' . $code . '.' . $format;
  }

}
