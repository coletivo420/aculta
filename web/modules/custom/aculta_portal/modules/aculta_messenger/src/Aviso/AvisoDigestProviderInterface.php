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
   */
  public function digestRowsFor(int $uid): array;

  /**
   * Marks the recipient's included digest entries as delivered.
   */
  public function markDigestSent(int $uid): void;

  /**
   * Discards pending email entries when the recipient has opted out.
   */
  public function discardDigest(int $uid): void;

}
