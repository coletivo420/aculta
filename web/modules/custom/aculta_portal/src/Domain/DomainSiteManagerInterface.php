<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

use Drupal\Core\Url;
use Drupal\domain\DomainInterface;

/**
 * Defines the typed functional site-key API for the ACULTA Portal.
 */
interface DomainSiteManagerInterface {

  /**
   * Resolves the active site key.
   */
  public function getCurrentSiteKey(): ?SiteKey;

  /**
   * Resolves the active Drupal Domain ID.
   */
  public function getCurrentDomainId(): ?string;

  /**
   * Resolves a site key from a Drupal Domain ID.
   */
  public function getSiteKeyForDomainId(string $domainId): ?SiteKey;

  /**
   * Runs a callback with the selected site's Domain active.
   */
  public function runInSite(SiteKey $siteKey, callable $callback): mixed;

  /**
   * Loads the Domain associated with a site key.
   */
  public function getDomain(SiteKey $siteKey): ?DomainInterface;

  /**
   * Builds an absolute route URL for a site key.
   *
   * @param \Drupal\aculta_portal\Domain\SiteKey $siteKey
   *   Functional site key.
   * @param string $routeName
   *   Route name.
   * @param array<string, mixed> $parameters
   *   Route parameters.
   */
  public function routeUrl(SiteKey $siteKey, string $routeName, array $parameters = []): ?Url;

  /**
   * Builds an absolute canonical route URL.
   *
   * @param \Drupal\aculta_portal\Domain\SiteKey $siteKey
   *   Functional site key.
   * @param string $routeName
   *   Route name.
   * @param array<string, mixed> $parameters
   *   Route parameters.
   */
  public function canonicalRouteUrl(SiteKey $siteKey, string $routeName, array $parameters = []): ?Url;

  /**
   * Builds an absolute path URL on the configured canonical domain.
   */
  public function canonicalPathUrl(SiteKey $siteKey, string $path): ?Url;

  /**
   * Builds an absolute route URL for the active deployment environment.
   *
   * @param \Drupal\aculta_portal\Domain\SiteKey $siteKey
   *   Functional site key.
   * @param string $routeName
   *   Route name.
   * @param array<string, mixed> $parameters
   *   Route parameters.
   */
  public function environmentRouteUrl(SiteKey $siteKey, string $routeName, array $parameters = []): ?Url;

  /**
   * Builds an absolute path URL for the active deployment environment.
   */
  public function environmentPathUrl(SiteKey $siteKey, string $path): ?Url;

  /**
   * Builds an absolute path URL using the active Domain alias.
   */
  public function pathUrl(SiteKey $siteKey, string $path): ?Url;

}
