<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Context;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use LogicException;

/** Resolves a host entity through registered context providers. */
final class DiscussionContextResolver {

  /** @var \Drupal\aculta_forum\Context\DiscussionContextProviderInterface[] */
  private readonly array $providers;

  /** @param iterable<\Drupal\aculta_forum\Context\DiscussionContextProviderInterface> $providers */
  public function __construct(iterable $providers) {
    $this->providers = is_array($providers) ? array_values($providers) : iterator_to_array($providers, FALSE);
    foreach ($this->providers as $provider) {
      if (!$provider instanceof DiscussionContextProviderInterface) {
        throw new LogicException('Every discussion context provider must implement DiscussionContextProviderInterface.');
      }
    }
  }

  /**
   * Resolves the entity to one provider; unknown entities return NULL.
   *
   * Multiple matches are configuration errors and fail explicitly.
   */
  public function resolve(EntityInterface $entity, AccountInterface $account): ?DiscussionContextInterface {
    $matches = [];
    foreach ($this->providers as $provider) {
      if ($provider->supports($entity)) {
        $matches[] = $provider;
      }
    }
    if (count($matches) > 1) {
      throw new LogicException(sprintf('More than one discussion context provider supports %s:%s.', $entity->getEntityTypeId(), (string) $entity->id()));
    }
    return $matches === [] ? NULL : $matches[0]->getContext($entity, $account);
  }

}
