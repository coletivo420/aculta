<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Participation;

/**
 * Supplies aggregate published-participation evidence for one context.
 */
interface PublishedParticipationLookupInterface {

  /**
   * Returns the stable context identifier owned by this provider. */
  public function getContextId(): string;

  /**
   * Looks up published participation for an account in this context only.
   */
  public function lookup(int $accountId): PublishedParticipationEvidence;

}
