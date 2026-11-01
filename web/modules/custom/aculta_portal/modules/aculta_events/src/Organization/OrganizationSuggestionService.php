<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Organization;

use Drupal\aculta_events\Access\EventPermissionPolicy;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_events\Entity\EventOrganizationSuggestion;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Submits and moderates proposals for public, unclaimed organizations.
 */
final class OrganizationSuggestionService {

  /**
   * Public profile fields that can be proposed for change.
   *
   * @var string[]
   */
  private const EDITABLE_FIELDS = [
    'title',
    'body',
    'imagem',
    'links',
    'localidade',
    'uf',
    'ocultar_localidade',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EventPermissionPolicy $permissionPolicy,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
    private readonly Connection $database,
  ) {}

  /**
   * Stores a proposed profile update without changing the public organization.
   *
   * @param \Drupal\aculta_events\Entity\EventOrganization $organization
   *   Organization being edited.
   * @param \Drupal\Core\Session\AccountInterface $author
   *   Account proposing the changes.
   * @param array<string, scalar|null> $changes
   *   Allowlisted field values from the edit form.
   */
  public function submit(EventOrganization $organization, AccountInterface $author, array $changes): EventOrganizationSuggestion {
    /** @var \Drupal\aculta_events\Entity\EventOrganization|null $freshOrganization */
    $freshOrganization = $this->entityTypeManager->getStorage('aculta_event_organization')->loadUnchanged($organization->id());
    if ($freshOrganization === NULL || !$freshOrganization->isUnclaimed() || !$this->permissionPolicy->canCreateEvent($author)) {
      throw new \RuntimeException('A conta não pode sugerir alterações para esta organização.');
    }

    $allowed = [];
    foreach (self::EDITABLE_FIELDS as $field) {
      if (array_key_exists($field, $changes)) {
        $value = $changes[$field];
        if (!is_scalar($value) && $value !== NULL) {
          throw new \RuntimeException('A proposta contém um valor de campo inválido.');
        }
        $allowed[$field] = $value;
      }
    }
    if ($allowed === []) {
      throw new \RuntimeException('A proposta não contém alterações permitidas.');
    }

    if ((int) $author->id() <= 0 || $this->entityTypeManager->getStorage('user')->load($author->id()) === NULL) {
      throw new \RuntimeException('A proposta exige uma conta autenticada válida.');
    }

    /** @var \Drupal\aculta_events\Entity\EventOrganizationSuggestion $suggestion */
    $suggestion = $this->entityTypeManager
      ->getStorage('aculta_event_org_suggestion')
      ->create([
        'title' => (string) $freshOrganization->label(),
        'field_organization' => (int) $freshOrganization->id(),
        'field_submitted_by' => (int) $author->id(),
        'field_changes' => json_encode($allowed, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        'field_original_hash' => $this->fingerprint($freshOrganization),
        'field_status' => 'pending',
      ]);
    $suggestion->save();

    return $suggestion;
  }

  /**
   * Applies a pending proposal if its source organization is unchanged.
   */
  public function approve(EventOrganizationSuggestion $suggestion, AccountInterface $reviewer, string $note = ''): bool {
    $this->assertReviewer($reviewer);
    return $this->decide($suggestion, $reviewer, 'approved', $note);
  }

  /**
   * Rejects a pending proposal without changing the organization.
   */
  public function reject(EventOrganizationSuggestion $suggestion, AccountInterface $reviewer, string $note = ''): bool {
    $this->assertReviewer($reviewer);
    return $this->decide($suggestion, $reviewer, 'rejected', $note);
  }

  /**
   * Performs a serialized, idempotent moderation decision.
   */
  private function decide(EventOrganizationSuggestion $suggestion, AccountInterface $reviewer, string $decision, string $note): bool {
    $id = (int) $suggestion->id();
    $lockName = 'aculta_events.organization_suggestion.' . $id;
    if (!$this->lock->acquire($lockName, 30.0)) {
      throw new \RuntimeException('A proposta está sendo processada. Tente novamente.');
    }
    $organizationId = (int) $suggestion->get('field_organization')->target_id;
    $organizationLockName = 'aculta_events.organization.' . $organizationId;
    if (!$this->lock->acquire($organizationLockName, 30.0)) {
      $this->lock->release($lockName);
      throw new \RuntimeException('A organização está sendo atualizada. Tente novamente.');
    }

    $transaction = NULL;
    try {
      $transaction = $this->database->startTransaction();
      $storage = $this->entityTypeManager->getStorage('aculta_event_org_suggestion');
      /** @var \Drupal\aculta_events\Entity\EventOrganizationSuggestion|null $current */
      $current = $storage->loadUnchanged($id);
      if ($current === NULL || $current->get('field_status')->value !== 'pending') {
        return FALSE;
      }

      $organizationId = (int) $current->get('field_organization')->target_id;
      if ($decision === 'approved') {
        // Serialize approval against other writes to the organization's row.
        $this->database->select('aculta_event_organization', 'organization')
          ->fields('organization', ['id'])
          ->condition('id', $organizationId)
          ->forUpdate()
          ->execute()
          ->fetchField();
      }

      /** @var \Drupal\aculta_events\Entity\EventOrganization|null $organization */
      $organization = $this->entityTypeManager
        ->getStorage('aculta_event_organization')
        ->loadUnchanged($organizationId);

      if ($decision === 'approved') {
        if ($organization === NULL
          || !$organization->isUnclaimed()
          || !hash_equals(
            (string) $current->get('field_original_hash')->value,
            $this->fingerprint($organization),
          )) {
          $current->set('field_status', 'stale');
          $this->recordDecision($current, $reviewer, 'A organização foi alterada ou reivindicada desde o envio.');
          return FALSE;
        }

        $changes = json_decode((string) $current->get('field_changes')->value, TRUE, 512, JSON_THROW_ON_ERROR);
        foreach (self::EDITABLE_FIELDS as $field) {
          if (array_key_exists($field, $changes)) {
            $organization->set($field, $changes[$field]);
          }
        }
        $organization->save();
      }

      $this->recordDecision($current, $reviewer, $note, $decision);
      return TRUE;
    }
    catch (\Throwable $exception) {
      $transaction?->rollBack();
      throw $exception;
    }
    finally {
      $this->lock->release($organizationLockName);
      $this->lock->release($lockName);
    }
  }

  /**
   * Persists reviewer and decision metadata.
   */
  private function recordDecision(EventOrganizationSuggestion $suggestion, AccountInterface $reviewer, string $note, string $status = 'stale'): void {
    $suggestion->set('field_status', $status);
    $suggestion->set('field_reviewed_by', (int) $reviewer->id());
    $suggestion->set('field_reviewed', $this->time->getRequestTime());
    $suggestion->set('field_review_note', mb_substr(trim($note), 0, 4000));
    $suggestion->save();
  }

  /**
   * Requires the moderation permission at service boundary.
   */
  private function assertReviewer(AccountInterface $reviewer): void {
    if (!$reviewer->hasPermission('administer aculta events')) {
      throw new \RuntimeException('Apenas administradores de Eventos podem revisar propostas.');
    }
  }

  /**
   * Hashes persisted organization values for stale-proposal detection.
   */
  private function fingerprint(EventOrganization $organization): string {
    return hash('sha256', json_encode(
      $organization->toArray(),
      JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
    ));
  }

}
