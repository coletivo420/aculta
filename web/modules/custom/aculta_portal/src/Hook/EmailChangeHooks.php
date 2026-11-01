<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Account\EmailChangePolicy;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Remove a troca de e-mail da edição de conta do Core (ver EmailChangePolicy).
 */
final class EmailChangeHooks {

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for user forms.
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }
    $allowed = EmailChangePolicy::mailFieldAllowed($form_object->getEntity()->isNew(), $form_object->getOperation());
    if (!$allowed) {
      unset($form['account']['mail']);
    }
  }

}
