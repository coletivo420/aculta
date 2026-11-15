<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_messenger\Entity\PrivateMessageBan;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Constraint validator for a unique bans.
 */
class UniqueBanConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @param mixed $value
   *   O valor.
   * @param Constraint $constraint
   *   A restrição de validação.
   */
  public function validate($value, Constraint $constraint): void {
    assert($value instanceof PrivateMessageBan);
    if (!$constraint instanceof UniqueBanConstraint) {
      throw new \InvalidArgumentException('UniqueBanConstraintValidator recebeu uma constraint de outro tipo.');
    }

    $storage = $this->entityTypeManager->getStorage('private_message_ban');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('owner', $value->getOwnerId(), '=')
      ->condition('target', $value->getTargetId(), '=');

    if ($query->range(0, 1)->execute()) {
      $this->context->buildViolation($constraint->message, [
        '%user' => $value->getTarget()->getDisplayName(),
      ])->addViolation();
    }
  }

}
