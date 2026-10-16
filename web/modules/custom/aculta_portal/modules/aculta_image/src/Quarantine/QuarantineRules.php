<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Quarantine;

/**
 * Regras puras da quarentena (aculta_image, fase M7): prazo de retenção e vencimento.
 */
final class QuarantineRules {

  /** Dias de retenção antes de a exclusão ficar disponível. */
  public const RETENCAO_DIAS = 30;

  /** Timestamp em que a entrada vence, a partir do envio. */
  public static function vencimento(int $enviadoEm): int {
    return $enviadoEm + self::RETENCAO_DIAS * 86400;
  }

  /** A entrada já pode ser excluída? */
  public static function vencida(int $vencimento, int $agora): bool {
    return $agora >= $vencimento;
  }

}
