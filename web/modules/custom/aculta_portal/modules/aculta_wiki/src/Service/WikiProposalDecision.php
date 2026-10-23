<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\domain\DomainInterface;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\node\NodeInterface;
use Drupal\aculta_wiki\Notifier\WikiProposalOutcomeNotifier;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Records editorial feedback on a private Wiki proposal.
 */
final class WikiProposalDecision {

  /**
   * Allowed proposal decisions.
   *
   * @var list<string>
   */
  public const DECISIONS = ['needs_changes', 'declined', 'accepted'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly Connection $database,
    private readonly WikiProposalOutcomeNotifier $notifier,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly StateTransitionValidationInterface $transitionValidation,
    private readonly ModerationInformationInterface $moderationInformation,
  ) {}

  /**
   * Records a non-public editorial decision as a new proposal revision.
   *
   * @param \Drupal\aculta_wiki\Entity\WikiProposal $proposal
   *   The proposal shown to the editor.
   * @param int $expectedRevisionId
   *   Revision ID shown in the decision form.
   * @param string $decision
   *   Either `needs_changes` or `declined`.
   * @param string $note
   *   Plain text feedback for the contributor.
   * @param list<string> $approvedFields
   *   Subset of structured proposal fields to apply when accepting.
   * @param array<string, string> $listModes
   *   Per-field `replace` or `append` operation for list fields.
   *
   * @return \Drupal\aculta_wiki\Entity\WikiProposal
   *   The saved current default revision.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   When the account cannot moderate the proposal.
   * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException
   *   When another action changed the proposal after the form was opened.
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   When the decision, proposal state, or note is invalid.
   */
  public function decide(
    WikiProposal $proposal,
    int $expectedRevisionId,
    string $decision,
    string $note,
    array $approvedFields = [],
    array $listModes = [],
  ): WikiProposal {
    if (!$this->currentUser->hasPermission('moderate wiki proposals')) {
      throw new AccessDeniedHttpException();
    }
    if (!in_array($decision, self::DECISIONS, TRUE)) {
      throw new UnprocessableEntityHttpException('Decisão editorial inválida.');
    }
    $note = trim($note);
    if ($note === '' || mb_strlen($note) > 5000) {
      throw new UnprocessableEntityHttpException('A nota editorial é obrigatória e deve ter até 5.000 caracteres.');
    }

    $transaction = $this->database->startTransaction();
    try {
      $lockedId = $this->database->select('aculta_wiki_proposal', 'proposal')
        ->fields('proposal', ['id'])
        ->condition('id', $proposal->id())
        ->forUpdate()
        ->execute()
        ->fetchField();
      if (!$lockedId) {
        throw new ConflictHttpException('A proposta não está mais disponível para decisão.');
      }

      $storage = $this->entityTypeManager->getStorage('aculta_wiki_proposal');
      $current = $storage->loadUnchanged($proposal->id());
      if (!$current instanceof WikiProposal || !$current->isDefaultRevision()) {
        throw new ConflictHttpException('A proposta não está mais disponível para decisão.');
      }
      if ((int) $current->getRevisionId() !== $expectedRevisionId
        || !in_array($current->get('state')->value, ['submitted', 'needs_changes'], TRUE)) {
        throw new ConflictHttpException('A proposta mudou desde que o formulário foi aberto. Atualize a fila antes de decidir.');
      }

      $updateAccess = $current->access('update', $this->currentUser, TRUE);
      if (!$updateAccess->isAllowed()) {
        throw new AccessDeniedHttpException();
      }

      if ($decision === 'accepted') {
        $this->applyAcceptedFields($current, $approvedFields, $listModes);
      }

      $current->setNewRevision(TRUE);
      $current->isDefaultRevision(TRUE);
      $current->set('state', $decision);
      $current->set('decision_note', $note);
      $current->setRevisionLogMessage(match ($decision) {
        'needs_changes' => 'Proposta devolvida para ajustes',
        'accepted' => 'Proposta aceita; campos aprovados aplicados e publicados',
        default => 'Proposta recusada',
      });
      $current->save();
      $this->notifier->notify($current);
      unset($transaction);

      return $current;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Applies the selected fields using Wiki workflow permissions.
   *
   * @param \Drupal\aculta_wiki\Entity\WikiProposal $proposal
   *   Current locked proposal.
   * @param list<string> $approvedFields
   *   Fields selected by the editor for application.
   * @param array<string, string> $listModes
   *   Per-field `replace` or `append` choice for list fields.
   */
  private function applyAcceptedFields(WikiProposal $proposal, array $approvedFields, array $listModes): void {
    $proposedFields = $proposal->getProposedFields();
    $approvedFields = array_values(array_unique(array_filter($approvedFields, 'is_string')));
    if ($approvedFields === [] || array_diff($approvedFields, array_keys($proposedFields)) !== []) {
      throw new UnprocessableEntityHttpException('Selecione ao menos um campo proposto e disponível para aprovação.');
    }

    $targetId = (int) $proposal->get('target')->target_id;
    $this->domainPurposes->runInPurpose('wiki', function () use ($proposal, $targetId, $approvedFields, $listModes, $proposedFields): void {
      $wikiDomain = $this->domainPurposes->getDomain('wiki');
      $storage = $this->entityTypeManager->getStorage('node');
      $lockedId = $this->database->select('node', 'node')
        ->fields('node', ['nid'])
        ->condition('nid', $targetId)
        ->forUpdate()
        ->execute()
        ->fetchField();
      $target = $lockedId ? $storage->loadUnchanged($targetId) : NULL;
      if (!$wikiDomain || !$target instanceof NodeInterface || $target->bundle() !== 'wiki_entry'
        || !$target->isDefaultRevision() || !$target->isPublished()
        || (int) $target->getRevisionId() !== (int) $proposal->get('target_revision_id')->value) {
        throw new ConflictHttpException('O verbete-base mudou ou não pode mais ser editado. Atualize a proposta antes de aceitar.');
      }
      if (!$target->hasField('field_domain_source')
        || $target->get('field_domain_source')->target_id !== $wikiDomain->id()
        || !$target->access('update', $this->currentUser)) {
        throw new AccessDeniedHttpException();
      }

      foreach ($approvedFields as $fieldName) {
        if (!isset(WikiProposal::EDITORIAL_FIELDS[$fieldName]) || !$target->hasField($fieldName)
          || !$target->get($fieldName)->access('edit', $this->currentUser)) {
          throw new AccessDeniedHttpException();
        }
      }
      $this->assertCanPublishWikiEntry($target);

      foreach ($approvedFields as $fieldName) {
        $value = $proposedFields[$fieldName];
        if ($fieldName === 'title') {
          $value = $value['value'];
        }
        elseif (in_array($fieldName, ['field_wiki_references', 'field_wiki_categories', 'field_wiki_related'], TRUE)) {
          $value = $this->mergeListField($target, $fieldName, $value, $listModes[$fieldName] ?? NULL, $wikiDomain);
        }
        elseif ($fieldName === 'field_wiki_image') {
          if ($value !== NULL) {
            $media = $this->entityTypeManager->getStorage('media')->load((int) $value);
            if (!$media || $media->bundle() !== 'image' || !$media->isPublished()
              || !$media->access('view', $this->currentUser)) {
              throw new AccessDeniedHttpException();
            }
          }
          $value = $value === NULL ? [] : ['target_id' => $value];
        }

        $target->set($fieldName, $value);
        if ($target->get($fieldName)->validate()->count() > 0) {
          throw new UnprocessableEntityHttpException('O valor aprovado para ' . WikiProposal::EDITORIAL_FIELDS[$fieldName] . ' não é válido.');
        }
      }

      // Validate each official Wiki transition in sequence. Core's moderation
      // field maps the final published state to a new default revision.
      $target->setNewRevision(TRUE);
      $this->validateTransition($target, 'published', 'draft');
      $target->set('moderation_state', 'draft');
      $this->validateTransition($target, 'draft', 'review');
      $target->set('moderation_state', 'review');
      $this->validateTransition($target, 'review', 'published');
      $target->set('moderation_state', 'published');
      $target->setRevisionUserId((int) $this->currentUser->id());
      $target->setRevisionLogMessage('Proposta da Wiki aceita; campos aprovados aplicados e publicados.');
      $target->save();
    });
  }

  /**
   * Checks the official transition path for proposal publication.
   */
  private function assertCanPublishWikiEntry(NodeInterface $target): void {
    if (!$this->currentUser->hasPermission('edit any wiki_entry content')) {
      throw new AccessDeniedHttpException();
    }
    $workflow = $this->moderationInformation->getWorkflowForEntity($target);
    if ($workflow === NULL) {
      throw new AccessDeniedHttpException();
    }
    $transitions = [
      ['published', 'draft'],
      ['draft', 'review'],
      ['review', 'published'],
    ];
    foreach ($transitions as [$from, $to]) {
      $this->validateTransition($target, $from, $to, $workflow);
    }
  }

  /**
   * Verifies one official moderation transition for the current editor.
   */
  private function validateTransition(NodeInterface $target, string $from, string $to, $workflow = NULL): void {
    $workflow ??= $this->moderationInformation->getWorkflowForEntity($target);
    if ($workflow === NULL) {
      throw new AccessDeniedHttpException();
    }
    $plugin = $workflow->getTypePlugin();
    if (!$plugin->hasState($from) || !$plugin->hasState($to)) {
      throw new AccessDeniedHttpException();
    }
    $fromState = $plugin->getState($from);
    $toState = $plugin->getState($to);
    try {
      $plugin->getTransitionFromStateToState($from, $to);
    }
    catch (\InvalidArgumentException) {
      throw new AccessDeniedHttpException();
    }
    if (!$this->transitionValidation->isTransitionValid($workflow, $fromState, $toState, $this->currentUser, $target)) {
      throw new AccessDeniedHttpException();
    }
  }

  /**
   * Merges or replaces list fields, with target revalidation and deduplication.
   *
   * @return array
   *   Core field item values for the final list.
   */
  private function mergeListField(NodeInterface $target, string $fieldName, mixed $proposed, ?string $mode, DomainInterface $wikiDomain): array {
    if (!in_array($mode, ['replace', 'append'], TRUE)) {
      throw new UnprocessableEntityHttpException('Escolha substituir ou acrescentar os valores de ' . WikiProposal::EDITORIAL_FIELDS[$fieldName] . '.');
    }
    $current = $target->get($fieldName)->getValue();
    $combined = $mode === 'append' ? array_merge($current, $proposed) : $proposed;
    $unique = [];
    foreach ($combined as $item) {
      $key = match ($fieldName) {
        'field_wiki_references' => (string) ($item['uri'] ?? ''),
        default => (string) (is_array($item) ? ($item['target_id'] ?? '') : $item),
      };
      if ($key === '' || isset($unique[$key])) {
        continue;
      }
      if ($fieldName === 'field_wiki_categories') {
        $entity = $this->entityTypeManager->getStorage('taxonomy_term')->load((int) $key);
        if (!$entity || $entity->bundle() !== 'wiki_category' || !$entity->access('view', $this->currentUser)) {
          throw new AccessDeniedHttpException();
        }
      }
      elseif ($fieldName === 'field_wiki_related') {
        $entity = $this->entityTypeManager->getStorage('node')->load((int) $key);
        if (!$entity instanceof NodeInterface || $entity->bundle() !== 'wiki_entry' || !$entity->isDefaultRevision()
          || !$entity->isPublished() || !$entity->hasField('field_domain_source')
          || $entity->get('field_domain_source')->target_id !== $wikiDomain->id()
          || !$entity->access('view', $this->currentUser)) {
          throw new AccessDeniedHttpException();
        }
      }
      $unique[$key] = in_array($fieldName, ['field_wiki_categories', 'field_wiki_related'], TRUE)
        ? ['target_id' => (int) $key]
        : $item;
    }
    return array_values($unique);
  }

}
