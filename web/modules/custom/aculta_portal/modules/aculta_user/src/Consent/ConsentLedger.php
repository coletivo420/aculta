<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Consent;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Path\PathAliasManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Append-only evidence of account agreements and optional consents. */
final class ConsentLedger {

  public function __construct(
    private readonly Connection $database,
    private readonly RequestStack $requestStack,
    private readonly TimeInterface $time,
    private readonly PrivateKey $privateKey,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PathAliasManagerInterface $pathAliasManager,
  ) {}

  /** Records a new event; document metadata must identify the accepted text. */
  public function record(int $uid, string $agreement, string $action, array $documents = [], bool $legacy = FALSE, ?int $created = NULL): int {
    if ($uid < 1 || !in_array($action, ['accepted', 'revoked'], TRUE)) {
      throw new \InvalidArgumentException('A valid account and consent action are required.');
    }
    if (!$legacy && $action === 'accepted' && $agreement === 'aculta_account_terms' && $documents === []) {
      $documents = $this->currentTermsEvidence();
    }
    $request = $this->requestStack->getCurrentRequest();
    $previous = $this->database->select('aculta_user_consent', 'c')
      ->fields('c', ['record_hash'])
      ->condition('uid', $uid)
      ->orderBy('id', 'DESC')
      ->range(0, 1)->execute()->fetchField() ?: '';
    $values = [
      'uid' => $uid,
      'agreement' => $agreement,
      'action' => $action,
      'created' => $created ?? $this->time->getRequestTime(),
      'ip_address' => $legacy || !$request ? NULL : $request->getClientIp(),
      'user_agent' => $legacy || !$request ? NULL : substr((string) $request->headers->get('User-Agent', ''), 0, 2048),
      'documents' => json_encode($documents, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'legacy' => (int) $legacy,
      'previous_hash' => $previous,
    ];
    $payload = json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $values['record_hash'] = hash_hmac('sha256', $payload, $this->privateKey->get());
    return (int) $this->database->insert('aculta_user_consent')->fields($values)->execute();
  }

  /** Captures stable revision references and content hashes for the current legal documents. */
  private function currentTermsEvidence(): array {
    $evidence = [];
    foreach (['/termos-de-uso', '/politica-de-privacidade'] as $alias) {
      $path = $this->pathAliasManager->getPathByAlias($alias);
      if (!preg_match('@^/node/(\d+)$@', $path, $matches)) {
        $evidence[] = ['alias' => $alias, 'status' => 'document_unresolved'];
        continue;
      }
      $node = $this->entityTypeManager->getStorage('node')->load((int) $matches[1]);
      if ($node === NULL || !$node->hasField('body')) {
        $evidence[] = ['alias' => $alias, 'status' => 'document_unresolved'];
        continue;
      }
      $body = (string) $node->get('body')->value;
      $evidence[] = [
        'alias' => $alias,
        'uuid' => (string) $node->uuid(),
        'revision_id' => (string) $node->getRevisionId(),
        'content_sha256' => hash('sha256', json_encode([
          'title' => (string) $node->label(),
          'body' => $body,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
      ];
    }
    return $evidence;
  }

  /** Returns whether the latest event for an agreement is an acceptance. */
  public function hasAccepted(int $uid, string $agreement): bool {
    $action = $this->database->select('aculta_user_consent', 'c')
      ->fields('c', ['action'])->condition('uid', $uid)->condition('agreement', $agreement)
      ->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchField();
    return $action === 'accepted';
  }

  /** Verifies the per-account chain and HMACs, returning FALSE on any alteration. */
  public function verify(int $uid): bool {
    $previous = '';
    foreach ($this->database->select('aculta_user_consent', 'c')->fields('c')->condition('uid', $uid)->orderBy('id')->execute() as $row) {
      $actual = (string) $row->record_hash;
      $record = [
        'uid' => (int) $row->uid,
        'agreement' => (string) $row->agreement,
        'action' => (string) $row->action,
        'created' => (int) $row->created,
        'ip_address' => $row->ip_address === NULL ? NULL : (string) $row->ip_address,
        'user_agent' => $row->user_agent === NULL ? NULL : (string) $row->user_agent,
        'documents' => (string) $row->documents,
        'legacy' => (int) $row->legacy,
        'previous_hash' => (string) $row->previous_hash,
      ];
      if (!hash_equals($previous, (string) $record['previous_hash'])) {
        return FALSE;
      }
      $expected = hash_hmac('sha256', json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $this->privateKey->get());
      if (!hash_equals((string) $actual, $expected)) {
        return FALSE;
      }
      $previous = (string) $actual;
    }
    return TRUE;
  }

}
