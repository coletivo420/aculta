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

  public function canClaim(EntityInterface $entity, AccountInterface $account): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }

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
          $this->membershipManager->addMembership($applicantUid, (string) $targetId, 'admin', 'approved');
        }
      }
    }
  }

}