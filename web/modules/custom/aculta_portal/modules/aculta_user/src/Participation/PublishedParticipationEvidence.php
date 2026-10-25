<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Participation;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Aggregate evidence that an account has published in one context.
 *
 * This value deliberately contains no entity IDs, text, or personal details.
 */
final class PublishedParticipationEvidence implements CacheableDependencyInterface {

  private function __construct(
    private readonly bool $hasPublishedContribution,
    private readonly bool $reliable,
    private readonly CacheableMetadata $cacheability,
  ) {}

  /**
   * Creates evidence from a successful context-specific lookup.
   *
   * @param bool $hasPublishedContribution
   *   Whether the account has a published contribution in the context.
   * @param string[] $cacheContexts
   *   Cache contexts required by the evidence.
   * @param string[] $cacheTags
   *   Cache tags invalidated when the evidence may change.
   * @param int $cacheMaxAge
   *   Cache lifetime for the evidence.
   */
  public static function known(bool $hasPublishedContribution, array $cacheContexts = [], array $cacheTags = [], int $cacheMaxAge = Cache::PERMANENT): self {
    $cacheability = (new CacheableMetadata())
      ->setCacheContexts($cacheContexts)
      ->setCacheTags($cacheTags)
      ->setCacheMaxAge($cacheMaxAge);
    return new self($hasPublishedContribution, TRUE, $cacheability);
  }

  /**
   * Creates unavailable evidence that fails closed.
   */
  public static function unavailable(): self {
    return new self(FALSE, FALSE, (new CacheableMetadata())->setCacheMaxAge(0));
  }

  /**
   * Whether a published contribution was found. */
  public function hasPublishedContribution(): bool {
    return $this->hasPublishedContribution;
  }

  /**
   * Whether the lookup completed successfully with exactly one provider. */
  public function isReliable(): bool {
    return $this->reliable;
  }

  /**
   * Whether policy must require review because there is no trusted evidence.
   */
  public function requiresReview(): bool {
    return !$this->reliable || !$this->hasPublishedContribution;
  }

  /**
   * {@inheritdoc} */
  public function getCacheContexts(): array {
    return $this->cacheability->getCacheContexts();
  }

  /**
   * {@inheritdoc} */
  public function getCacheTags(): array {
    return $this->cacheability->getCacheTags();
  }

  /**
   * {@inheritdoc} */
  public function getCacheMaxAge(): int {
    return $this->cacheability->getCacheMaxAge();
  }

}
