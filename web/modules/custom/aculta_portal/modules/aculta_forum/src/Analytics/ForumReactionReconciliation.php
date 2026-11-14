<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Analytics;

use Drupal\aculta_forum\Service\DiscussionReactionManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Psr\Log\LoggerInterface;

/**
 * Auditoria e reconciliação das reações gravadas com escopo antigo.
 *
 * Por padrão é somente leitura. Com `apply`, reclassifica cada reação pelo
 * escopo atual e reconstrói `aculta_discussion_reaction_user_stats` a partir
 * das reações públicas. A execução é idempotente, limitada por lote e
 * protegida por lock; nenhum conteúdo, autor ou visitante é exposto no
 * relatório.
 */
final class ForumReactionReconciliation {

  /**
   * Nome do lock compartilhado entre execuções concorrentes.
   */
  public const LOCK_NAME = 'aculta_forum_reaction_reconciliation';

  /**
   * Tamanho padrão do lote.
   */
  public const DEFAULT_BATCH_SIZE = 200;

  /**
   * Estado de retenção/rollback documentado no plano FST.
   *
   * O dry-run produz a contagem antes e depois. O estado anterior dos
   * contadores agregados é reproduzível: a tabela de reações por pessoa é a
   * fonte, então uma nova execução com `apply` calcula novamente o mesmo
   * resultado. Não há histórico destrutivo a restaurar.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ForumAnalyticsScopeResolver $scopeResolver,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Inspeciona (e opcionalmente corrige) as reações gravadas.
   *
   * @param bool $apply
   *   FALSE para dry-run. TRUE grava a classificação e reconstrói contadores.
   * @param int $batchSize
   *   Reações lidas por lote.
   * @param int|null $limit
   *   Limite total de reações inspecionadas; NULL percorre tudo.
   *
   * @return array<string, int|string|bool>
   *   Relatório estruturado da execução.
   */
  public function run(bool $apply = FALSE, int $batchSize = self::DEFAULT_BATCH_SIZE, ?int $limit = NULL): array {
    if ($batchSize < 1 || $batchSize > 1000) {
      throw new \InvalidArgumentException('O lote deve ficar entre 1 e 1000 reações.');
    }
    $lockAcquired = $this->lock->acquire(self::LOCK_NAME, 900);
    if (!$lockAcquired) {
      return [
        'status' => 'locked',
        'scanned' => 0,
        'public' => 0,
        'restricted' => 0,
        'divergences' => 0,
        'unresolved' => 0,
        'updated' => 0,
      ];
    }

    $report = [
      'status' => $apply ? 'applied' : 'dry_run',
      'scanned' => 0,
      'public' => 0,
      'restricted' => 0,
      'divergences' => 0,
      'unresolved' => 0,
      'updated' => 0,
    ];

    try {
      $cursor = 0;
      $storage = $this->entityTypeManager->getStorage('aculta_forum_post');
      while ($limit === NULL || $report['scanned'] < $limit) {
        $remaining = $limit === NULL ? $batchSize : min($batchSize, $limit - $report['scanned']);
        if ($remaining < 1) {
          break;
        }
        $rows = $this->database->select(DiscussionReactionManager::TABLE_REACTIONS, 'r')
          ->fields('r', ['id', 'entity_type', 'entity_id', 'context_id', 'is_public_context'])
          ->condition('id', $cursor, '>')
          ->orderBy('id')
          ->range(0, $remaining)
          ->execute()
          ->fetchAll();
        if ($rows === []) {
          break;
        }

        foreach ($rows as $row) {
          $cursor = (int) $row->id;
          $report['scanned']++;
          $entity = $this->loadTarget((string) $row->entity_type, (int) $row->entity_id, $storage);
          if ($entity === NULL) {
            $report['unresolved']++;
            continue;
          }
          $scope = $this->scopeResolver->resolve($entity);
          if (!$scope->isKnown()) {
            $report['unresolved']++;
          }
          $resolvedPublic = $scope->isPublicForum();
          $resolvedPublic ? $report['public']++ : $report['restricted']++;
          $storedPublic = (int) $row->is_public_context === 1;
          $scopeChanged = (string) $row->context_id !== $scope->scope;
          if ($resolvedPublic !== $storedPublic || $scopeChanged) {
            $report['divergences']++;
            if ($apply) {
              $this->database->update(DiscussionReactionManager::TABLE_REACTIONS)
                ->fields([
                  'context_id' => $scope->scope,
                  'is_public_context' => $resolvedPublic ? 1 : 0,
                ])
                ->condition('id', $row->id)
                ->execute();
              $report['updated']++;
            }
          }
        }
      }

      if ($apply) {
        $report['user_stats_rebuilt'] = $this->rebuildUserStats();
      }
    }
    catch (\Throwable $exception) {
      $this->logger->error('Reconciliação de reações interrompida: @message', [
        '@message' => $exception->getMessage(),
      ]);
      $report['status'] = 'failed';
    }
    finally {
      $this->lock->release(self::LOCK_NAME);
    }

    return $report;
  }

  /**
   * Carrega a entidade reagida, se ainda existir.
   */
  private function loadTarget(string $entityType, int $entityId, EntityStorageInterface $forumPostStorage): ?EntityInterface {
    if ($entityId <= 0 || !in_array($entityType, ['node', 'comment', 'aculta_forum_post'], TRUE)) {
      return NULL;
    }
    try {
      $entity = $entityType === 'aculta_forum_post'
        ? $forumPostStorage->load($entityId)
        : $this->entityTypeManager->getStorage($entityType)->load($entityId);
    }
    catch (\Throwable) {
      return NULL;
    }
    return $entity instanceof EntityInterface ? $entity : NULL;
  }

  /**
   * Reconstrói os contadores operacionais a partir das reações públicas.
   *
   * @return int
   *   Quantidade de contas regravadas.
   */
  private function rebuildUserStats(): int {
    $this->database->delete(DiscussionReactionManager::TABLE_STATS)->execute();
    $created = $this->time->getRequestTime();

    $given = [];
    $result = $this->database->select(DiscussionReactionManager::TABLE_REACTIONS, 'r')
      ->fields('r', ['uid'])
      ->condition('is_public_context', 1)
      ->condition('uid', 0, '>')
      ->groupBy('uid');
    $result->addExpression('COUNT(id)', 'total');
    foreach ($result->execute() as $row) {
      $given[(int) $row->uid] = (int) $row->total;
    }

    $received = [];
    $result = $this->database->select(DiscussionReactionManager::TABLE_REACTIONS, 'r')
      ->fields('r', ['author_uid'])
      ->condition('is_public_context', 1)
      ->condition('author_uid', 0, '>');
    $result->where('author_uid <> uid');
    $result->groupBy('author_uid');
    $result->addExpression('COUNT(id)', 'total');
    foreach ($result->execute() as $row) {
      $received[(int) $row->author_uid] = (int) $row->total;
    }

    $uids = array_unique(array_merge(array_keys($given), array_keys($received)));
    $rows = [];
    foreach ($uids as $uid) {
      $rows[] = [
        'uid' => $uid,
        'reactions_given' => $given[$uid] ?? 0,
        'reactions_received' => $received[$uid] ?? 0,
        'updated' => $created,
      ];
    }
    foreach (array_chunk($rows, 100) as $chunk) {
      $insert = $this->database->insert(DiscussionReactionManager::TABLE_STATS)
        ->fields(['uid', 'reactions_given', 'reactions_received', 'updated']);
      foreach ($chunk as $row) {
        $insert->values($row);
      }
      $insert->execute();
    }

    return count($rows);
  }

}
