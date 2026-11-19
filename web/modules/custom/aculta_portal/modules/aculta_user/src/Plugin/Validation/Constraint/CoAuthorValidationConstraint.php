<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates that coauthors are valid, active accounts and not the node owner.
 */
#[Constraint(
  id: 'AcultaCoAuthorValidation',
  label: new TranslatableMarkup('Validação de coautoria do ACULTA', [], ['context' => 'Validation']),
)]
final class CoAuthorValidationConstraint extends SymfonyConstraint {

  /**
   * Violation message when node owner is added as coauthor.
   */
  public string $ownerMessage = 'O autor principal do conteúdo não pode ser adicionado como coautor.';

  /**
   * Violation message when a blocked or non-existent account is referenced.
   */
  public string $invalidAccountMessage = 'A conta de coautor informada não existe ou está bloqueada.';

  /**
   * Violation message when duplicate coauthors are referenced.
   */
  public string $duplicateMessage = 'Não é permitido incluir o mesmo coautor mais de uma vez.';

}
