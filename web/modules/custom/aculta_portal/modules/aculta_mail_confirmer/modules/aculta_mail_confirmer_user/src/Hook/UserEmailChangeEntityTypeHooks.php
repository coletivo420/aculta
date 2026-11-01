<?php

declare(strict_types=1);

namespace Drupal\aculta_mail_confirmer_user\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Tipos de entidade: formulário de cancelamento da troca de e-mail.
 */
final class UserEmailChangeEntityTypeHooks {

  /**
   * Implements hook_entity_type_alter().
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface[] $entity_types
   *   The entity types.
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    $entity_types['user']->setFormClass('cancel_email_change', '\Drupal\aculta_mail_confirmer_user\Form\UserEmailChangeCancelForm');
  }

}
