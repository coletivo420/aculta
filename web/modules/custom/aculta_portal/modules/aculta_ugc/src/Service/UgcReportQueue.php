<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_user\Moderation\ReportQueue;

/**
 * Fila unificada de denúncias e migração controlada (D-04).
 */
final class UgcReportQueue {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Retorna os itens pendentes da fila agrupados por entidade.
   *
   * @return array<int, array{target_type: string, target_id: int, total: int, ultima: int}>
   */
  public function getPendingItems(?string $purpose = NULL, int $limit = 50): array {
    $query = $this->entityTypeManager->getStorage('aculta_ugc_report')->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 'pendente')
      ->sort('created', 'DESC')
      ->range(0, $limit * 5);

    if ($purpose !== NULL) {
      $query->condition('purpose', $purpose);
    }

    $ids = $query->execute();
    if (empty($ids)) {
      return [];
    }

    /** @var \Drupal\aculta_ugc\Entity\UgcReport[] $reports */
    $reports = $this->entityTypeManager->getStorage('aculta_ugc_report')->loadMultiple($ids);
    $grupos = [];

    foreach ($reports as $report) {
      $key = $report->get('target_type')->value . ':' . $report->get('target_id')->value;
      if (!isset($grupos[$key])) {
        $grupos[$key] = [
          'target_type' => (string) $report->get('target_type')->value,
          'target_id' => (int) $report->get('target_id')->value,
          'total' => 0,
          'ultima' => (int) $report->get('created')->value,
        ];
      }
      $grupos[$key]['total']++;
    }

    return array_slice(array_values($grupos), 0, $limit);
  }

  /**
   * Migração controlada das sinalizações legadas do ReportQueue para aculta_ugc_report (D-04).
   *
   * @return array{flags_lidas: int, novas_denuncias: int, ignoradas_duplicadas: int}
   */
  public function migrateFromReportQueue(): array {
    if (!$this->entityTypeManager->hasDefinition('flagging')) {
      return ['flags_lidas' => 0, 'novas_denuncias' => 0, 'ignoradas_duplicadas' => 0];
    }

    $flagStorage = $this->entityTypeManager->getStorage('flagging');
    $reportStorage = $this->entityTypeManager->getStorage('aculta_ugc_report');

    $flagIds = $flagStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('flag_id', ReportQueue::FLAGS, 'IN')
      ->execute();

    $flagsCount = count($flagIds);
    if ($flagsCount === 0) {
      return ['flags_lidas' => 0, 'novas_denuncias' => 0, 'ignoradas_duplicadas' => 0];
    }

    $flaggings = $flagStorage->loadMultiple($flagIds);
    $createdCount = 0;
    $ignoredCount = 0;

    foreach ($flaggings as $flagging) {
      $targetType = $flagging->getFlaggableType();
      $targetId = (int) $flagging->getFlaggableId();
      $uid = (int) $flagging->getOwnerId();

      // Checa se já existe denúncia deste par na tabela unificada.
      $existing = $reportStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('target_type', $targetType)
        ->condition('target_id', $targetId)
        ->condition('uid', $uid)
        ->range(0, 1)
        ->execute();

      if (!empty($existing)) {
        $ignoredCount++;
        continue;
      }

      $report = $reportStorage->create([
        'target_type' => $targetType,
        'target_id' => $targetId,
        'uid' => $uid,
        'motivo' => 'sinalizacao_legada_forum',
        'purpose' => 'forum',
        'status' => 'pendente',
        'created' => (int) $flagging->getCreatedTime(),
      ]);
      $report->save();
      $createdCount++;
    }

    return [
      'flags_lidas' => $flagsCount,
      'novas_denuncias' => $createdCount,
      'ignoradas_duplicadas' => $ignoredCount,
    ];
  }

}