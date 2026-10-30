<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_ugc\ModerationScope;
use Drupal\aculta_user\Moderation\ReportQueue;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Fila unificada de moderação e migração controlada (ARQ-06C, ARQ-06D, ARQ-06G).
 */
final class UgcReportQueue {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ?AccountInterface $currentUser = NULL,
    private readonly ?Connection $database = NULL,
  ) {}

  /**
   * Retorna itens agrupados da fila com controle de acesso, agregação e paginação.
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

    $db = $this->database;
    $grupos = [];
    $migratedFlagIds = [];
    $decidedTargets = [];

    if ($db !== NULL && $db->schema()->tableExists('aculta_ugc_report')) {
      // 1. Identifica TODOS os legacy_flag_id já presentes na base UGC.
      // Impede reincorporação de flags já analisadas ou decididas.
      if ($db->schema()->fieldExists('aculta_ugc_report', 'legacy_flag_id')) {
        $migratedFlagIds = $db->select('aculta_ugc_report', 'r')
          ->fields('r', ['legacy_flag_id'])
          ->condition('legacy_flag_id', 0, '>')
          ->condition('purpose', 'forum')
          ->distinct()
          ->execute()
          ->fetchCol();
      }

      // Alvos já decididos não devem ser reabertos via flags legadas.
      if ($status === 'pendente' && in_array('forum', $allowedPurposes, TRUE)) {
        $decidedRows = $db->select('aculta_ugc_report', 'r')
          ->fields('r', ['target_type', 'target_id'])
          ->condition('purpose', 'forum')
          ->condition('status', ['procedente', 'improcedente'], 'IN')
          ->distinct()
          ->execute()
          ->fetchAll();
        foreach ($decidedRows as $dRow) {
          $decidedTargets[$dRow->target_type . ':' . $dRow->target_id] = TRUE;
        }
      }

      // 2. Consulta agregada SQL no status e purposes solicitados.
      $ugcQuery = $db->select('aculta_ugc_report', 'r');
      $ugcQuery->fields('r', ['target_type', 'target_id', 'purpose']);
      $ugcQuery->addExpression('COUNT(*)', 'total');
      $ugcQuery->addExpression('MAX(created)', 'ultima');
      $ugcQuery->condition('status', $status);
      $ugcQuery->condition('purpose', $allowedPurposes, 'IN');
      $ugcQuery->groupBy('r.target_type');
      $ugcQuery->groupBy('r.target_id');
      $ugcQuery->groupBy('r.purpose');
      $ugcRows = $ugcQuery->execute()->fetchAll();

      foreach ($ugcRows as $row) {
        $key = $row->target_type . ':' . $row->target_id . ':' . $row->purpose;
        $grupos[$key] = [
          'target_type' => (string) $row->target_type,
          'target_id' => (int) $row->target_id,
          'purpose' => (string) $row->purpose,
          'total' => (int) $row->total,
          'ultima' => (int) $row->ultima,
          'motivos' => [],
        ];
      }

      // 3. Reconciliação com flags legadas do Fórum pendentes de migração.
      if (in_array('forum', $allowedPurposes, TRUE)
        && $status === 'pendente'
        && $db->schema()->tableExists('flagging')) {
        $flagQuery = $db->select('flagging', 'f');
        $flagQuery->join('users_field_data', 'u', 'u.uid = f.uid');
        $flagQuery->fields('f', ['entity_type', 'entity_id']);
        $flagQuery->addExpression('COUNT(*)', 'total');
        $flagQuery->addExpression('MAX(f.created)', 'ultima');
        $flagQuery->condition('f.flag_id', ReportQueue::FLAGS, 'IN');
        $flagQuery->condition('u.status', 1);
        if (!empty($migratedFlagIds)) {
          $flagQuery->condition('f.id', $migratedFlagIds, 'NOT IN');
        }
        $flagQuery->groupBy('f.entity_type');
        $flagQuery->groupBy('f.entity_id');
        $flagRows = $flagQuery->execute()->fetchAll();

        foreach ($flagRows as $fRow) {
          $targetKey = $fRow->entity_type . ':' . $fRow->entity_id;
          if (isset($decidedTargets[$targetKey])) {
            continue;
          }
          $key = $targetKey . ':forum';
          if (!isset($grupos[$key])) {
            $grupos[$key] = [
              'target_type' => (string) $fRow->entity_type,
              'target_id' => (int) $fRow->entity_id,
              'purpose' => 'forum',
              'total' => 0,
              'ultima' => 0,
              'motivos' => ['sinalizacao_legada_forum'],
            ];
          }
          elseif (!in_array('sinalizacao_legada_forum', $grupos[$key]['motivos'], TRUE)) {
            $grupos[$key]['motivos'][] = 'sinalizacao_legada_forum';
          }
          $grupos[$key]['total'] += (int) $fRow->total;
          $grupos[$key]['ultima'] = max($grupos[$key]['ultima'], (int) $fRow->ultima);
        }
      }
    }

    if (empty($grupos)) {
      return [
        'items' => [],
        'total_items' => 0,
        'total_pages' => 0,
        'page' => $page,
        'limit' => $limit,
      ];
    }

    // 4. Verificação em lote da existência dos alvos (sem queries N+1).
    // Agrupa IDs por target_type e consulta apenas uma vez por tipo.
    $idsByType = [];
    foreach ($grupos as $g) {
      $idsByType[$g['target_type']][] = $g['target_id'];
    }

    $existingTargetIds = [];
    foreach ($idsByType as $targetType => $tIds) {
      if (!$this->entityTypeManager->hasDefinition($targetType)) {
        continue;
      }
      $uniqueIds = array_values(array_unique($tIds));
      $storage = $this->entityTypeManager->getStorage($targetType);
      $idKey = $storage->getEntityType()->getKey('id');
      if ($idKey) {
        $foundIds = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition($idKey, $uniqueIds, 'IN')
          ->execute();
        foreach ($foundIds as $foundId) {
          $existingTargetIds[$targetType . ':' . $foundId] = TRUE;
        }
      }
      else {
        foreach ($uniqueIds as $uId) {
          if ($storage->load($uId) !== NULL) {
            $existingTargetIds[$targetType . ':' . $uId] = TRUE;
          }
        }
      }
    }

    // Remove alvos excluídos fisicamente preservando o purpose dos grupos.
    $validGrupos = [];
    foreach ($grupos as $g) {
      $targetKey = $g['target_type'] . ':' . $g['target_id'];
      if (isset($existingTargetIds[$targetKey])) {
        $validGrupos[] = $g;
      }
    }

    // 5. Ordenação determinística: mais recentes primeiro; desempate por target_id.
    usort($validGrupos, static function (array $a, array $b): int {
      $cmp = $b['ultima'] <=> $a['ultima'];
      if ($cmp !== 0) {
        return $cmp;
      }
      $cmpId = $b['target_id'] <=> $a['target_id'];
      if ($cmpId !== 0) {
        return $cmpId;
      }
      return strcmp($a['purpose'], $b['purpose']);
    });

    $totalItems = count($validGrupos);
    $totalPages = $limit > 0 ? (int) ceil($totalItems / $limit) : 1;
    $offset = max(0, $page * $limit);
    $pagedItems = array_slice($validGrupos, $offset, $limit);

    // 6. Enriquecimento de motivos respeitando (target_type, target_id, purpose, status).
    if (!empty($pagedItems) && $db !== NULL) {
      $orCond = $db->condition('OR');
      foreach ($pagedItems as $pItem) {
        $andCond = $db->condition('AND')
          ->condition('target_type', $pItem['target_type'])
          ->condition('target_id', $pItem['target_id'])
          ->condition('purpose', $pItem['purpose']);
        $orCond->condition($andCond);
      }
      $motivoQuery = $db->select('aculta_ugc_report', 'r')
        ->fields('r', ['target_type', 'target_id', 'purpose', 'motivo'])
        ->condition($orCond)
        ->condition('status', $status)
        ->distinct();
      $motivoRows = $motivoQuery->execute()->fetchAll();

      $motivosByGroup = [];
      foreach ($motivoRows as $mRow) {
        $gKey = $mRow->target_type . ':' . $mRow->target_id . ':' . $mRow->purpose;
        $motivosByGroup[$gKey][] = (string) $mRow->motivo;
      }
      foreach ($pagedItems as &$pItemRef) {
        $gKey = $pItemRef['target_type'] . ':' . $pItemRef['target_id'] . ':' . $pItemRef['purpose'];
        if (isset($motivosByGroup[$gKey])) {
          foreach ($motivosByGroup[$gKey] as $m) {
            if (!in_array($m, $pItemRef['motivos'], TRUE)) {
              $pItemRef['motivos'][] = $m;
            }
          }
        }
      }
      unset($pItemRef);
    }

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
   * @param string|null $purpose
   *   Propósito das denúncias.
   * @param int $limit
   *   Limite máximo de itens retornados.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   Conta do moderador consultante.
   *
   * @return array<int, array{target_type: string, target_id: int, total: int, ultima: int}>
   *   Lista dos itens pendentes agrupados.
   */
  public function getPendingItems(?string $purpose = NULL, int $limit = 50, ?AccountInterface $account = NULL): array {
    $moderator = $account ?? $this->currentUser;
    if ($moderator === NULL || $moderator->isAnonymous()) {
      throw new AccessDeniedHttpException('Acesso negado: usuário anônimo não tem permissão para moderar.');
    }
    $checkPurpose = $purpose ?? 'forum';
    if (!ModerationScope::allows($moderator, $checkPurpose)) {
      throw new AccessDeniedHttpException('Acesso negado: sem permissão para consultar denúncias deste purpose.');
    }
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
