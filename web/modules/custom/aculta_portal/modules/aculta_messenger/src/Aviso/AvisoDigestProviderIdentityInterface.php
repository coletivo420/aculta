<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

/**
 * Adds a stable, unique source identity to a digest provider.
 *
 * Providers that implement this interface keep their persisted digest
 * identity across PHP class and service refactors. Existing providers may
 * continue implementing only AvisoDigestProviderInterface.
 */
interface AvisoDigestProviderIdentityInterface extends AvisoDigestProviderInterface {

  /**
   * Returns the stable identifier used in persisted digest manifests.
   *
   * Use a lowercase module/source key such as `lms` or `wiki`. The identifier
   * must be unique among every digest provider enabled on the site; providers
   * with duplicate identities are excluded from delivery and acknowledgement.
   */
  public function digestProviderId(): string;

}
