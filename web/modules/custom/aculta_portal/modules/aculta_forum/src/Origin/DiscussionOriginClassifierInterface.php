<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Origin;

use Drupal\Core\Entity\EntityInterface;

/**
 * Optional source classifier. Return NULL when the provider does not own it.
 */
interface DiscussionOriginClassifierInterface {

  /**
   * Classifies an entity owned by this provider.
   */
  public function classify(EntityInterface $entity): ?DiscussionOriginClassification;

}
