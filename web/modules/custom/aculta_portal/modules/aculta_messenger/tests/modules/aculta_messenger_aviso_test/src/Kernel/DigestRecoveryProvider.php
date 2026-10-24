<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger_aviso_test\Kernel;

use Drupal\Core\Database\Connection;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderIdentityInterface;

/**
 * Shared digest source fixture for cross-process recovery Kernel tests.
 */
final class DigestRecoveryProvider implements AvisoDigestProviderIdentityInterface {

  /**
   * Test-only acknowledgement table.
   */
  public const TABLE = 'aculta_messenger_mailer_digest_test';

  public function __construct(
    private readonly int $uid,
    private readonly Connection $database,
    private bool $failFirstAcknowledgement = FALSE,
    private readonly bool $forbidDigestRows = FALSE,
  ) {
    if (!$this->database->schema()->tableExists(self::TABLE)) {
      $this->database->schema()->createTable(self::TABLE, [
        'fields' => [
          'id' => ['type' => 'int', 'not null' => TRUE],
          'acknowledged' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        ],
        'primary key' => ['id'],
      ]);
    }
    $exists = $this->database->select(self::TABLE, 'digest')
      ->condition('id', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
    if ((int) $exists === 0) {
      $this->database->insert(self::TABLE)
        ->fields(['id' => 1, 'acknowledged' => 0])
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function digestProviderId(): string {
    return 'kernel_recovery';
  }

  /**
   * {@inheritdoc}
   */
  public function pendingRecipientIds(): array {
    return [$this->uid];
  }

  /**
   * {@inheritdoc}
   */
  public function digestRowsFor(int $uid): array {
    if ($this->forbidDigestRows) {
      throw new \RuntimeException('The recovery worker must use the persisted manifest.');
    }
    if ($uid !== $this->uid) {
      return [];
    }
    return [
      [
        'tipo' => 'wiki',
        'titulo' => 'Revisão publicada',
        'quantidade' => 1,
        'delivery_id' => 501,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function markDigestSent(int $uid, array $deliveryIds): void {
    $this->database->update(self::TABLE)
      ->fields(['acknowledged' => 1])
      ->condition('id', 1)
      ->execute();
    if ($this->failFirstAcknowledgement) {
      $this->failFirstAcknowledgement = FALSE;
      throw new \RuntimeException('Synthetic digest acknowledgement failure.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function discardDigest(int $uid): void {}

}
