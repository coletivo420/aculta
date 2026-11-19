<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Presentation;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Internal, cache-aware value object for the multidomain shell contract.
 *
 * This object belongs to aculta_portal and must not be exposed to Twig. Use
 * toThemeArray() at the Portal preprocess boundary.
 */
final class DomainPresentation implements CacheableDependencyInterface {

  /**
   * Constructs a cache-aware theme presentation contract.
   *
   * @param array $identity
   *   Stable purpose identity and display labels.
   * @param array $regions
   *   Render arrays and links that belong to presentation regions.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cache metadata for the identity, regions, layout, and site list.
   * @param array $layout
   *   Visibility decided by the Portal per purpose.
   * @param array $domainSites
   *   Published sites with stable purposes and environment-specific URLs.
   */
  public function __construct(
    private readonly array $identity,
    private readonly array $regions,
    private readonly CacheableMetadata $cacheability,
    private readonly array $layout,
    private readonly array $domainSites,
  ) {}

  /**
   * Returns the neutral array allowed to cross the Portal -> theme boundary.
   *
   * @return array<string, mixed>
   *   Identity, regions, layout flags, and the neutral domain_sites list.
   */
  public function toThemeArray(): array {
    return [
      'identity' => $this->identity,
      'regions' => $this->regions,
      'layout' => $this->layout,
      'domain_sites' => $this->domainSites,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return $this->cacheability->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return $this->cacheability->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return $this->cacheability->getCacheMaxAge();
  }

}
