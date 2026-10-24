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
   * Each returned row includes its provider-owned `delivery_id`, which is used
   * to acknowledge only the entries included in the successfully sent email.
   *
   * @return array<int, array{tipo: string, titulo: string, quantidade: int, delivery_id: int}>
   *   Digest rows with their provider-owned delivery IDs.
   */
  public function digestRowsFor(int $uid): array;

  /**
   * Marks only the included digest entries as delivered.
   *
   * @param int $uid
   *   Recipient account ID.
   * @param int[] $deliveryIds
   *   IDs returned by digestRowsFor() for this recipient.
   */
  public function markDigestSent(int $uid, array $deliveryIds): void;

  /**
   * Discards pending email entries when the recipient has opted out.
   */
  public function discardDigest(int $uid): void;

}
