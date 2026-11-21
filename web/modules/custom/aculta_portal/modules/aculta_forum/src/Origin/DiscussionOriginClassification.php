<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Origin;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Origin result plus the cacheability of the evidence used to classify it.
 */
final class DiscussionOriginClassification implements CacheableDependencyInterface {

  private function __construct(
    public readonly string $origin,
    private readonly CacheableMetadata $cacheability,
  ) {
    if (!DiscussionOrigin::isValid($origin)) {
      throw new \InvalidArgumentException(sprintf('Unsupported discussion origin "%s".', $origin));
    }
  }

  /**
   * Creates a classification from one cacheable source.
   */
  public static function from(string $origin, CacheableDependencyInterface $source): self {
    return new self($origin, CacheableMetadata::createFromObject($source));
  }

  /**
   * Creates a classification with explicit cache metadata.
   *
   * @param string $origin
   *   The classification identifier.
   * @param list<\Drupal\Core\Cache\CacheableDependencyInterface> $sources
   *   Cacheable evidence used by providers.
   */
  public static function fromSources(string $origin, array $sources): self {
    $cacheability = new CacheableMetadata();
    foreach ($sources as $source) {
      $cacheability = $cacheability->merge(CacheableMetadata::createFromObject($source));
    }
    return new self($origin, $cacheability);
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
