<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Participation;

/**
 * Dispatches evidence requests to exactly one provider per context.
 */
final class PublishedParticipationLookup {

  /**
   * Registered context-specific providers.
   *
   * @var \Drupal\aculta_user\Participation\PublishedParticipationLookupInterface[]
   */
  private readonly array $providers;

  /**
   * Constructs the lookup dispatcher.
   *
   * @param iterable<\Drupal\aculta_user\Participation\PublishedParticipationLookupInterface> $providers
   *   Context-specific evidence providers.
   */
  public function __construct(iterable $providers) {
    $this->providers = is_array($providers) ? $providers : iterator_to_array($providers);
  }

  /**
   * Looks up evidence without combining separate contexts.
   */
  public function lookup(int $accountId, string $contextId): PublishedParticipationEvidence {
    if ($accountId < 1 || $contextId === '') {
      return PublishedParticipationEvidence::unavailable();
    }

    $providers = array_values(array_filter(
      $this->providers,
      static fn (PublishedParticipationLookupInterface $provider): bool => $provider->getContextId() === $contextId,
    ));
    if (count($providers) !== 1) {
      return PublishedParticipationEvidence::unavailable();
    }

    try {
      return $providers[0]->lookup($accountId);
    }
    catch (\Throwable) {
      // A lookup failure is never evidence that publication has occurred.
      return PublishedParticipationEvidence::unavailable();
    }
  }

}
