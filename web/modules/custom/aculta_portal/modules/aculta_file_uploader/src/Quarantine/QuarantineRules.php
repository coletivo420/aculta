<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader\Quarantine;

/**
 * Regras de retenção da quarentena de arquivos.
 *
 * Define o prazo e o instante de vencimento. Movida do aculta_image (M7) para
 * o aculta_file_uploader, que passa a ser dono da limpeza de arquivos.
 */
final class QuarantineRules {

  /**
   * Dias de retenção antes de a exclusão ficar disponível.
   */
  public const RETENCAO_DIAS = 30;

  /**
   * Timestamp em que a entrada vence, a partir do envio.
   */
  public static function vencimento(int $enviadoEm): int {
    return $enviadoEm + self::RETENCAO_DIAS * 86400;
  }

  /**
   * Indica se a entrada já pode ser excluída.
   */
  public static function vencida(int $vencimento, int $agora): bool {
    return $agora >= $vencimento;
  }

}
