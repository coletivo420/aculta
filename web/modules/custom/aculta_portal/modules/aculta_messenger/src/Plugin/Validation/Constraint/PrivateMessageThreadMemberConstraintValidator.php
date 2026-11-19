<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Private message thread constraint validator.
 *
 * Ensures that all members of a private message thread have permission to use
 * the private message thread.
 */
class PrivateMessageThreadMemberConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface<\Drupal\Core\Entity\EntityInterface> $value
   *   The list of referenced users.
   * @param \Drupal\aculta_messenger\Plugin\Validation\Constraint\PrivateMessageThreadMemberConstraint $constraint
   *   The constraint.
   */
  public function validate($value, Constraint $constraint): void {
    $users = $value->referencedEntities();
    foreach ($users as $user) {
      /** @var \Drupal\user\UserInterface $user */
      if (!$user->hasPermission('use private messaging system')) {
        $this->context->addViolation($constraint->userPrivateMessagePermissionError, ['%user' => $user->getDisplayName()]);
      }
    }
  }

}
