<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Origin;

use Drupal\Core\Entity\EntityInterface;

/**
 * Resolves the stable source category used by discussion consumers.
 */
interface DiscussionOriginResolverInterface {

  /**
   * Returns one origin; absent or conflicting evidence is UNKNOWN.
   */
  public function classify(EntityInterface $entity): DiscussionOriginClassification;

}
