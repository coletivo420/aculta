<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\flag\FlaggingInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Conecta sinalizações do Fórum à infraestrutura transversal UGC (ARQ-06F).
 */
final class ForumReportUgcBridgeHooks {

  public const FLAGS = ['sinalizar_topico', 'sinalizar_comentario', 'sinalizar_post_forum'];

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_ugc.moderation_notifier')]
    private readonly mixed $notifier = NULL,
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

    $reportStorage = $this->entityTypeManager->getStorage('aculta_ugc_report');
    $flagIdNum = (int) $entity->id();
    $targetType = $entity->getFlaggableType();
    $targetId = (int) $entity->getFlaggableId();
    $uid = (int) $entity->getOwnerId();

    // Idempotência: impede duplicação se já existir denúncia aberta ou migrada.
    $existing = $reportStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_type', $targetType)
      ->condition('target_id', $targetId)
      ->condition('uid', $uid)
      ->condition('status', ['pendente', 'em_analise'], 'IN')
      ->range(0, 1)
      ->execute();

    if (!empty($existing)) {
      return;
    }

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

    if ($this->notifier !== NULL && method_exists($this->notifier, 'notifyNewReport')) {
      try {
        $this->notifier->notifyNewReport($report);
      }
      catch (\Throwable) {
        // Falha no aviso não impede o registro da sinalização.
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

}
