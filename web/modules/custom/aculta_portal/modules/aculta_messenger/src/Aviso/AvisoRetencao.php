<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\aculta_messenger\Entity\Aviso;

/**
 * Retenção dos avisos por pessoa do Fórum (ACULTA Messenger): seis meses desde a criação.
 *
 * Só vale para os avisos da entidade `aculta_aviso` (respostas e atualizações de tópicos). Os avisos persistentes
 * (`aculta_notice`) e os alertas do site (`site_alert`), enviados pelo sistema administrativo, não têm regra temporal
 * e não são tocados por esta regra. Os avisos do LMS também ficam fora: não são `aculta_aviso` e não têm tipo de Fórum.
 * Conversas têm a própria política (ConversationPolicy).
 */
final class AvisoRetencao {

  /** Meses desde a criação até a exclusão automática. */
  public const MESES = 6;

  /** Tipos de aviso sujeitos à retenção: somente os avisos do Fórum. */
  public const TIPOS = [Aviso::TIPO_RESPOSTA, Aviso::TIPO_ATUALIZACAO];

  /** Timestamp abaixo do qual (inclusive) um aviso criado está vencido. */
  public static function limite(int $agora): int {
    return (new \DateTimeImmutable('@' . $agora))
      ->modify('-' . self::MESES . ' months')
      ->getTimestamp();
  }

  /** Um aviso criado em $criado está vencido em $agora? */
  public static function vencido(int $criado, int $agora): bool {
    return $criado <= self::limite($agora);
  }

}
