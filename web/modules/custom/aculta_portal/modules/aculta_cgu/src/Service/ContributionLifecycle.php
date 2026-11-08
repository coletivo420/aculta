<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Service;

use Drupal\aculta_cgu\Adapter\ContributionAdapterRegistry;
use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Coordinates private proposal submission and review. */
final class ContributionLifecycle {

  private const FLOOD_LIMIT = 10;
  private const FLOOD_WINDOW = 3600;
  private const FLOOD_EVENT = 'aculta_contributions.submit';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly ContributionAdapterRegistry $adapters,
    private readonly ContributionStateMachine $stateMachine,
    private readonly FloodInterface $flood,
    private readonly LockBackendInterface $lock,
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Submits an idempotent private proposal without changing its source entity.
   *
   * @param string $source
   *   Registered domain adapter ID.
   * @param string $intent
   *   Adapter-defined create or update intent.
   * @param string|null $targetUuid
   *   Target UUID, resolved only by the registered adapter.
   * @param string $subject
   *   Private proposal summary.
   * @param string $rationale
   *   Proponent's rationale.
   * @param array<string, mixed> $payload
   *   Proposed values validated and normalized by the adapter.
   * @param string $token
   *   Random idempotency token for this submission.
   * @param array<string, mixed> $sourceData
   *   Adapter-validated private context kept separate from applicable values.
   * @param string|null $expectedBaseVersion
   *   Optional source version captured when the submission form was opened.
   */
  public function submit(string $source, string $intent, ?string $targetUuid, string $subject, string $rationale, array $payload, string $token, array $sourceData = [], ?string $expectedBaseVersion = NULL): Contribution {
    if (!$this->currentUser->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    $subject = trim($subject);
    $rationale = trim($rationale);
    if ($subject === '' || mb_strlen($subject) > 255 || $rationale === '' || mb_strlen($rationale) > 5000) {
      throw new UnprocessableEntityHttpException('Proposal summary or rationale is invalid.');
    }
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
      throw new UnprocessableEntityHttpException('Invalid submission token.');
    }

    $adapter = $this->adapters->get($source);
    $target = $adapter->loadTarget($intent, $targetUuid);
    if (!$adapter->submitAccess($this->currentUser, $intent, $target)) {
      throw new AccessDeniedHttpException();
    }
    $payload = $adapter->validatePayload($intent, $payload, $target);
    $sourceData = $adapter->validateSourceData($intent, $sourceData);
    $baseVersion = $adapter->currentVersion($intent, $target);
    if ($expectedBaseVersion !== NULL && !hash_equals($baseVersion, $expectedBaseVersion)) {
      throw new ConflictHttpException('The source changed while the proposal was being prepared.');
    }
    $moduleId = $adapter->moduleId();
    $purpose = $adapter->purpose($target);
    if ($baseVersion === '' || strlen($baseVersion) > 255
      || !preg_match('/^[a-z][a-z0-9_]*$/D', $moduleId)
      || !preg_match('/^[a-z][a-z0-9_]*$/D', $purpose)) {
      throw new \LogicException('A contribution adapter returned invalid source metadata.');
    }

    $accountId = (int) $this->currentUser->id();
    $idempotencyHash = hash('sha256', $accountId . ':' . $token);
    $lockName = 'aculta_contributions.submit:' . $idempotencyHash;
    $this->acquire($lockName);
    try {
      $storage = $this->entityTypeManager->getStorage('aculta_contribution');
      $matches = $storage->getQuery()->accessCheck(FALSE)
        ->condition('uid', $accountId)
        ->condition('idempotency_hash', $idempotencyHash)
        ->range(0, 1)->execute();
      if ($matches) {
        $existing = $storage->load((int) reset($matches));
        if ($existing instanceof Contribution) {
          return $existing;
        }
      }

      $settings = $this->configFactory->get('aculta_cgu.settings');
      if ($settings->isNew()) {
        $settings = $this->configFactory->get('aculta_contributions.settings');
      }
      $floodLimit = max(1, (int) ($settings->get('flood_limit') ?? self::FLOOD_LIMIT));
      $floodWindow = max(1, (int) ($settings->get('flood_window') ?? self::FLOOD_WINDOW));
      if (!$this->flood->isAllowed(self::FLOOD_EVENT, $floodLimit, $floodWindow, (string) $accountId)) {
        throw new TooManyRequestsHttpException($floodWindow, 'The temporary proposal limit has been reached.');
      }

      $proposal = $storage->create([
        'uid' => $accountId,
        'subject' => $subject,
        'source' => $source,
        'source_module' => $moduleId,
        'purpose' => $purpose,
        'intent' => $intent,
        'target_type' => $target?->getEntityTypeId(),
        'target_uuid' => $target?->uuid(),
        'base_revision' => $baseVersion,
        'payload' => $payload,
        'source_data' => $sourceData,
        'rationale' => $rationale,
        'state' => 'submitted',
        'idempotency_hash' => $idempotencyHash,
        'revision_log' => 'Proposal submitted',
      ]);
      if (!$proposal instanceof Contribution) {
        throw new \LogicException('Contribution storage returned an unexpected entity.');
      }
      $proposal->setNewRevision(TRUE);
      $proposal->isDefaultRevision(TRUE);
      $transaction = $this->database->startTransaction();
      try {
        $proposal->save();
        $this->flood->register(self::FLOOD_EVENT, $floodWindow, (string) $accountId);
        $transaction->commitOrRelease();
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
      return $proposal;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Records a reviewer decision and optionally applies selected payload keys.
   *
   * @param \Drupal\aculta_cgu\Entity\Contribution $proposal
   *   Proposal to decide.
   * @param string $outcome
   *   Accepted, needs_changes, or declined.
   * @param string $note
   *   Required plain-text review note.
   * @param list<string> $selectedFields
   *   Payload keys selected for application when accepting.
   * @param int $expectedRevision
   *   Revision ID reviewed by the caller.
   * @param array<string, mixed> $decisionData
   *   Adapter-specific application choices (never unvalidated target fields).
   */
  public function decide(Contribution $proposal, string $outcome, string $note, array $selectedFields, int $expectedRevision, array $decisionData = []): Contribution {
    if (!$this->currentUser->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    if (!in_array($outcome, ['accepted', 'needs_changes', 'declined'], TRUE)) {
      throw new UnprocessableEntityHttpException('Unsupported reviewer decision.');
    }
    $note = trim(strip_tags($note));
    if ($note === '' || mb_strlen($note) > 5000) {
      throw new UnprocessableEntityHttpException('A decision note is required.');
    }

    $id = (int) $proposal->id();
    $storage = $this->entityTypeManager->getStorage('aculta_contribution');
    $proposal = $storage->load($id);
    if (!$proposal instanceof Contribution || (int) $proposal->getRevisionId() !== $expectedRevision) {
      throw new ConflictHttpException('The proposal changed while it was being reviewed.');
    }
    $adapter = $this->adapters->get((string) $proposal->get('source')->value);
    $intent = (string) $proposal->get('intent')->value;
    if ($outcome !== 'accepted' && $decisionData !== []) {
      throw new UnprocessableEntityHttpException('Only accepted decisions may include application choices.');
    }
    $decisionData = $adapter->validateDecisionData($intent, $selectedFields, $decisionData);
    $targetUuid = $proposal->get('target_uuid')->value;
    $target = $adapter->loadTarget($intent, $targetUuid ? (string) $targetUuid : NULL);
    if (!$adapter->reviewAccess($this->currentUser, $intent, $target)) {
      throw new AccessDeniedHttpException();
    }

    $lockName = 'aculta_contributions.proposal:' . $id;
    $this->acquire($lockName);
    $targetLock = 'aculta_contributions.target:' . hash('sha256', $proposal->get('source')->value . ':' . ($targetUuid ?: $proposal->uuid()));
    try {
      $this->acquire($targetLock);
    }
    catch (\Throwable $exception) {
      $this->lock->release($lockName);
      throw $exception;
    }
    try {
      $current = $storage->load($id);
      if (!$current instanceof Contribution || (int) $current->getRevisionId() !== $expectedRevision) {
        throw new ConflictHttpException('The proposal changed while it was being reviewed.');
      }
      if (!$this->stateMachine->canDecide((string) $current->get('state')->value, $outcome)) {
        throw new ConflictHttpException('This proposal is already in a final state.');
      }
      $target = $adapter->loadTarget($intent, $targetUuid ? (string) $targetUuid : NULL);
      if (!$adapter->reviewAccess($this->currentUser, $intent, $target)) {
        throw new AccessDeniedHttpException();
      }

      $transaction = $this->database->startTransaction();
      $sourceConflict = FALSE;
      try {
        if ($outcome === 'accepted') {
          $sourceConflict = !$this->applySelectedFields($current, $selectedFields, $decisionData);
        }
        elseif ($selectedFields !== []) {
          throw new UnprocessableEntityHttpException('Only accepted decisions may apply proposal fields.');
        }
        $current->set('state', $sourceConflict ? 'conflict' : $outcome);
        $current->set('decision_note', $sourceConflict ? 'The source changed since this proposal was submitted.' : $note);
        $current->set('decision_data', $sourceConflict ? [] : [
          'selected_fields' => array_values(array_unique($selectedFields)),
          'adapter_data' => $outcome === 'accepted' ? $decisionData : [],
        ]);
        $current->setNewRevision(TRUE);
        $current->isDefaultRevision(TRUE);
        $current->setRevisionLogMessage($sourceConflict ? 'Source revision conflict' : 'Proposal decision: ' . $outcome);
        $current->save();
        $transaction->commitOrRelease();
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
      if ($sourceConflict) {
        throw new ConflictHttpException('The source changed since this proposal was submitted.');
      }
      try {
        $adapter->recordDecision($current, $outcome);
      }
      catch (\Throwable $exception) {
        // The reviewed source change and proposal decision are already
        // committed. Downstream notice delivery must never roll them back.
        $this->logger->error('A contribution decision notice could not be queued.');
      }
      return $current;
    }
    finally {
      $this->lock->release($targetLock);
      $this->lock->release($lockName);
    }
  }

  /**
   * Revises a proposal returned for changes and resubmits it for review.
   *
   * @param \Drupal\aculta_cgu\Entity\Contribution $proposal
   *   Proposal to revise.
   * @param string $subject
   *   Revised private summary.
   * @param string $rationale
   *   Revised rationale.
   * @param array<string, mixed> $payload
   *   Updated values validated by the registered domain adapter.
   * @param int $expectedRevision
   *   Revision ID the author reviewed.
   * @param array<string, mixed> $sourceData
   *   Adapter-validated private context kept separate from applicable values.
   */
  public function revise(Contribution $proposal, string $subject, string $rationale, array $payload, int $expectedRevision, array $sourceData = []): Contribution {
    if (!$this->currentUser->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    $proposal = $this->entityTypeManager->getStorage('aculta_contribution')->load($proposal->id());
    if (!$proposal instanceof Contribution || (int) $proposal->getRevisionId() !== $expectedRevision
      || (int) $proposal->getOwnerId() !== (int) $this->currentUser->id()) {
      throw new AccessDeniedHttpException();
    }
    $subject = trim($subject);
    $rationale = trim($rationale);
    if ($subject === '' || mb_strlen($subject) > 255 || $rationale === '' || mb_strlen($rationale) > 5000) {
      throw new UnprocessableEntityHttpException('Proposal summary or rationale is invalid.');
    }
    $adapter = $this->adapters->get((string) $proposal->get('source')->value);
    $intent = (string) $proposal->get('intent')->value;
    $targetUuid = $proposal->get('target_uuid')->value;
    $target = $adapter->loadTarget($intent, $targetUuid ? (string) $targetUuid : NULL);
    if (!$adapter->submitAccess($this->currentUser, $intent, $target)
      || !$adapter->entityAccess($this->currentUser, $proposal, 'update')->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $payload = $adapter->validatePayload($intent, $payload, $target);
    $sourceData = $adapter->validateSourceData($intent, $sourceData);
    $lockName = 'aculta_contributions.proposal:' . (int) $proposal->id();
    $this->acquire($lockName);
    try {
      $current = $this->entityTypeManager->getStorage('aculta_contribution')->load($proposal->id());
      if (!$current instanceof Contribution || (int) $current->getRevisionId() !== $expectedRevision
        || (int) $current->getOwnerId() !== (int) $this->currentUser->id()
        || $current->get('state')->value !== 'needs_changes') {
        throw new ConflictHttpException('The proposal can no longer be revised in its current state.');
      }
      $target = $adapter->loadTarget($intent, $targetUuid ? (string) $targetUuid : NULL);
      if (!$adapter->submitAccess($this->currentUser, $intent, $target)
        || !$adapter->entityAccess($this->currentUser, $current, 'update')->isAllowed()) {
        throw new AccessDeniedHttpException();
      }
      if (!hash_equals((string) $current->get('base_revision')->value, $adapter->currentVersion($intent, $target))) {
        $current->set('state', 'conflict');
        $current->set('decision_note', 'The source changed since this proposal was submitted.');
        $current->setNewRevision(TRUE);
        $current->isDefaultRevision(TRUE);
        $current->setRevisionLogMessage('Source revision conflict during proposal revision');
        $current->save();
        throw new ConflictHttpException('The source changed since this proposal was submitted.');
      }
      $current->set('subject', $subject);
      $current->set('rationale', $rationale);
      $current->set('payload', $payload);
      $current->set('source_data', $sourceData);
      $current->set('state', 'submitted');
      $current->set('decision_note', NULL);
      $current->set('decision_data', []);
      $current->setNewRevision(TRUE);
      $current->isDefaultRevision(TRUE);
      $current->setRevisionLogMessage('Proponent revised proposal');
      $current->save();
      return $current;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Withdraws an author's active proposal as a new immutable revision. */
  public function withdraw(Contribution $proposal, int $expectedRevision): Contribution {
    if (!$this->currentUser->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    $proposal = $this->entityTypeManager->getStorage('aculta_contribution')->load($proposal->id());
    if (!$proposal instanceof Contribution || (int) $proposal->getRevisionId() !== $expectedRevision
      || (int) $proposal->getOwnerId() !== (int) $this->currentUser->id()) {
      throw new AccessDeniedHttpException();
    }
    $adapter = $this->adapters->get((string) $proposal->get('source')->value);
    if (!$adapter->entityAccess($this->currentUser, $proposal, 'view')->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $lockName = 'aculta_contributions.proposal:' . (int) $proposal->id();
    $this->acquire($lockName);
    try {
      $current = $this->entityTypeManager->getStorage('aculta_contribution')->load($proposal->id());
      if (!$current instanceof Contribution || (int) $current->getRevisionId() !== $expectedRevision
        || (int) $current->getOwnerId() !== (int) $this->currentUser->id()
        || !$this->stateMachine->canWithdraw((string) $current->get('state')->value)) {
        throw new ConflictHttpException('The proposal can no longer be withdrawn in its current state.');
      }
      if (!$adapter->entityAccess($this->currentUser, $current, 'view')->isAllowed()) {
        throw new AccessDeniedHttpException();
      }
      $current->set('state', 'withdrawn');
      $current->set('decision_note', NULL);
      $current->setNewRevision(TRUE);
      $current->isDefaultRevision(TRUE);
      $current->setRevisionLogMessage('Proponent withdrew proposal');
      $current->save();
      return $current;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Applies only submitted keys selected by the reviewer. */
  private function applySelectedFields(Contribution $proposal, array $selectedFields, array $decisionData): bool {
    $payload = $proposal->getPayload();
    foreach ($selectedFields as $field) {
      if (!is_string($field) || !array_key_exists($field, $payload)) {
        throw new UnprocessableEntityHttpException('A selected proposal field is unavailable.');
      }
    }
    $selectedFields = array_values(array_unique($selectedFields));
    if ($selectedFields === []) {
      throw new UnprocessableEntityHttpException('Select at least one proposal field to accept.');
    }
    $adapter = $this->adapters->get((string) $proposal->get('source')->value);
    $intent = (string) $proposal->get('intent')->value;
    $targetUuid = $proposal->get('target_uuid')->value;
    $target = $adapter->loadTarget($intent, $targetUuid ? (string) $targetUuid : NULL);
    if (!$adapter->applyAccess($this->currentUser, $intent, $target)) {
      throw new AccessDeniedHttpException('The domain policy no longer allows applying this proposal.');
    }
    $actualVersion = $adapter->currentVersion($intent, $target);
    if (!hash_equals((string) $proposal->get('base_revision')->value, $actualVersion)) {
      return FALSE;
    }
    $selected = array_intersect_key($payload, array_fill_keys($selectedFields, TRUE));
    if (method_exists($adapter, 'applyContribution')) {
      $adapter->applyContribution(
        $proposal,
        $intent,
        $target,
        (string) $proposal->get('base_revision')->value,
        $selected,
        $this->currentUser,
        $decisionData,
      );
    }
    else {
      $adapter->apply($intent, $target, (string) $proposal->get('base_revision')->value, $selected, $this->currentUser, $decisionData);
    }
    return TRUE;
  }

  /**
   * Obtains a lock with a bounded retry.
   *
   * @param string $name
   *   Lock name.
   */
  private function acquire(string $name): void {
    if (!$this->lock->acquire($name, 30.0)) {
      $this->lock->wait($name, 5);
      if (!$this->lock->acquire($name, 30.0)) {
        throw new TooManyRequestsHttpException(5, 'This proposal is currently being processed.');
      }
    }
  }

}
