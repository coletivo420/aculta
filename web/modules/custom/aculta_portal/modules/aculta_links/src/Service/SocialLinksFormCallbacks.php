<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Service;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form API callbacks for dynamically editing LINKS page buttons. */
final class SocialLinksFormCallbacks {

  /**
   * Adds an empty button row and rebuilds the form. */
  public function addButton(array &$form, FormStateInterface $form_state): void {
    $ids = $form_state->get('aculta_social_links_button_ids') ?? [];
    $next_id = (int) ($form_state->get('aculta_social_links_button_next_id') ?? 0);
    $ids[] = $next_id;
    $form_state->set('aculta_social_links_button_ids', $ids);
    $form_state->set('aculta_social_links_button_next_id', $next_id + 1);
    $form_state->setRebuild();
  }

  /**
   * Removes the selected button row while retaining remaining form values. */
  public function removeButton(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement() ?? [];
    $trigger_name = (string) ($trigger['#name'] ?? '');
    if (!preg_match('/^remove_social_button_(\d+)$/', $trigger_name, $matches)) {
      return;
    }
    $remove_id = $matches[1];
    $ids = $form_state->get('aculta_social_links_button_ids') ?? [];
    $ids = array_values(array_filter($ids, static fn ($id): bool => (string) $id !== $remove_id));
    $form_state->set('aculta_social_links_button_ids', $ids);

    $values = $form_state->getValue(['buttons_wrapper', 'items']) ?? [];
    unset($values[$remove_id]);
    $form_state->setValue(['buttons_wrapper', 'items'], $values);
    $form_state->setRebuild();
  }

  /**
   * Returns the dynamic button controls wrapper to Drupal AJAX. */
  public function buttonsAjax(array &$form, FormStateInterface $form_state): array {
    return $form['buttons_wrapper'];
  }

}
