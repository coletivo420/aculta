<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\aculta_portal\Environment\DeployerEnvironment;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves ACULTA purposes to their configured domain entities.
 */
final class DomainPurposeManager {

  /**
   * Canonical Domain IDs generated from production hosts.
   */
  private const DOMAIN_IDS = [
    'main' => 'aculta_org',
    'account' => 'conta_aculta_org',
    'support' => 'apoio_aculta_org',
    'magazine' => 'revista420_aculta_org',
    'wiki' => 'wiki420_aculta_org',
    'shop' => 'loja_aculta_org',
    'courses' => 'cursos_aculta_org',
    'forum' => 'forum_aculta_org',
    'links' => 'links_aculta_org',
    'eventos' => 'eventos_aculta_org',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainNegotiatorInterface $domainNegotiator,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly RequestStack $requestStack,
    private readonly ?string $appRoot = NULL,
  ) {}

  /**
   * Returns the current request.
   */
  private function getCurrentRequest(): ?Request {
    return $this->requestStack->getCurrentRequest();
  }

  /**
   * Returns the purpose associated with the active domain entity.
   */
  public function getCurrentPurpose(): ?string {
    $domain = $this->domainNegotiator->getActiveDomain();
    return $domain instanceof DomainInterface ? $this->getPurposeForDomainId($domain->id()) : NULL;
  }

  /**
   * Returns the stable purpose for a configured Domain ID.
   */
  public function getPurposeForDomainId(string $domainId): ?string {
    $purpose = array_search($domainId, self::DOMAIN_IDS, TRUE);
    return $purpose === FALSE ? NULL : $purpose;
  }

  /**
   * Executes a callback with the purpose's domain active, then restores it.
   *
   * Avalia acesso no purpose de origem, como verbetes da WIKI no sitemap.
   *
   * Não altera a regra de acesso; muda apenas o contexto da avaliação.
   */
  public function runInPurpose(string $purpose, callable $callback): mixed {
    $domain = $this->getDomain($purpose);
    if ($domain === NULL) {
      return NULL;
    }
    $previous = $this->domainNegotiator->getActiveDomain();
    $this->domainNegotiator->setActiveDomain($domain);
    try {
      return $callback();
    }
    finally {
      // Without a previous domain, restore negotiation to its initial state.
      if ($previous instanceof DomainInterface) {
        $this->domainNegotiator->setActiveDomain($previous);
      }
      else {
        $this->domainNegotiator->getActiveDomain(TRUE);
      }
    }
  }

  /**
   * Returns a domain entity for a stable purpose.
   */
  public function getDomain(string $purpose): ?DomainInterface {
    $id = self::DOMAIN_IDS[$purpose] ?? NULL;
    if ($id === NULL) {
      return NULL;
    }
    $domain = $this->entityTypeManager->getStorage('domain')->load($id);
    return $domain instanceof DomainInterface ? $domain : NULL;
  }

  /**
   * Generates an absolute route URL using the active Domain Alias context.
   */
  public function routeUrl(string $purpose, string $routeName, array $parameters = []): ?Url {
    $domain = $this->getDomain($purpose);
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
  public function canonicalRouteUrl(string $purpose, string $routeName, array $parameters = []): ?Url {
    $domainId = self::DOMAIN_IDS[$purpose] ?? NULL;
    $domain = $this->getDomain($purpose);
    if ($domainId === NULL || !$domain) {
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
  public function canonicalPathUrl(string $purpose, string $path): ?Url {
    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
      return NULL;
    }
    $domainId = self::DOMAIN_IDS[$purpose] ?? NULL;
    $domain = $this->getDomain($purpose);
    if ($domainId === NULL || !$domain) {
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
  public function environmentRouteUrl(string $purpose, string $routeName, array $parameters = []): ?Url {
    $domainId = self::DOMAIN_IDS[$purpose] ?? NULL;
    $domain = $this->getDomain($purpose);
    $host = $domainId === NULL ? NULL : $this->deployerHost($domainId);
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
  public function environmentPathUrl(string $purpose, string $path): ?Url {
    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
      return NULL;
    }
    $domainId = self::DOMAIN_IDS[$purpose] ?? NULL;
    $host = $domainId === NULL ? NULL : $this->deployerHost($domainId);
    return $host === NULL ? NULL : Url::fromUri('https://' . $host . $path);
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
   * Generates an absolute path URL using the current purpose alias.
   */
  public function pathUrl(string $purpose, string $path): ?Url {
    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
      return NULL;
    }
    $domain = $this->getDomain($purpose);
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
    // absolute URI from the cloned purpose Domain so cross-domain paths keep
    // the requested purpose and alias environment.
    return Url::fromUri(rtrim($domain->getPath(), '/') . $path);
  }

}
