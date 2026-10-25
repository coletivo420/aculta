<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFormInterface;

/**
 * Form API submit actions for the native Wiki entry form.
 */
final class WikiEntryFormActions {

  /**
   * Selects the official review state before Core saves the Node form.
   */
  public static function submitForReview(array &$form, FormStateInterface $form_state): void {
    $parents = $form['moderation_state']['widget'][0]['state']['#parents'] ?? NULL;
    if (!is_array($parents) || $parents === []) {
      throw new \LogicException('The Wiki review action requires the native moderation state widget.');
    }
    $form_state->setValue($parents, 'review');
    $formObject = $form_state->getFormObject();
    if ($formObject instanceof EntityFormInterface) {
      $formObject->getEntity()->set('moderation_state', 'review');
    }
  }

}
