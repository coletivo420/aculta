<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_ugc\Service\ReportService;
use Drupal\flag\FlaggingInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Conecta sinalizações do Fórum à infraestrutura UGC (ARQ-06F, ARQ-06G).
 */
final class ForumReportUgcBridgeHooks {

  public const FLAGS = ['sinalizar_topico', 'sinalizar_comentario', 'sinalizar_post_forum'];

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_ugc.moderation_notifier')]
    private readonly mixed $notifier = NULL,
    #[Autowire(service: 'lock')]
    private readonly ?LockBackendInterface $lock = NULL,
    #[Autowire(service: 'datetime.time')]
    private readonly ?TimeInterface $time = NULL,
    #[Autowire(service: 'logger.channel.aculta_forum')]
    private readonly ?LoggerInterface $logger = NULL,
  ) {}

  /**
   * Espelha sinalização do Fórum em denúncia UGC ao salvar Flagging.
   */
  #[Hook('flagging_insert')]
  public function flaggingInsert(EntityInterface $entity): void {
    if (!$entity instanceof FlaggingInterface) {
      return;
    }
    $flagId = $entity->getFlagId();
    if (!in_array($flagId, self::FLAGS, TRUE)) {
      return;
    }
    if (!$this->entityTypeManager->hasDefinition('aculta_ugc_report')) {
      return;
    }

    $targetType = $entity->getFlaggableType();
    $targetId = (int) $entity->getFlaggableId();
    $uid = (int) $entity->getOwnerId();

    // 1. Rejeita sinalização anônima.
    if ($uid <= 0) {
      return;
    }

    // 2. Confere existência do alvo denunciado.
    if (!$this->entityTypeManager->hasDefinition($targetType)) {
      return;
    }
    $target = $this->entityTypeManager->getStorage($targetType)->load($targetId);
    if ($target === NULL) {
      return;
    }

    // 3. Carrega e valida conta denunciante.
    $userStorage = $this->entityTypeManager->getStorage('user');
    $account = $userStorage->load($uid);
    if (!$account instanceof AccountInterface || !$account->isActive()) {
      throw new AccessDeniedHttpException('Conta inválida ou inativa não pode denunciar.');
    }

    // 4. Entity Access: a conta só pode denunciar o que tem permissão para ver.
    if (!$target->access('view', $account)) {
      throw new AccessDeniedHttpException('A conta não pode denunciar conteúdo que não tem permissão para visualizar.');
    }

    // 5. Valida se o conteúdo pertence ao propósito do Fórum.
    if (!$this->isForumContent($target)) {
      throw new AccessDeniedHttpException('O conteúdo denunciado não pertence ao Fórum.');
    }

    $reportStorage = $this->entityTypeManager->getStorage('aculta_ugc_report');

    // 6. Lock contra concorrência para evitar duplicidade simultânea.
    $lockName = sprintf('aculta_ugc:flag_bridge:%s:%d:%d', $targetType, $targetId, $uid);
    if ($this->lock !== NULL && !$this->lock->acquire($lockName, 10.0)) {
      return;
    }

    try {
      // 7. Não permite denúncia duplicada em aberto (pendente ou em análise).
      $existingOpen = $reportStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('target_type', $targetType)
        ->condition('target_id', $targetId)
        ->condition('uid', $uid)
        ->condition('status', ['pendente', 'em_analise'], 'IN')
        ->range(0, 1)
        ->execute();

      if (!empty($existingOpen)) {
        return;
      }

      // 8. Se já houver decisão final para este usuário neste alvo, não reabre.
      $existingDecided = $reportStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('target_type', $targetType)
        ->condition('target_id', $targetId)
        ->condition('uid', $uid)
        ->condition('status', ['procedente', 'improcedente'], 'IN')
        ->range(0, 1)
        ->execute();

      if (!empty($existingDecided)) {
        return;
      }

      // 9. Limite diário por conta contra assédio em massa / DoS de flags.
      $now = $this->time !== NULL ? $this->time->getRequestTime() : time();
      $countToday = $reportStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('uid', $uid)
        ->condition('created', $now - 86400, '>=')
        ->count()
        ->execute();

      if ((int) $countToday >= ReportService::DAILY_LIMIT) {
        if ($this->logger !== NULL) {
          $this->logger->warning('Conta @uid excedeu o limite diário de denúncias via Fórum.', ['@uid' => $uid]);
        }
        throw new AccessDeniedHttpException('Limite diário de denúncias atingido.');
      }

      $flagIdNum = (int) $entity->id();

      /** @var \Drupal\aculta_ugc\Entity\UgcReport $report */
      $report = $reportStorage->create([
        'target_type' => $targetType,
        'target_id' => $targetId,
        'uid' => $uid,
        'motivo' => 'sinalizacao_legada_forum',
        'purpose' => 'forum',
        'status' => 'pendente',
        'legacy_flag_id' => $flagIdNum,
        'created' => (int) $entity->getCreatedTime(),
      ]);
      $report->save();
    }
    finally {
      if ($this->lock !== NULL) {
        $this->lock->release($lockName);
      }
    }

    // 10. Notificação segura sem interromper o fluxo em caso de falha de envio.
    if ($this->notifier !== NULL && method_exists($this->notifier, 'notifyNewReport')) {
      try {
        $this->notifier->notifyNewReport($report);
      }
      catch (\Throwable $e) {
        if ($this->logger !== NULL) {
          $this->logger->error('Falha ao enviar notificação de moderação do Fórum: @msg', ['@msg' => $e->getMessage()]);
        }
      }
    }
  }

  /**
   * Remove denúncia pendente correspondente caso a sinalização seja desfeita.
   */
  #[Hook('flagging_delete')]
  public function flaggingDelete(EntityInterface $entity): void {
    if (!$entity instanceof FlaggingInterface) {
      return;
    }
    $flagId = $entity->getFlagId();
    if (!in_array($flagId, self::FLAGS, TRUE)) {
      return;
    }
    if (!$this->entityTypeManager->hasDefinition('aculta_ugc_report')) {
      return;
    }

    $flagIdNum = (int) $entity->id();
    $reportStorage = $this->entityTypeManager->getStorage('aculta_ugc_report');

    // Só exclui se a denúncia estiver estritamente 'pendente'.
    // Denúncias 'em_analise', 'procedente' ou 'improcedente' são preservadas.
    $ids = $reportStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('legacy_flag_id', $flagIdNum)
      ->condition('status', 'pendente')
      ->execute();

    if (!empty($ids)) {
      $reports = $reportStorage->loadMultiple($ids);
      $reportStorage->delete($reports);
    }
  }

  /**
   * Confere se a entidade pertence ao propósito Fórum.
   */
  private function isForumContent(EntityInterface $entity): bool {
    $entityTypeId = $entity->getEntityTypeId();
    if ($entityTypeId === 'aculta_forum_post') {
      return TRUE;
    }
    if ($entityTypeId === 'node') {
      return $entity->bundle() === 'aculta_forum_topic';
    }
    if ($entityTypeId === 'comment') {
      $commented = method_exists($entity, 'getCommentedEntity') ? $entity->getCommentedEntity() : NULL;
      return $commented instanceof EntityInterface && $this->isForumContent($commented);
    }
    return FALSE;
  }

}
