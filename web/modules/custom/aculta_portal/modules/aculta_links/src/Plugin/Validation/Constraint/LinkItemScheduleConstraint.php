<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates the open interval used by a scheduled page item. */
#[Constraint(
  id: 'AcultaLinkItemSchedule',
  label: new TranslatableMarkup('ACULTA Links item schedule', [], ['context' => 'Validation']),
)]
final class LinkItemScheduleConstraint extends SymfonyConstraint {

  /**
   * Violation message when the end is not later than the start. */
  public string $message = 'A data final deve ser posterior à data inicial.';

}
