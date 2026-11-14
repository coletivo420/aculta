<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;


use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\aculta_portal\Environment\DeployerEnvironment;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves ACULTA sites to their configured domain entities.
 */
final class DomainSiteManager implements DomainSiteManagerInterface {

  /**
   * Canonical Domain IDs generated from production hosts.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainNegotiatorInterface $domainNegotiator,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly RequestStack $requestStack,
    private readonly SiteKeyRegistry $siteKeys = new SiteKeyRegistry(),
    private readonly ?string $appRoot = NULL,
  ) {}

  /**
   * Returns the current request.
   */
  private function getCurrentRequest(): ?Request {
    return $this->requestStack->getCurrentRequest();
  }

  /**
   * Returns the site key associated with the active Domain entity.
   */
  public function getCurrentSiteKey(): ?SiteKey {
    $domain = $this->domainNegotiator->getActiveDomain();
    return $domain instanceof DomainInterface ? $this->getSiteKeyForDomainId($domain->id()) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentDomainId(): ?string {
    $domain = $this->domainNegotiator->getActiveDomain();
    return $domain instanceof DomainInterface ? $domain->id() : NULL;
  }

  /**
   * Resolves a site key from a configured Domain ID.
   */
  public function getSiteKeyForDomainId(string $domainId): ?SiteKey {
    return $this->siteKeys->forDomainId($domainId);
  }

  /**
   * Resolves a Drupal Domain ID to its functional site-key value.
   */
  public function getSiteKeyValueForDomainId(string $domainId): ?string {
    return $this->getSiteKeyForDomainId($domainId)?->value;
  }

  /**
   * Executes a callback with the site's domain active, then restores it.
   *
   * Avalia acesso no site de origem, como verbetes da WIKI no sitemap.
   *
   * Não altera a regra de acesso; muda apenas o contexto da avaliação.
   */
  public function runInSite(SiteKey|string $siteKey, callable $callback): mixed {
    $siteKey = $this->resolveSiteKey($siteKey);
    if ($siteKey === NULL) {
      return NULL;
    }
    $domain = $this->getDomain($siteKey);
    if ($domain === NULL) {
      return NULL;
    }
    $previous = $this->domainNegotiator->getActiveDomain();
    $context = $this->domainNegotiator->getContext();
    $context->setDomain($domain);
    $context->setNegotiated(TRUE);
    try {
      return $callback();
    }
    finally {
      // Without a previous domain, restore negotiation to its initial state.
      if ($previous instanceof DomainInterface) {
        $context->setDomain($previous);
        $context->setNegotiated(TRUE);
      }
      else {
        $this->domainNegotiator->getActiveDomain(TRUE);
      }
    }
  }

  /**
   * Returns a domain entity for a site key.
   */
  public function getDomain(SiteKey|string $siteKey): ?DomainInterface {
    $siteKey = $this->resolveSiteKey($siteKey);
    if ($siteKey === NULL) {
      return NULL;
    }
    $id = $this->siteKeys->domainId($siteKey);
    $domain = $this->entityTypeManager->getStorage('domain')->load($id);
    return $domain instanceof DomainInterface ? $domain : NULL;
  }

  /**
   * Generates an absolute route URL using the active Domain Alias context.
   */
  public function routeUrl(SiteKey|string $siteKey, string $routeName, array $parameters = []): ?Url {
    $siteKey = $this->resolveSiteKey($siteKey);
    if ($siteKey === NULL) {
      return NULL;
    }
    $domain = $this->getDomain($siteKey);
    if (!$domain) {
      return NULL;
    }
    $activeDomain = $this->domainNegotiator->getActiveDomain();
    // Do not mutate the loaded entity when adapting a non-default alias URL.
    $domain = clone $domain;
    if ($activeDomain && isset($activeDomain->alias)
      && $activeDomain->alias->getEnvironment() !== 'default') {
      $request = $this->getCurrentRequest();
      if ($request) {
        $domain->set('scheme', $request->getScheme());
        $domain->setPath();
      }
    }
    return Url::fromRoute($routeName, $parameters, [
      'absolute' => TRUE,
      'domain' => $domain,
      'https' => $domain->isHttps(),
    ]);
  }

  /**
   * Generates an absolute route URL on the configured canonical domain.
   */
  public function canonicalRouteUrl(SiteKey|string $siteKey, string $routeName, array $parameters = []): ?Url {
    $siteKey = $this->resolveSiteKey($siteKey);
    if ($siteKey === NULL) {
      return NULL;
    }
    $domainId = $this->siteKeys->domainId($siteKey);
    $domain = $this->getDomain($siteKey);
    if (!$domain) {
      return NULL;
    }
    $config = $this->configFactory->get('domain.record.' . $domainId);
    $hostname = $config->get('hostname');
    if (!is_string($hostname) || $hostname === '') {
      return NULL;
    }
    // Domain Alias rewrites loaded Domain entities for the current environment.
    // Restore the configured hostname before creating production URLs.
    $domain = clone $domain;
    $domain->setHostname($hostname);
    $domain->set('scheme', $config->get('scheme') ?: 'https');
    $domain->setPath();
    return Url::fromRoute($routeName, $parameters, [
      'absolute' => TRUE,
      'domain' => $domain,
      'https' => $domain->isHttps(),
    ]);
  }

  /**
   * Generates an absolute path URL on the canonical domain.
   *
   * Uses the production hostname from the domain record, never an alias.
   */
  public function canonicalPathUrl(SiteKey|string $siteKey, string $path): ?Url {
    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
      return NULL;
    }
    $siteKey = $this->resolveSiteKey($siteKey);
    if ($siteKey === NULL) {
      return NULL;
    }
    $domainId = $this->siteKeys->domainId($siteKey);
    $domain = $this->getDomain($siteKey);
    if (!$domain) {
      return NULL;
    }
    $config = $this->configFactory->get('domain.record.' . $domainId);
    $hostname = $config->get('hostname');
    if (!is_string($hostname) || $hostname === '') {
      return NULL;
    }
    $domain = clone $domain;
    $domain->setHostname($hostname);
    $domain->set('scheme', $config->get('scheme') ?: 'https');
    $domain->setPath();
    return Url::fromUri(rtrim($domain->getPath(), '/') . $path);
  }

  /**
   * Generates an absolute route URL on the active Deployer environment's host.
   *
   * Use this for environment-specific artifacts such as test sitemaps. Public
   * production URLs continue to use canonicalRouteUrl().
   */
  public function environmentRouteUrl(SiteKey|string $siteKey, string $routeName, array $parameters = []): ?Url {
    $siteKey = $this->resolveSiteKey($siteKey);
    if ($siteKey === NULL) {
      return NULL;
    }
    $domainId = $this->siteKeys->domainId($siteKey);
    $domain = $this->getDomain($siteKey);
    $host = $this->deployerHost($domainId);
    if ($domain === NULL || $host === NULL) {
      return NULL;
    }
    $domain = clone $domain;
    $domain->setHostname($host);
    $domain->set('scheme', 'https');
    $domain->setPath();
    return Url::fromRoute($routeName, $parameters, [
      'absolute' => TRUE,
      'domain' => $domain,
      'https' => TRUE,
    ]);
  }

  /**
   * Generates an absolute path URL on the active Deployer environment's host.
   */
  public function environmentPathUrl(SiteKey|string $siteKey, string $path): ?Url {
    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
      return NULL;
    }
    $siteKey = $this->resolveSiteKey($siteKey);
    if ($siteKey === NULL) {
      return NULL;
    }
    $domainId = $this->siteKeys->domainId($siteKey);
    $host = $this->deployerHost($domainId);
    return $host === NULL ? NULL : Url::fromUri('https://' . $host . $path);
  }

  /**
   * Normalizes a typed or string site key.
   */
  private function resolveSiteKey(SiteKey|string $siteKey): ?SiteKey {
    return $siteKey instanceof SiteKey ? $siteKey : SiteKey::tryFrom($siteKey);
  }

  /**
   * Resolves a domain ID through the Deployer environment mapping.
   */
  private function deployerHost(string $domainId): ?string {
    $root = $this->appRoot ?? (defined('DRUPAL_ROOT') ? DRUPAL_ROOT : NULL);
    return is_string($root) && $root !== ''
      ? DeployerEnvironment::domainHost(dirname($root), $domainId)
      : NULL;
  }

  /**
   * Generates an absolute path URL using the current site alias.
   */
  public function pathUrl(SiteKey|string $siteKey, string $path): ?Url {
    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
      return NULL;
    }
    $siteKey = $this->resolveSiteKey($siteKey);
    if ($siteKey === NULL) {
      return NULL;
    }
    $domain = $this->getDomain($siteKey);
    if (!$domain) {
      return NULL;
    }
    $activeDomain = $this->domainNegotiator->getActiveDomain();
    // Do not mutate the loaded entity when adapting a non-default alias URL.
    $domain = clone $domain;
    if ($activeDomain && isset($activeDomain->alias)
      && $activeDomain->alias->getEnvironment() !== 'default') {
      $request = $this->getCurrentRequest();
      if ($request) {
        $domain->set('scheme', $request->getScheme());
        $domain->setPath();
      }
    }
    // fromUserInput() assembles an internal path against the active request
    // host and does not apply Domain's outbound path processor. Build the
    // absolute URI from the cloned site Domain so cross-domain paths keep
    // the requested site and alias environment.
    return Url::fromUri(rtrim($domain->getPath(), '/') . $path);
  }

}
