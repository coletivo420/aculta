<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_ugc\ModerationScope;
use Drupal\aculta_user\Moderation\ReportQueue;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Fila unificada de moderação e migração controlada (ARQ-06C, ARQ-06D, D-04).
 */
final class UgcReportQueue {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?AccountInterface $currentUser = NULL,
  ) {}

  /**
   * Retorna itens agrupados da fila de moderação com controle de acesso e paginação.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   Conta do moderador. Se nula, usa o usuário logado atual.
   * @param string|null $purpose
   *   Purpose solicitado. Se nulo, abrange todos os purposes que a conta modera.
   * @param string $status
   *   Status das denúncias ('pendente', 'em_analise' ou 'decidido').
   * @param int $page
   *   Número da página (base 0).
   * @param int $limit
   *   Itens por página.
   *
   * @return array{items: array<int, array{target_type: string, target_id: int, purpose: string, total: int, ultima: int, motivos: string[]}>, total_items: int, total_pages: int, page: int, limit: int}
   *   Estrutura paginada com os itens agrupados da fila.
   */
  public function getQueueItems(
    ?AccountInterface $account = NULL,
    ?string $purpose = NULL,
    string $status = 'pendente',
    int $page = 0,
    int $limit = 50,
  ): array {
    $moderator = $account ?? $this->currentUser;
    if ($moderator === NULL || $moderator->isAnonymous()) {
      throw new AccessDeniedHttpException('Acesso negado: usuário anônimo não tem permissão para moderar.');
    }

    // Valida escopo do moderador.
    if ($purpose !== NULL) {
      if (!ModerationScope::allows($moderator, $purpose)) {
        throw new AccessDeniedHttpException('Acesso negado: sem permissão para moderar o purpose informado.');
      }
      $allowedPurposes = [$purpose];
    }
    else {
      $allowedPurposes = ModerationScope::purposesFor($moderator);
      if (empty($allowedPurposes)) {
        throw new AccessDeniedHttpException('Acesso negado: sem permissão de moderação em nenhum purpose.');
      }
    }

    $storage = $this->entityTypeManager->getStorage('aculta_ugc_report');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', $status)
      ->condition('purpose', $allowedPurposes, 'IN')
      ->sort('created', 'DESC');

    $reportIds = $query->execute();
    if (empty($reportIds)) {
      return [
        'items' => [],
        'total_items' => 0,
        'total_pages' => 0,
        'page' => $page,
        'limit' => $limit,
      ];
    }

    /** @var \Drupal\aculta_ugc\Entity\UgcReport[] $reports */
    $reports = $storage->loadMultiple($reportIds);
    $grupos = [];
    $validTargets = [];

    foreach ($reports as $report) {
      $targetType = (string) $report->get('target_type')->value;
      $targetId = (int) $report->get('target_id')->value;
      $reportPurpose = (string) $report->get('purpose')->value;
      $key = $targetType . ':' . $targetId;

      // Verifica existência da entidade denunciada para excluir alvos inexistentes.
      if (!isset($validTargets[$key])) {
        if (!$this->entityTypeManager->hasDefinition($targetType)) {
          $validTargets[$key] = FALSE;
        }
        else {
          $target = $this->entityTypeManager->getStorage($targetType)->load($targetId);
          $validTargets[$key] = ($target !== NULL);
        }
      }

      if (!$validTargets[$key]) {
        // Alvo excluído fisicamente: não expõe na fila de moderação ativa.
        continue;
      }

      if (!isset($grupos[$key])) {
        $grupos[$key] = [
          'target_type' => $targetType,
          'target_id' => $targetId,
          'purpose' => $reportPurpose,
          'total' => 0,
          'ultima' => (int) $report->get('created')->value,
          'motivos' => [],
        ];
      }

      $grupos[$key]['total']++;
      $motivo = (string) $report->get('motivo')->value;
      if (!in_array($motivo, $grupos[$key]['motivos'], TRUE)) {
        $grupos[$key]['motivos'][] = $motivo;
      }
    }

    $allItems = array_values($grupos);
    $totalItems = count($allItems);
    $totalPages = $limit > 0 ? (int) ceil($totalItems / $limit) : 1;
    $offset = max(0, $page * $limit);
    $pagedItems = array_slice($allItems, $offset, $limit);

    return [
      'items' => $pagedItems,
      'total_items' => $totalItems,
      'total_pages' => $totalPages,
      'page' => $page,
      'limit' => $limit,
    ];
  }

  /**
   * Retorna os itens pendentes da fila agrupados por entidade (retrocompatibilidade).
   *
   * @return array<int, array{target_type: string, target_id: int, total: int, ultima: int}>
   *   Lista dos itens pendentes agrupados.
   */
  public function getPendingItems(?string $purpose = NULL, int $limit = 50): array {
    $storage = $this->entityTypeManager->getStorage('aculta_ugc_report');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 'pendente')
      ->sort('created', 'DESC');

    if ($purpose !== NULL) {
      $query->condition('purpose', $purpose);
    }

    $ids = $query->execute();
    if (empty($ids)) {
      return [];
    }

    /** @var \Drupal\aculta_ugc\Entity\UgcReport[] $reports */
    $reports = $storage->loadMultiple($ids);
    $grupos = [];
    $validTargets = [];

    foreach ($reports as $report) {
      $targetType = (string) $report->get('target_type')->value;
      $targetId = (int) $report->get('target_id')->value;
      $key = $targetType . ':' . $targetId;

      if (!isset($validTargets[$key])) {
        if (!$this->entityTypeManager->hasDefinition($targetType)) {
          $validTargets[$key] = FALSE;
        }
        else {
          $target = $this->entityTypeManager->getStorage($targetType)->load($targetId);
          $validTargets[$key] = ($target !== NULL);
        }
      }

      if (!$validTargets[$key]) {
        continue;
      }

      if (!isset($grupos[$key])) {
        $grupos[$key] = [
          'target_type' => $targetType,
          'target_id' => $targetId,
          'total' => 0,
          'ultima' => (int) $report->get('created')->value,
        ];
      }
      $grupos[$key]['total']++;
    }

    return array_slice(array_values($grupos), 0, $limit);
  }

  /**
   * Migração controlada das sinalizações legadas do ReportQueue para aculta_ugc_report (ARQ-06D, D-04).
   *
   * @param bool $dryRun
   *   Quando TRUE, apenas simula a operação e calcula as contagens sem persistir.
   * @param int $limit
   *   Limite de registros a processar por lote.
   *
   * @return array{dry_run: bool, flags_lidas: int, novas_denuncias: int, ignoradas_duplicadas: int, alvos_invalidos: int}
   *   Relatório estatístico da execução da migração.
   */
  public function migrateFromReportQueue(bool $dryRun = FALSE, int $limit = 500): array {
    if (!$this->entityTypeManager->hasDefinition('flagging')) {
      return [
        'dry_run' => $dryRun,
        'flags_lidas' => 0,
        'novas_denuncias' => 0,
        'ignoradas_duplicadas' => 0,
        'alvos_invalidos' => 0,
      ];
    }

    $flagStorage = $this->entityTypeManager->getStorage('flagging');
    $reportStorage = $this->entityTypeManager->getStorage('aculta_ugc_report');

    $flagIds = $flagStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('flag_id', ReportQueue::FLAGS, 'IN')
      ->range(0, $limit)
      ->execute();

    $flagsCount = count($flagIds);
    if ($flagsCount === 0) {
      return [
        'dry_run' => $dryRun,
        'flags_lidas' => 0,
        'novas_denuncias' => 0,
        'ignoradas_duplicadas' => 0,
        'alvos_invalidos' => 0,
      ];
    }

    $flaggings = $flagStorage->loadMultiple($flagIds);
    $createdCount = 0;
    $ignoredCount = 0;
    $invalidCount = 0;

    foreach ($flaggings as $flagging) {
      $flagId = (int) $flagging->id();
      $targetType = $flagging->getFlaggableType();
      $targetId = (int) $flagging->getFlaggableId();
      $uid = (int) $flagging->getOwnerId();

      // Verifica se o alvo ainda existe.
      if (!$this->entityTypeManager->hasDefinition($targetType)) {
        $invalidCount++;
        continue;
      }
      $target = $this->entityTypeManager->getStorage($targetType)->load($targetId);
      if ($target === NULL) {
        $invalidCount++;
        continue;
      }

      // 1. Verifica se já existe por legacy_flag_id.
      $existingByLegacyId = $reportStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('legacy_flag_id', $flagId)
        ->range(0, 1)
        ->execute();
      if (!empty($existingByLegacyId)) {
        $ignoredCount++;
        continue;
      }

      // 2. Verifica se já existe denúncia deste mesmo par e autor na tabela unificada.
      $existingByTuple = $reportStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('target_type', $targetType)
        ->condition('target_id', $targetId)
        ->condition('uid', $uid)
        ->range(0, 1)
        ->execute();
      if (!empty($existingByTuple)) {
        $ignoredCount++;
        continue;
      }

      if (!$dryRun) {
        $report = $reportStorage->create([
          'target_type' => $targetType,
          'target_id' => $targetId,
          'uid' => $uid,
          'motivo' => 'sinalizacao_legada_forum',
          'purpose' => 'forum',
          'status' => 'pendente',
          'legacy_flag_id' => $flagId,
          'created' => (int) $flagging->getCreatedTime(),
        ]);
        $report->save();
      }
      $createdCount++;
    }

    return [
      'dry_run' => $dryRun,
      'flags_lidas' => $flagsCount,
      'novas_denuncias' => $createdCount,
      'ignoradas_duplicadas' => $ignoredCount,
      'alvos_invalidos' => $invalidCount,
    ];
  }

  /**
   * Reverte a migração das flags legadas de forma segura (ARQ-06D).
   *
   * Remove apenas os registros originados de flagging legado.
   *
   * @return int
   *   Quantidade de denúncias removidas.
   */
  public function rollbackMigration(): int {
    $reportStorage = $this->entityTypeManager->getStorage('aculta_ugc_report');
    $ids = $reportStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('motivo', 'sinalizacao_legada_forum')
      ->exists('legacy_flag_id')
      ->execute();

    if (empty($ids)) {
      return 0;
    }

    $reports = $reportStorage->loadMultiple($ids);
    $reportStorage->delete($reports);
    return count($ids);
  }

}
