<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_ugc\Entity\UgcReport;
use Drupal\aculta_ugc\Exception\ReportNotAllowedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Serviço unificado de denúncias de conteúdo gerado por usuários (ARQ-06).
 */
final class ReportService {

  public const DAILY_LIMIT = 20;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly ModerationNotifier $notifier,
  ) {}

  /**
   * Verifica se o usuário pode denunciar a entidade.
   */
  public function canReport(EntityInterface $entity, AccountInterface $account): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }

    $uid = (int) $account->id();
    $targetType = $entity->getEntityTypeId();
    $targetId = (int) $entity->id();

    // 1. Não permite denúncia duplicada em aberto para o mesmo objeto.
    $existing = $this->entityTypeManager->getStorage('aculta_ugc_report')->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_type', $targetType)
      ->condition('target_id', $targetId)
      ->condition('uid', $uid)
      ->condition('status', ['pendente', 'em_analise'], 'IN')
      ->range(0, 1)
      ->execute();

    if (!empty($existing)) {
      return FALSE;
    }

    // 2. Limite diário por conta contra assédio em massa.
    $oneDayAgo = $this->time->getRequestTime() - 86400;
    $countToday = $this->entityTypeManager->getStorage('aculta_ugc_report')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('created', $oneDayAgo, '>=')
      ->count()
      ->execute();

    return $countToday < self::DAILY_LIMIT;
  }

  /**
   * Registra uma nova denúncia.
   */
  public function createReport(EntityInterface $entity, AccountInterface $account, string $motivo, ?string $purpose = NULL): UgcReport {
    if (!$this->canReport($entity, $account)) {
      throw new ReportNotAllowedException('Denúncia não permitida: limite diário atingido ou denúncia já pendente.');
    }

    $resolvedPurpose = $purpose ?? $this->resolvePurpose($entity);

    /** @var \Drupal\aculta_ugc\Entity\UgcReport $report */
    $report = $this->entityTypeManager->getStorage('aculta_ugc_report')->create([
      'target_type' => $entity->getEntityTypeId(),
      'target_id' => (int) $entity->id(),
      'uid' => (int) $account->id(),
      'motivo' => trim($motivo),
      'purpose' => $resolvedPurpose,
      'status' => 'pendente',
    ]);
    $report->save();

    // Notifica os moderadores daquele purpose sem vazar identidade.
    $this->notifier->notifyNewReport($report);

    return $report;
  }

  /**
   * Decide uma denúncia via moderação.
   */
  public function decideReport(UgcReport $report, AccountInterface $moderator, string $status, string $notes): void {
    if (!$moderator->hasPermission('moderar denuncias ugc')) {
      throw new AccessDeniedHttpException('Usuário sem permissão para moderar denúncias.');
    }

    $report->set('status', $status);
    $report->set('decided_by', (int) $moderator->id());
    $report->set('decided_at', $this->time->getRequestTime());
    $report->set('decision_notes', trim($notes));
    $report->save();
  }

  /**
   * Infere o purpose com base no tipo da entidade.
   */
  public function resolvePurpose(EntityInterface $entity): string {
    $type = $entity->getEntityTypeId();
    if (str_contains($type, 'event')) {
      return 'eventos';
    }
    if (str_contains($type, 'forum') || str_contains($type, 'comment')) {
      return 'forum';
    }
    if ($type === 'node') {
      /** @var \Drupal\node\NodeInterface $entity */
      $bundle = $entity->bundle();
      if ($bundle === 'revista') {
        return 'revista';
      }
      if ($bundle === 'wiki_entry') {
        return 'wiki';
      }
      if ($bundle === 'aculta_forum_topic') {
        return 'forum';
      }
    }
    return 'portal';
  }

}