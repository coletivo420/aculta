<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Requires a local Drupal URI or a credential-free HTTPS destination.
 */
#[Constraint(
  id: 'AcultaLinkDestination',
  label: new TranslatableMarkup('Safe ACULTA Links destination', [], ['context' => 'Validation']),
)]
final class LinkDestinationConstraint extends SymfonyConstraint {

  /**
   * Violation message for unsafe destinations. */
  public string $message = 'Use um caminho interno do Drupal ou uma URL HTTPS sem credenciais.';

}
