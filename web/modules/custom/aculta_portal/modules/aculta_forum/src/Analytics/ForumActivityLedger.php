<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Analytics;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;

/**
 * Chave persistente de idempotência da atividade do Fórum.
 *
 * Uma publicação lógica reivindica uma impressão digital estável antes de
 * emitir o evento. A chave única no banco sobrevive a rebuild de cache,
 * reinício de worker, replay e duas requisições concorrentes; nunca guarda
 * autor, texto, e-mail ou IP. Se o ledger não estiver disponível, a emissão é
 * recusada (fail closed) em vez de arriscar contagem dupla.
 */
final class ForumActivityLedger {

  /**
   * Tabela do ledger.
   */
  public const TABLE = 'aculta_forum_analytics_ledger';

  /**
   * Retenção do ledger, acima da retenção padrão das métricas (90 dias).
   */
  public const RETENTION_DAYS = 400;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Reivindica a primeira emissão de uma atividade.
   *
   * @param string $entityType
   *   Tipo da entidade de origem.
   * @param int $entityId
   *   ID da entidade de origem.
   * @param string $eventName
   *   Nome do evento emitido.
   *
   * @return bool
   *   TRUE na primeira reivindicação; FALSE em replay, concorrência ou falha.
   */
  public function claim(string $entityType, int $entityId, string $eventName): bool {
    if ($entityId <= 0) {
      return FALSE;
    }
    $fingerprint = hash('sha256', $entityType . ':' . $entityId . ':' . $eventName);
    try {
      $this->database->insert(self::TABLE)
        ->fields([
          'fingerprint' => $fingerprint,
          'created' => $this->time->getRequestTime(),
        ])
        ->execute();
      return TRUE;
    }
    catch (IntegrityConstraintViolationException) {
      return FALSE;
    }
    catch (\Throwable) {
      return FALSE;
    }
  }

  /**
   * Remove entradas antigas do ledger.
   *
   * @return int
   *   Quantidade de linhas removidas.
   */
  public function purgeExpired(): int {
    $cutoff = $this->time->getRequestTime() - self::RETENTION_DAYS * 86400;
    try {
      return (int) $this->database->delete(self::TABLE)
        ->condition('created', $cutoff, '<')
        ->execute();
    }
    catch (\Throwable) {
      return 0;
    }
  }

}
