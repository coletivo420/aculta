<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Plugin\Validation\Constraint;

use Drupal\aculta_links\Entity\Link;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Enforces one and only one destination source on a reusable link.
 */
final class LinkDestinationChoiceConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$value instanceof Link) {
      throw new UnexpectedValueException($value, Link::class);
    }

    $has_uri = !$value->get('uri')->isEmpty();
    $has_node = !$value->get('target_node')->isEmpty();
    if ($has_uri === $has_node) {
      $this->context->buildViolation($constraint->message)
        ->atPath($has_node ? 'target_node' : 'uri')
        ->addViolation();
    }
  }

}
