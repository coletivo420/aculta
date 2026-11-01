<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Plugin\Validation\Constraint;

use Drupal\aculta_links\Entity\LinkItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Rejects an empty or reversed schedule window. */
final class LinkItemScheduleConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$value instanceof LinkItem) {
      throw new UnexpectedValueException($value, LinkItem::class);
    }

    $from = $value->get('available_from')->value;
    $until = $value->get('available_until')->value;
    if (!$from || !$until) {
      return;
    }

    $timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $from_date = \DateTimeImmutable::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $from, $timezone);
    $until_date = \DateTimeImmutable::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $until, $timezone);
    if (!$from_date || !$until_date || $until_date <= $from_date) {
      $this->context->buildViolation($constraint->message)
        ->atPath('available_until')
        ->addViolation();
    }
  }

}
