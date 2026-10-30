<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Organization;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_user\Entity\OwnershipClaim;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Serviço de reivindicação de posse e titularidade (ARQ-05, D-02, C-02).
 */
final class OwnershipClaimService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly OrganizationMembershipManager $membershipManager,
  ) {}

  /**
   * Confere se a conta possui direito de reivindicar a entidade.
   */
  public function canClaim(EntityInterface $entity, AccountInterface $account): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }

    if ($entity->getEntityTypeId() === 'aculta_event_organization') {
      if (!empty($entity->get('reivindicada')->value)) {
        return FALSE;
      }
      $titular = (int) $entity->get('titular')->target_id;
      if ($titular > 0 && $titular === (int) $account->id()) {
        return FALSE;
      }
    }
    else {
      if (!$entity->hasField('field_allow_claiming')) {
        return FALSE;
      }
      if (empty($entity->get('field_allow_claiming')->value)) {
        return FALSE;
      }

      if ($entity->hasField('field_organizador')) {
        $currentOwner = (int) $entity->get('field_organizador')->target_id;
        if ($currentOwner > 0 && $currentOwner === (int) $account->id()) {
          return FALSE;
        }
      }
    }

    $existing = $this->entityTypeManager->getStorage('aculta_ownership_claim')->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_type', $entity->getEntityTypeId())
      ->condition('target_id', (int) $entity->id())
      ->condition('uid', (int) $account->id())
      ->condition('status', 'pending')
      ->range(0, 1)
      ->execute();

    return empty($existing);
  }

  /**
   * Submete um pedido de reivindicação de posse para a moderação.
   */
  public function submitClaim(EntityInterface $entity, AccountInterface $account, string $justification, ?string $contact = NULL): OwnershipClaim {
    if (!$this->canClaim($entity, $account)) {
      throw new ClaimNotAllowedException(sprintf(
        'A entidade %s de ID %s não pode ser reivindicada pelo usuário %s.',
        $entity->getEntityTypeId(),
        $entity->id(),
        $account->id()
      ));
    }

    $trimmedJustification = trim($justification);
    if ($trimmedJustification === '') {
      throw new ClaimNotAllowedException('A justificativa da reivindicação é obrigatória.');
    }

    /** @var \Drupal\aculta_user\Entity\OwnershipClaim $claim */
    $claim = $this->entityTypeManager->getStorage('aculta_ownership_claim')->create([
      'target_type' => $entity->getEntityTypeId(),
      'target_id' => (int) $entity->id(),
      'uid' => (int) $account->id(),
      'justificativa' => $trimmedJustification,
      'contato' => $contact !== NULL ? trim($contact) : NULL,
      'status' => 'pending',
    ]);
    $claim->save();

    return $claim;
  }

  /**
   * Decide um pedido de reivindicação (aprovação ou rejeição).
   */
  public function decideClaim(OwnershipClaim $claim, AccountInterface $moderator, bool $approved, string $notes): void {
    if (!$moderator->hasPermission('administer aculta claims')) {
      throw new AccessDeniedHttpException('O usuário não possui permissão para moderar reivindicações.');
    }

    if ($claim->get('status')->value !== 'pending') {
      throw new \LogicException('Este pedido de reivindicação já foi decidido anteriormente.');
    }

    $claim->set('status', $approved ? 'approved' : 'rejected');
    $claim->set('decided_by', (int) $moderator->id());
    $claim->set('decided_at', $this->time->getRequestTime());
    $claim->set('decision_notes', trim($notes));
    $claim->save();

    if ($approved) {
      $targetType = (string) $claim->get('target_type')->value;
      $targetId = (int) $claim->get('target_id')->value;
      $applicantUid = (int) $claim->get('uid')->target_id;

      $targetEntity = $this->entityTypeManager->getStorage($targetType)->load($targetId);
      if ($targetEntity !== NULL) {
        if ($targetEntity->hasField('field_organizador')) {
          $targetEntity->set('field_organizador', $applicantUid);
          $targetEntity->save();
        }

        if ($targetType === 'taxonomy_term' || str_contains($targetType, 'organiza')) {
          $this->membershipManager->suspendMemberships((string) $targetId);
          $this->membershipManager->addMembership($applicantUid, (string) $targetId, 'owner', 'approved', TRUE, TRUE);
          if ($targetEntity->hasField('titular')) {
            $targetEntity->set('titular', $applicantUid);
          }
          if ($targetEntity->hasField('reivindicada')) {
            $targetEntity->set('reivindicada', TRUE);
          }
          if ($targetEntity->hasField('reivindicacao_status')) {
            $targetEntity->set('reivindicacao_status', 'em_revisao');
          }
          $targetEntity->save();
        }
      }
    }
  }

  /**
   * Inicia o processo de transferência de titularidade para um coproprietário.
   */
  public function initiateOwnershipTransfer(EntityInterface $entity, AccountInterface $titular, AccountInterface $coOwner): void {
    if (!$entity->hasField('titular') || !$entity->hasField('pending_owner_uid')) {
      throw new \InvalidArgumentException('A entidade não suporta transferência de titularidade.');
    }

    $currentTitularUid = (int) $entity->get('titular')->target_id;
    if ($currentTitularUid !== (int) $titular->id() && !$titular->hasPermission('administer aculta claims')) {
      throw new AccessDeniedHttpException('Apenas o titular atual pode iniciar a transferência de titularidade.');
    }

    $role = $this->membershipManager->getMemberRole($coOwner, (string) $entity->id());
    if ($role !== 'co_owner') {
      throw new \LogicException('A titularidade só pode ser transferida para um coproprietário aprovado.');
    }

    $entity->set('pending_owner_uid', (int) $coOwner->id());
    $entity->save();
  }

  /**
   * Aceita a transferência de titularidade pelo coproprietário convidado.
   */
  public function acceptOwnershipTransfer(EntityInterface $entity, AccountInterface $coOwner): void {
    if (!$entity->hasField('titular') || !$entity->hasField('pending_owner_uid')) {
      throw new \InvalidArgumentException('A entidade não suporta transferência de titularidade.');
    }

    $pendingUid = (int) $entity->get('pending_owner_uid')->target_id;
    if ($pendingUid !== (int) $coOwner->id()) {
      throw new AccessDeniedHttpException('Este usuário não possui convite pendente de titularidade.');
    }

    $oldTitularUid = (int) $entity->get('titular')->target_id;
    if ($oldTitularUid > 0 && $oldTitularUid !== (int) $coOwner->id()) {
      $this->membershipManager->addMembership($oldTitularUid, (string) $entity->id(), 'co_owner', 'approved', TRUE, TRUE);
    }

    $this->membershipManager->addMembership($coOwner, (string) $entity->id(), 'owner', 'approved', TRUE, TRUE);
    $entity->set('titular', (int) $coOwner->id());
    if ($entity->hasField('field_organizador')) {
      $entity->set('field_organizador', (int) $coOwner->id());
    }
    $entity->set('pending_owner_uid', NULL);
    $entity->save();
  }

  /**
   * Recusa a transferência de titularidade pelo coproprietário convidado.
   */
  public function rejectOwnershipTransfer(EntityInterface $entity, AccountInterface $coOwner): void {
    if (!$entity->hasField('pending_owner_uid')) {
      return;
    }

    $pendingUid = (int) $entity->get('pending_owner_uid')->target_id;
    if ($pendingUid !== (int) $coOwner->id()) {
      throw new AccessDeniedHttpException('Usuário não autorizado a recusar este convite.');
    }

    $entity->set('pending_owner_uid', NULL);
    $entity->save();
  }

  /**
   * Executa recuperação administrativa excepcional de titularidade por administrador.
   */
  public function administrativeRecovery(EntityInterface $entity, AccountInterface $admin, AccountInterface $newOwner, string $notes): void {
    if (!$admin->hasPermission('administer aculta claims') && !$admin->hasPermission('administer aculta events')) {
      throw new AccessDeniedHttpException('Apenas administradores podem executar recuperação administrativa.');
    }

    $this->membershipManager->addMembership($newOwner, (string) $entity->id(), 'owner', 'approved', TRUE, TRUE);
    if ($entity->hasField('titular')) {
      $entity->set('titular', (int) $newOwner->id());
    }
    if ($entity->hasField('field_organizador')) {
      $entity->set('field_organizador', (int) $newOwner->id());
    }
    if ($entity->hasField('pending_owner_uid')) {
      $entity->set('pending_owner_uid', NULL);
    }
    if ($entity->hasField('reivindicada')) {
      $entity->set('reivindicada', TRUE);
    }
    if ($entity->hasField('reivindicacao_status')) {
      $entity->set('reivindicacao_status', 'confirmada');
    }
    $entity->save();
  }

}
