<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

/**
 * Supplies validated rows to the shared daily avisos email.
 */
interface AvisoDigestProviderInterface {

  /**
   * Returns user IDs with pending digest entries.
   */
  public function pendingRecipientIds(): array;

  /**
   * Returns summary rows for one recipient, revalidating current access.
   *
   * Each row includes a representative provider-owned `delivery_id`; grouped
   * rows may include the exact `delivery_ids` to acknowledge safely.
   *
   * @return array<int, array{tipo: string, titulo: string, quantidade: int, delivery_id: int, delivery_ids?: list<int>}>
   *   Digest rows with their provider-owned delivery IDs.
   */
  public function digestRowsFor(int $uid): array;

  /**
   * Marks only the included digest entries as delivered.
   *
   * @param int $uid
   *   Recipient account ID.
   * @param int[] $deliveryIds
   *   All IDs represented by digestRowsFor() rows for this recipient.
   */
  public function markDigestSent(int $uid, array $deliveryIds): void;

  /**
   * Discards pending email entries when the recipient has opted out.
   */
  public function discardDigest(int $uid): void;

}
