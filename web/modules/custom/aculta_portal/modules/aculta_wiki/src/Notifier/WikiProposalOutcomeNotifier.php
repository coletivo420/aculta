<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Notifier;

use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\aculta_wiki\Type\WikiProposalOutcomeMailType;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\user\UserInterface;

/**
 * Queues private proposal outcomes for the confirmed contributor.
 */
final class WikiProposalOutcomeNotifier {

  use StringTranslationTrait;

  public function __construct(
    TranslationInterface $stringTranslation,
    private readonly EmailConfirmationPolicy $emailConfirmation,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly UuidInterface $uuid,
    private readonly ?object $queue = NULL,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Queues a minimal outcome notice when the author can be notified safely.
   *
   * @return bool
   *   Whether the notice was queued.
   */
  public function notify(WikiProposal $proposal): bool {
    if ($this->queue === NULL || !method_exists($this->queue, 'enqueue')) {
      return FALSE;
    }
    $owner = $proposal->getOwner();
    $state = (string) $proposal->get('state')->value;
    if (!$owner instanceof UserInterface
      || !$owner->isActive()
      || !$this->emailConfirmation->isConfirmed($owner)
      || !in_array($state, ['needs_changes', 'declined', 'accepted'], TRUE)
      || !filter_var((string) $owner->getEmail(), FILTER_VALIDATE_EMAIL)) {
      return FALSE;
    }

    $access = $proposal->access('view', $owner, TRUE);
    if (!$access->isAllowed()) {
      return FALSE;
    }
    $url = $this->domainPurposes->canonicalRouteUrl('wiki', 'aculta_wiki.proposal_status', [
      'proposal' => $proposal->id(),
    ]);
    if ($url === NULL) {
      return FALSE;
    }

    $stateLabel = match ($state) {
      'needs_changes' => $this->t('Precisa de ajustes'),
      'accepted' => $this->t('Aceita e publicada'),
      default => $this->t('Recusada'),
    };
    $this->queue->enqueue(new MailMessage(
      WikiProposalOutcomeMailType::ID,
      $owner->getEmail(),
      $owner->getPreferredLangcode(),
      [
        'subject' => (string) $proposal->label(),
        'state' => (string) $stateLabel,
        'decision_note' => (string) $proposal->get('decision_note')->value,
        'proposal_url' => $url->toString(),
      ],
      $this->uuid->generate(),
    ));
    return TRUE;
  }

}
