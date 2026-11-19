<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Origin;

use Drupal\Core\Entity\EntityInterface;

/**
 * Resolves entity origin through optional, independently owned providers.
 */
final class DiscussionOriginResolver implements DiscussionOriginResolverInterface {

  /**
   * Classifiers registered by the Forum core and optional addons.
   *
   * @var list<\Drupal\aculta_forum\Origin\DiscussionOriginClassifierInterface>
   */
  private readonly array $classifiers;

  /**
   * Creates the resolver.
   *
   * @param iterable<\Drupal\aculta_forum\Origin\DiscussionOriginClassifierInterface> $classifiers
   *   Tagged origin classifiers.
   */
  public function __construct(iterable $classifiers) {
    $this->classifiers = is_array($classifiers) ? array_values($classifiers) : iterator_to_array($classifiers, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function classify(EntityInterface $entity): DiscussionOriginClassification {
    $claims = [];
    foreach ($this->classifiers as $classifier) {
      $claim = $classifier->classify($entity);
      if ($claim !== NULL) {
        $claims[] = $claim;
      }
    }

    if ($claims === []) {
      return DiscussionOriginClassification::from(DiscussionOrigin::UNKNOWN, $entity);
    }

    $sources = [$entity];
    $origins = [];
    foreach ($claims as $claim) {
      $sources[] = $claim;
      $origins[$claim->origin] = TRUE;
    }

    if (isset($origins[DiscussionOrigin::UNKNOWN])) {
      return DiscussionOriginClassification::fromSources(DiscussionOrigin::UNKNOWN, $sources);
    }

    if (count($origins) === 1) {
      return DiscussionOriginClassification::fromSources((string) array_key_first($origins), $sources);
    }

    // A legacy provider is authoritative for its archive records. Its claim
    // may overlap the generic Forum host classifier by design.
    $nonArchiveOrigins = array_diff(
      array_keys($origins),
      [DiscussionOrigin::ARCHIVE, DiscussionOrigin::CURRENT, DiscussionOrigin::CONTEXTUAL],
    );
    if (isset($origins[DiscussionOrigin::ARCHIVE]) && $nonArchiveOrigins === []) {
      return DiscussionOriginClassification::fromSources(DiscussionOrigin::ARCHIVE, $sources);
    }

    return DiscussionOriginClassification::fromSources(DiscussionOrigin::UNKNOWN, $sources);
  }

}
