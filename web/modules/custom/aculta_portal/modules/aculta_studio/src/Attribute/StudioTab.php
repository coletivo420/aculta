<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Declares an ACULTA Studio tab provider.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class StudioTab extends Plugin {

  public function __construct(
    string $id,
    public readonly TranslatableMarkup $label,
    public readonly int $weight = 0,
    ?string $deriver = NULL,
  ) {
    parent::__construct($id, $deriver);
  }

}
