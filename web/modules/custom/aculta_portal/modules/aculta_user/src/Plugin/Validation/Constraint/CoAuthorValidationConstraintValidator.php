<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the aculta_coauthors field items.
 */
final class CoAuthorValidationConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof CoAuthorValidationConstraint) {
      return;
    }

    if (!$value instanceof FieldItemListInterface) {
      return;
    }

    $node = $value->getEntity();
    if (!$node instanceof NodeInterface) {
      return;
    }

    $ownerId = (int) $node->getOwnerId();
    $userStorage = $this->entityTypeManager->getStorage('user');
    $seenUids = [];

    foreach ($value as $delta => $item) {
      $rawTarget = $item->get('target_id')->getValue();
      if (!is_scalar($rawTarget)) {
        continue;
      }
      $targetId = (int) $rawTarget;
      if ($targetId <= 0) {
        continue;
      }

      if ($targetId === $ownerId) {
        $this->context->buildViolation($constraint->ownerMessage)
          ->atPath((string) $delta)
          ->addViolation();
        continue;
      }

      if (isset($seenUids[$targetId])) {
        $this->context->buildViolation($constraint->duplicateMessage)
          ->atPath((string) $delta)
          ->addViolation();
        continue;
      }
      $seenUids[$targetId] = TRUE;

      $targetUser = $userStorage->load($targetId);
      if (!$targetUser instanceof UserInterface || !$targetUser->isActive()) {
        $this->context->buildViolation($constraint->invalidAccountMessage)
          ->atPath((string) $delta)
          ->addViolation();
      }
    }
  }

}
