<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Service;

use Drupal\aculta_cgu\Entity\CguReport;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Loads only existing content entities as candidate report targets.
 *
 * ReportService still decides whether the viewer may actually report them.
 */
final class ReportTargetResolver {

  public function __construct(private readonly EntityTypeManagerInterface $entities) {}

  public function load(string $type, string|int $id): ?ContentEntityInterface {
    if (!preg_match('/^[a-z][a-z0-9_]*$/D', $type)
      || !ctype_digit((string) $id)
      || (int) $id <= 0
      || !$this->entities->hasDefinition($type)) {
      return NULL;
    }
    $entity = $this->entities->getStorage($type)->load((int) $id);
    return $entity instanceof ContentEntityInterface && !$entity instanceof CguReport
      ? $entity : NULL;
  }

}
