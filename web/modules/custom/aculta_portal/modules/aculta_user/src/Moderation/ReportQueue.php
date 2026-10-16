<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Moderation;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Fila de denúncias do Fórum (ACULTA Fórum, F5.1).
 *
 * Não há contador persistido: cada item é derivado dos registros do Flag (flagging), um por conta e por conteúdo.
 * Motivo: o Flag já garante a deduplicação e é a fonte de verdade das denúncias. O limite de registros lidos por
 * consulta evita varredura sem fim; acima dele a fila mostra o que coube e avisa.
 */
final class ReportQueue {

  /** Sinalizações do Fórum (tópico e comentário). */
  public const FLAGS = ['sinalizar_topico', 'sinalizar_comentario'];

  /** Registros de Flag lidos por consulta. */
  public const SCAN_LIMIT = 500;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Itens denunciados, do mais recente para o mais antigo, agrupados por conteúdo.
   *
   * @return array{items: array<int, array{entity_type: string, entity_id: int, total: int, ultima: int}>, truncado: bool}
   */
  public function items(): array {
    $storage = $this->entityTypeManager->getStorage('flagging');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('flag_id', self::FLAGS, 'IN')
      ->sort('created', 'DESC')
      ->range(0, self::SCAN_LIMIT + 1)
      ->execute();
    $truncado = count($ids) > self::SCAN_LIMIT;
    $ids = array_slice($ids, 0, self::SCAN_LIMIT);

    $grupos = [];
    foreach ($storage->loadMultiple($ids) as $flagging) {
      $chave = $flagging->getFlaggableType() . ':' . $flagging->getFlaggableId();
      $grupos[$chave] ??= [
        'entity_type' => $flagging->getFlaggableType(),
        'entity_id' => (int) $flagging->getFlaggableId(),
        'total' => 0,
        'ultima' => (int) $flagging->getCreatedTime(),
      ];
      $grupos[$chave]['total']++;
    }
    return ['items' => array_values($grupos), 'truncado' => $truncado];
  }

}
