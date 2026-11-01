<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

/**
 * Optional exact-ID discard capability for digest providers.
 *
 * Providers can implement this capability to cancel selected pending email
 * records without discarding other eligible records or concurrent events.
 */
interface AvisoDigestSelectiveDiscardInterface {

  /**
   * Discards the selected provider-owned email records for one recipient.
   *
   * @param int $uid
   *   Recipient account ID.
   * @param int[] $deliveryIds
   *   Provider-owned delivery record IDs to discard.
   */
  public function discardDigestItems(int $uid, array $deliveryIds): void;

}
