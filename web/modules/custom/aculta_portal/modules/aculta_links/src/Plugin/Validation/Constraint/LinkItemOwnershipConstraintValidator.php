<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Plugin\Validation\Constraint;

use Drupal\aculta_links\Entity\LinkItem;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Rejects compositions that cross account ownership boundaries.
 */
final class LinkItemOwnershipConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$value instanceof LinkItem) {
      throw new UnexpectedValueException($value, LinkItem::class);
    }

    $page = $value->get('page')->entity;
    $link = $value->get('link')->entity;
    if (!$page || !$link) {
      return;
    }

    $owner_id = (string) $value->getOwnerId();
    if ($owner_id === ''
      || $owner_id !== (string) $page->getOwnerId()
      || $owner_id !== (string) $link->getOwnerId()) {
      $this->context->buildViolation($constraint->message)
        ->atPath('link')
        ->addViolation();
    }
  }

}
