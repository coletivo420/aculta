<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

/**
 * Supplies validated rows to the shared daily avisos email.
 */
interface AvisoDigestProviderInterface {

  /**
   * Returns user IDs with pending digest entries.
   *
   * @return array<mixed>
   *   IDs dos destinatários pendentes.
   */
  public function pendingRecipientIds(): array;

  /**
   * Returns summary rows for one recipient, revalidating current access.
   *
   * Valid rows include a representative provider-owned `delivery_id`; grouped
   * rows may include the exact `delivery_ids` to acknowledge safely. A row
   * without a valid identity is never included in an email. If other valid
   * rows are delivered, the malformed row remains pending because it cannot
   * be acknowledged by ID. When no valid row can be included, the consumer
   * may discard the recipient's pending digest to prevent endless retries.
   *
   * @return array<int, array{tipo: string, titulo: string, quantidade: int, delivery_id?: int, delivery_ids?: list<int>}>
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
   * Discards all pending email entries for a recipient.
   */
  public function discardDigest(int $uid): void;

}
