<?php

declare(strict_types=1);

namespace Drupal\aculta_mig_new\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Formatter fixture usado pelo spike de migração.
 */
#[FieldFormatter(
  id: 'aculta_mig_formatter',
  label: new TranslatableMarkup('Migration spike'),
  field_types: ['string'],
)]
final class MigFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements($items, $langcode): array {
    return [];
  }

}
