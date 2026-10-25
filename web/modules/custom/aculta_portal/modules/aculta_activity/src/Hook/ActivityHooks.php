<?php

declare(strict_types=1);

namespace Drupal\aculta_activity\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;
use Drupal\schema_metatag\SchemaMetatagManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Hooks and form presentation owned by the Activity module. */
final class ActivityHooks {

  public function __construct(
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
  ) {}

  /** Adds structured locations and removes empty image tags for activities. */
  #[Hook('metatags_alter')]
  public function metatagsAlter(array &$tags, array &$context): void {
    $node = ($context['entity'] ?? NULL) instanceof NodeInterface ? $context['entity'] : NULL;
    if ($node === NULL || $node->bundle() !== 'activity') {
      return;
    }

    if ($node->get('field_media_image')->isEmpty()) {
      foreach (['schema_article_image', 'schema_event_image', 'schema_web_page_image'] as $id) {
        if (str_contains((string) ($tags[$id] ?? ''), '[node:image]')) {
          unset($tags[$id]);
        }
      }
    }

    $locations = [];
    $mode = $node->get('field_modality')->value;
    if (in_array($mode, ['presencial', 'hibrido'], TRUE)
      && !$node->get('field_place_name')->isEmpty()) {
      $place = [
        '@type' => 'Place',
        'name' => $node->get('field_place_name')->value,
      ];
      $address = ['@type' => 'PostalAddress'];
      foreach ([
        'streetAddress' => 'field_event_street',
        'addressLocality' => 'field_event_city',
        'addressRegion' => 'field_event_region',
        'postalCode' => 'field_event_postal',
        'addressCountry' => 'field_event_country',
      ] as $key => $field) {
        if (!$node->get($field)->isEmpty()) {
          $address[$key] = $node->get($field)->value;
        }
      }
      if (count($address) > 1) {
        $place['address'] = $address;
      }
      $locations[] = $place;
    }

    if (in_array($mode, ['online', 'hibrido'], TRUE)
      && !$node->get('field_online_url')->isEmpty()) {
      $locations[] = [
        '@type' => 'VirtualLocation',
        'url' => $node->get('field_online_url')->uri,
      ];
    }

    // Respect explicit editorial overrides of location metadata.
    if (empty($tags['schema_event_location']) && $locations !== []) {
      $tags['schema_event_location'] = SchemaMetatagManager::serialize(
        count($locations) === 1 ? $locations[0] : $locations,
      );
    }
  }

  /** Groups Activity fields and attaches the owner module's validator. */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $formState, string $formId): void {
    $formObject = $formState->getFormObject();
    $node = method_exists($formObject, 'getEntity') ? $formObject->getEntity() : NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'activity') {
      return;
    }

    $groups = [
      'editorial_content' => [
        $this->translation->translate('Conteúdo'),
        -40,
        ['title', 'body', 'field_summary', 'field_subtitle', 'field_editorial_category', 'field_tags', 'field_editorial_author', 'field_credits', 'field_references'],
      ],
      'editorial_image' => [
        $this->translation->translate('Imagem'),
        -30,
        ['field_media_image', 'field_gallery'],
      ],
      'editorial_relations' => [
        $this->translation->translate('Relacionamentos'),
        -20,
        ['field_related_activity'],
      ],
      'editorial_seo' => [
        $this->translation->translate('SEO e compartilhamento'),
        70,
        ['field_meta_tags'],
      ],
    ];

    foreach ($groups as $id => [$title, $weight, $fields]) {
      $form[$id] = [
        '#type' => 'details',
        '#title' => $title,
        '#open' => in_array($id, ['editorial_content', 'editorial_image'], TRUE),
        '#weight' => $weight,
      ];
      foreach ($fields as $field) {
        if (isset($form[$field])) {
          $form[$field]['#group'] = $id;
        }
      }
    }

    if (isset($form['author'])) {
      $form['author']['#title'] = $this->translation->translate('Conta responsável pelo cadastro');
    }
    if (isset($form['options'])) {
      $form['options']['#title'] = $this->translation->translate('Publicação');
    }
    $form['#validate'][] = 'aculta_activity.form_callbacks:validateActivity';
  }

}
