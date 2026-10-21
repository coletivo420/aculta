<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Requires exactly one external/internal URI or referenced Drupal node.
 */
#[Constraint(
  id: 'AcultaLinkDestinationChoice',
  label: new TranslatableMarkup('ACULTA Links destination choice', [], ['context' => 'Validation']),
)]
final class LinkDestinationChoiceConstraint extends SymfonyConstraint {

  /**
   * Violation message for missing or conflicting destinations.
   */
  public string $message = 'Informe uma URL ou escolha um conteúdo do ACULTA, mas não ambos.';

}
