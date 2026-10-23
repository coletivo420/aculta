<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Records editorial feedback on a private Wiki proposal.
 */
final class WikiProposalDecision {

  /**
   * Decisions that do not apply content to a public Node.
   *
   * Acceptance is intentionally unavailable until its application contract is
   * defined: a proposal must never silently become public content.
   *
   * @var list<string>
   */
  public const DECISIONS = ['needs_changes', 'declined'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly Connection $database,
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
  public function decide(WikiProposal $proposal, int $expectedRevisionId, string $decision, string $note): WikiProposal {
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

      $current->setNewRevision(TRUE);
      $current->isDefaultRevision(TRUE);
      $current->set('state', $decision);
      $current->set('decision_note', $note);
      $current->setRevisionLogMessage($decision === 'needs_changes' ? 'Proposta devolvida para ajustes' : 'Proposta recusada');
      $current->save();
      unset($transaction);

      return $current;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

}
