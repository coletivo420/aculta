<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Requires a page item, its page, and destination to share one owner.
 */
#[Constraint(
  id: 'AcultaLinkItemOwnership',
  label: new TranslatableMarkup('ACULTA Links item ownership', [], ['context' => 'Validation']),
)]
final class LinkItemOwnershipConstraint extends SymfonyConstraint {

  /**
   * Violation message when the referenced content has different owners.
   */
  public string $message = 'A micropágina, o item e o destino precisam pertencer à mesma conta.';

}
