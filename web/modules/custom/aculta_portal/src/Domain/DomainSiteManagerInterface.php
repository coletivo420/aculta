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
   * Resolves a Drupal Domain ID to its functional site-key value.
   */
  public function getSiteKeyValueForDomainId(string $domainId): ?string;

  /**
   * Runs a callback with the selected site's Domain active.
   */
  public function runInSite(SiteKey|string $siteKey, callable $callback): mixed;

  /**
   * Loads the Domain associated with a site key.
   */
  public function getDomain(SiteKey|string $siteKey): ?DomainInterface;

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
  public function routeUrl(SiteKey|string $siteKey, string $routeName, array $parameters = []): ?Url;

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
  public function canonicalRouteUrl(SiteKey|string $siteKey, string $routeName, array $parameters = []): ?Url;

  /**
   * Builds an absolute path URL on the configured canonical domain.
   */
  public function canonicalPathUrl(SiteKey|string $siteKey, string $path): ?Url;

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
  public function environmentRouteUrl(SiteKey|string $siteKey, string $routeName, array $parameters = []): ?Url;

  /**
   * Builds an absolute path URL for the active deployment environment.
   */
  public function environmentPathUrl(SiteKey|string $siteKey, string $path): ?Url;

  /**
   * Builds an absolute path URL using the active Domain alias.
   */
  public function pathUrl(SiteKey|string $siteKey, string $path): ?Url;

}
