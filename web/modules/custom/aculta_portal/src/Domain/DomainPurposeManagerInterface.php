<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

use Drupal\Core\Url;
use Drupal\domain\DomainInterface;

/**
 * Temporary compatibility contract for the existing domain resolver.
 *
 * New consumers should depend on DomainSiteManager. This interface allows
 * that adapter to preserve the current resolution behavior during migration.
 */
interface DomainPurposeManagerInterface {

  /**
   * Returns the active functional key using the legacy value.
   */
  public function getCurrentPurpose(): ?string;

  /**
   * Resolves a Drupal Domain ID to its legacy functional key.
   */
  public function getPurposeForDomainId(string $domainId): ?string;

  /**
   * Runs a callback with the selected Domain active, then restores context.
   */
  public function runInPurpose(string $purpose, callable $callback): mixed;

  /**
   * Loads the Domain associated with the legacy functional key.
   */
  public function getDomain(string $purpose): ?DomainInterface;

  /**
   * Builds an absolute route URL for a legacy functional key.
   */
  public function routeUrl(string $purpose, string $routeName, array $parameters = []): ?Url;

  /**
   * Builds an absolute canonical route URL.
   */
  public function canonicalRouteUrl(string $purpose, string $routeName, array $parameters = []): ?Url;

  /**
   * Builds an absolute path URL on the configured canonical domain.
   */
  public function canonicalPathUrl(string $purpose, string $path): ?Url;

  /**
   * Builds an absolute route URL for the active deployment environment.
   */
  public function environmentRouteUrl(string $purpose, string $routeName, array $parameters = []): ?Url;

  /**
   * Builds an absolute path URL for the active deployment environment.
   */
  public function environmentPathUrl(string $purpose, string $path): ?Url;

  /**
   * Builds an absolute path URL using the active Domain alias.
   */
  public function pathUrl(string $purpose, string $path): ?Url;

}
