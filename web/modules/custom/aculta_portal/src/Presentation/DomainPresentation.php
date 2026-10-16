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
   * @param array{purpose: string, title: string, short_title: ?string, home_url: string, logo_alt: ?string} $identity
   * @param array{brand_media: ?array, navigation: ?array, actions: ?array, wiki_search: ?array} $regions
   * @param array{institution_bar_account: bool, show_utility_bar: bool, links_page: bool, show_site_header: bool} $layout
   *   Visibility decided by the Portal per purpose. The theme only draws what these flags allow.
   */
  public function __construct(
    private readonly array $identity,
    private readonly array $regions,
    private readonly CacheableMetadata $cacheability,
    private readonly array $layout,
  ) {}

  /**
   * Returns the neutral array allowed to cross the Portal -> theme boundary.
   *
   * @return array{
   *   identity: array{purpose: string, title: string, short_title: ?string, home_url: string, logo_alt: ?string},
   *   regions: array{brand_media: ?array, navigation: ?array, actions: ?array, wiki_search: ?array},
   *   layout: array{institution_bar_account: bool, show_utility_bar: bool, links_page: bool, show_site_header: bool}
   * }
   */
  public function toThemeArray(): array {
    return [
      'identity' => $this->identity,
      'regions' => $this->regions,
      'layout' => $this->layout,
    ];
  }

  /** {@inheritdoc} */
  public function getCacheContexts(): array {
    return $this->cacheability->getCacheContexts();
  }

  /** {@inheritdoc} */
  public function getCacheTags(): array {
    return $this->cacheability->getCacheTags();
  }

  /** {@inheritdoc} */
  public function getCacheMaxAge(): int {
    return $this->cacheability->getCacheMaxAge();
  }

}
