<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Lms;

use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_messenger\Aviso\PaginatedPersonalNoticeAdapterInterface;
use Drupal\message\MessageInterface;

/**
 * Contract for LMS avisos backed by ACULTA Messenger.
 */
interface LearningNoticeManagerInterface extends PaginatedPersonalNoticeAdapterInterface {

  /**
   * Records a notice once per source and recipient.
   */
  public function record(string $sourceUuid, string $type, int $uid, bool $sendEmail, string $eventVersion = '1', bool $includeInDigest = TRUE): ?MessageInterface;

  /**
   * Resolves the current target only when access is still valid.
   */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array;

  /**
   * Sets the read state after revalidating ownership and LMS access.
   */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool;

  /**
   * Returns user IDs with pending digest entries.
   *
   * @return int[]
   *   User IDs.
   */
  public function pendingRecipientIds(): array;

  /**
   * Returns validated daily digest rows.
   *
   * @return array<int, array{tipo: string, titulo: string, quantidade: int, delivery_id: int}>
   *   Rows ready for the shared email.
   */
  public function digestRowsFor(int $uid): array;

  /**
   * Marks only included digest entries as delivered.
   *
   * @param int $uid
   *   Recipient account ID.
   * @param int[] $deliveryIds
   *   Message IDs returned by digestRowsFor() for this recipient.
   */
  public function markDigestSent(int $uid, array $deliveryIds): void;

  /**
   * Discards pending email entries after opt-out.
   */
  public function discardDigest(int $uid): void;

}
