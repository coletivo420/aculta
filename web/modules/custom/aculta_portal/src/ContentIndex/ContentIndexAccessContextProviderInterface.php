<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\ContentIndex;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Optional provider contract for source-specific Entity Access contexts.
 *
 * Providers use this when access rules are evaluated in a canonical ACULTA
 * purpose such as `forum` or `wiki`. The Index still checks Entity Access for
 * the current account and fails closed when that purpose cannot be activated.
 */
interface ContentIndexAccessContextProviderInterface {

  /**
   * Returns the canonical access purpose for this source translation.
   *
   * Return NULL when normal active-request context is appropriate.
   */
  public function getAccessPurpose(ContentEntityInterface $entity): ?string;

}
