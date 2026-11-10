<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

use Drupal\Core\Url;
use Drupal\domain\DomainInterface;

/**
 * Site-key API that delegates to the existing, behavior-preserving resolver.
 */
final class DomainSiteManager implements DomainSiteManagerInterface {

  public function __construct(
    private readonly DomainPurposeManagerInterface $legacyResolver,
    private readonly SiteKeyRegistry $siteKeys,
  ) {}

  /**
   * Resolves the active site key from the existing domain resolver.
   */
  public function getCurrentSiteKey(): ?SiteKey {
    $value = $this->legacyResolver->getCurrentPurpose();
    return $value === NULL ? NULL : $this->siteKeys->fromValue($value);
  }

  /**
   * Resolves a site key from a Drupal Domain ID.
   */
  public function getSiteKeyForDomainId(string $domainId): ?SiteKey {
    $value = $this->legacyResolver->getPurposeForDomainId($domainId);
    return $value === NULL ? NULL : $this->siteKeys->fromValue($value);
  }

  /**
   * Runs a callback with the selected site's Domain active.
   */
  public function runInSite(SiteKey $siteKey, callable $callback): mixed {
    return $this->legacyResolver->runInPurpose($siteKey->value, $callback);
  }

  /**
   * Loads the Domain associated with a site key.
   */
  public function getDomain(SiteKey $siteKey): ?DomainInterface {
    return $this->legacyResolver->getDomain($siteKey->value);
  }

  /**
   * Builds an absolute route URL for a site key.
   */
  public function routeUrl(SiteKey $siteKey, string $routeName, array $parameters = []): ?Url {
    return $this->legacyResolver->routeUrl($siteKey->value, $routeName, $parameters);
  }

  /**
   * Builds an absolute canonical route URL.
   */
  public function canonicalRouteUrl(SiteKey $siteKey, string $routeName, array $parameters = []): ?Url {
    return $this->legacyResolver->canonicalRouteUrl($siteKey->value, $routeName, $parameters);
  }

  /**
   * Builds an absolute path URL on the configured canonical domain.
   */
  public function canonicalPathUrl(SiteKey $siteKey, string $path): ?Url {
    return $this->legacyResolver->canonicalPathUrl($siteKey->value, $path);
  }

  /**
   * Builds an absolute route URL for the active deployment environment.
   */
  public function environmentRouteUrl(SiteKey $siteKey, string $routeName, array $parameters = []): ?Url {
    return $this->legacyResolver->environmentRouteUrl($siteKey->value, $routeName, $parameters);
  }

  /**
   * Builds an absolute path URL for the active deployment environment.
   */
  public function environmentPathUrl(SiteKey $siteKey, string $path): ?Url {
    return $this->legacyResolver->environmentPathUrl($siteKey->value, $path);
  }

  /**
   * Builds an absolute path URL using the active Domain alias.
   */
  public function pathUrl(SiteKey $siteKey, string $path): ?Url {
    return $this->legacyResolver->pathUrl($siteKey->value, $path);
  }

}
