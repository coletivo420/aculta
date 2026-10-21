<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Edit form of a Revista publication: the secondary properties are grouped and collapsed, and the form warns
 * before leaving with unsaved changes. The form stays the owner's (Form API, access, CSRF and workflow).
 */
final class RevistaFormHooks {

  /** Form ids of the publication forms (create and edit). */
  private const FORM_IDS = ['node_revista_form', 'node_revista_edit_form'];

  /** Fields and properties that support the publication and that are not part of the main writing flow. */
  private const SECONDARY = [
    'field_tags',
    'field_project',
    'field_references',
    'field_related_activity',
    'field_credits',
    'field_meta_tags',
    'field_published_at',
    'path',
    'url_redirects',
    'simple_sitemap',
    'menu',
  ];

  #[Hook('form_alter')]
  public function alter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!in_array($form_id, self::FORM_IDS, TRUE)) {
      return;
    }
    $secondary = [
      '#type' => 'details',
      '#title' => new TranslatableMarkup('Propriedades secundárias'),
      '#description' => new TranslatableMarkup('Etiquetas, projeto, referências, SEO, endereço e agendamento.'),
      '#open' => FALSE,
      '#weight' => 90,
      '#attributes' => ['data-aculta-revista' => 'secondary'],
    ];
    foreach (self::SECONDARY as $key) {
      if (isset($form[$key]) && is_array($form[$key])) {
        $secondary[$key] = $form[$key];
        unset($form[$key]);
      }
    }
    if (count($secondary) > 6) {
      $form['revista_secondary'] = $secondary;
    }
    $form['#attached']['library'][] = 'aculta_magazine/revista-form';
  }

}
