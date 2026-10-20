<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Ajuste de política de e-mail (plugin `AcultaMailAdjuster`).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AcultaMailAdjuster extends Plugin {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    public readonly string $id,
    public readonly string|TranslatableMarkup|null $label = NULL,
    public readonly string|TranslatableMarkup|null $description = NULL,
  ) {}

}
