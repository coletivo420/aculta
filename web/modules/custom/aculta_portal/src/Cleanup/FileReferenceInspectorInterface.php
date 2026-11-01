<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cleanup;

use Drupal\Core\Entity\EntityInterface;

/**
 * Finds structured and formatted-text references to an entity.
 */
interface FileReferenceInspectorInterface {

  /**
   * Returns clear, referenced, or unverifiable after checking entity storage.
   */
  public function inspect(EntityInterface $target): FileReferenceStatus;

}
