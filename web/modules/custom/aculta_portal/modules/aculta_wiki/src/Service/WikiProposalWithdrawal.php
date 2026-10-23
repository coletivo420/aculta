<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Records an author's withdrawal as a private proposal revision.
 */
final class WikiProposalWithdrawal {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly Connection $database,
  ) {}

  /**
   * Withdraws a proposal that is still awaiting editorial resolution.
   *
   * @param \Drupal\aculta_wiki\Entity\WikiProposal $proposal
   *   Proposal displayed to its author.
   * @param int $expectedRevisionId
   *   Current proposal revision displayed by the confirmation form.
   *
   * @return \Drupal\aculta_wiki\Entity\WikiProposal
   *   The newly saved withdrawn revision.
   */
  public function withdraw(WikiProposal $proposal, int $expectedRevisionId): WikiProposal {
    $this->assertAuthor($proposal);

    $transaction = $this->database->startTransaction();
    try {
      $lockedId = $this->database->select('aculta_wiki_proposal', 'proposal')
        ->fields('proposal', ['id'])
        ->condition('id', $proposal->id())
        ->forUpdate()
        ->execute()
        ->fetchField();
      if (!$lockedId) {
        throw new ConflictHttpException('A proposta não está mais disponível para retirada.');
      }

      $current = $this->entityTypeManager->getStorage('aculta_wiki_proposal')->loadUnchanged($proposal->id());
      if (!$current instanceof WikiProposal || !$current->isDefaultRevision()) {
        throw new ConflictHttpException('A proposta não está mais disponível para retirada.');
      }
      $this->assertAuthor($current);
      if ((int) $current->getRevisionId() !== $expectedRevisionId
        || !in_array($current->get('state')->value, ['submitted', 'needs_changes'], TRUE)) {
        throw new ConflictHttpException('A proposta mudou desde que esta página foi aberta. Atualize antes de tentar novamente.');
      }

      $current->setNewRevision(TRUE);
      $current->isDefaultRevision(TRUE);
      $current->set('state', 'withdrawn');
      $current->set('decision_note', '');
      $current->setRevisionLogMessage('Proposta retirada pela pessoa autora');
      $current->save();
      unset($transaction);
      return $current;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Ensures the current account can withdraw this proposal.
   */
  private function assertAuthor(WikiProposal $proposal): void {
    if (!$this->currentUser->isAuthenticated()
      || (int) $this->currentUser->id() < 1
      || (int) $proposal->getOwnerId() !== (int) $this->currentUser->id()
      || !$proposal->access('view', $this->currentUser)) {
      throw new AccessDeniedHttpException();
    }
  }

}
