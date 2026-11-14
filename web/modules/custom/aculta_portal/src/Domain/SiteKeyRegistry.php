<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;


use Drupal\aculta_portal\Domain\SiteKey;
/**
 * Typed site-key access to the existing immutable domain and path mappings.
 */
final class SiteKeyRegistry {

  /**
   * Returns all supported functional site keys.
   *
   * @return list<\Drupal\aculta_portal\Domain\SiteKey>
   *   The canonical keys.
   */
  public function all(): array {
    return SiteKey::cases();
  }

  /**
   * Resolves a string to a supported site key.
   */
  public function fromValue(string $value): ?SiteKey {
    return SiteKey::tryFrom($value);
  }

  /**
   * Returns the existing Drupal Domain ID for a site key.
   */
  public function domainId(SiteKey $siteKey): string {
    return DomainSurfaceMap::DOMAIN_IDS[$siteKey->value];
  }

  /**
   * Resolves a site key from an existing Drupal Domain ID.
   */
  public function forDomainId(string $domainId): ?SiteKey {
    $siteKey = array_search($domainId, DomainSurfaceMap::DOMAIN_IDS, TRUE);
    return is_string($siteKey) ? SiteKey::tryFrom($siteKey) : NULL;
  }

  /**
   * Returns the existing internal front path, when one is configured.
   */
  public function frontPath(SiteKey $siteKey): ?string {
    return DomainSurfaceMap::FRONT_PATHS[$siteKey->value] ?? NULL;
  }

  /**
   * Returns the existing root-node path, when one is configured.
   */
  public function rootNodePath(SiteKey $siteKey): ?string {
    return DomainSurfaceMap::NO_DA_RAIZ[$siteKey->value] ?? NULL;
  }

  /**
   * Returns the existing closed legacy paths for a site key.
   *
   * @return list<string>
   *   Internal legacy paths that remain closed.
   */
  public function closedLegacyPaths(SiteKey $siteKey): array {
    return DomainSurfaceMap::CAMINHOS_LEGADOS[$siteKey->value] ?? [];
  }

}
