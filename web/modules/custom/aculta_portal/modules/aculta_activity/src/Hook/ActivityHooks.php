<?php

declare(strict_types=1);

namespace Drupal\aculta_activity\Hook;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;
use Drupal\schema_metatag\SchemaMetatagManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hooks and form presentation owned by the Activity module.
 */
final class ActivityHooks {

  public function __construct(
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Adds structured locations and removes empty image tags for activities.
   */
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
    if (in_array($mode, ['presencial', 'hibrido'], TRUE)) {
      $placeEntity = NULL;
      if ($node->hasField('field_event_location') && !$node->get('field_event_location')->isEmpty()) {
        $placeEntity = $node->get('field_event_location')->entity;
      }

      if ($placeEntity instanceof EventLocation) {
        $place = [
          '@type' => 'Place',
          'name' => $placeEntity->label(),
        ];
        if ($placeEntity->hasField('field_endereco') && !$placeEntity->get('field_endereco')->isEmpty()) {
          $addr = $placeEntity->get('field_endereco')->first();
          if ($addr) {
            $address = [
              '@type' => 'PostalAddress',
              'streetAddress' => (string) $addr->address_line1,
              'addressLocality' => (string) $addr->locality,
              'addressRegion' => (string) $addr->administrative_area,
              'postalCode' => (string) $addr->postal_code,
              'addressCountry' => (string) ($addr->country_code ?: 'BR'),
            ];
            $place['address'] = array_filter($address);
          }
        }
        $locations[] = $place;
      }
      elseif (!$node->get('field_place_name')->isEmpty()) {
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

  /**
   * Sincroniza campos de endereço legado a partir de field_event_location.
   */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    if ($node->bundle() !== 'activity') {
      return;
    }

    if ($node->hasField('field_event_location') && !$node->get('field_event_location')->isEmpty()) {
      $location = $node->get('field_event_location')->entity;
      if ($location instanceof EventLocation) {
        if ($node->hasField('field_place_name')) {
          $node->set('field_place_name', $location->label());
        }
        if ($location->hasField('field_endereco') && !$location->get('field_endereco')->isEmpty()) {
          $addr = $location->get('field_endereco')->first();
          if ($addr) {
            if ($node->hasField('field_event_street')) {
              $node->set('field_event_street', (string) $addr->address_line1);
            }
            if ($node->hasField('field_event_city')) {
              $node->set('field_event_city', (string) $addr->locality);
            }
            if ($node->hasField('field_event_region')) {
              $node->set('field_event_region', (string) $addr->administrative_area);
            }
            if ($node->hasField('field_event_postal')) {
              $node->set('field_event_postal', (string) $addr->postal_code);
            }
            if ($node->hasField('field_event_country')) {
              $node->set('field_event_country', (string) ($addr->country_code ?: 'BR'));
            }
          }
        }
      }
    }
  }

  /**
   * Renderiza o local referenciado em atividades.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, string $viewMode): void {
    if ($node->bundle() !== 'activity') {
      return;
    }

    if ($display->getComponent('field_event_location') && $node->hasField('field_event_location') && !$node->get('field_event_location')->isEmpty()) {
      $location = $node->get('field_event_location')->entity;
      if ($location instanceof EventLocation) {
        $currentUser = \Drupal::currentUser();
        if ($location->access('view', $currentUser)) {
          $build['field_event_location'] = [
            '#theme' => 'item_list',
            '#title' => $this->translation->translate('Local do evento'),
            '#items' => [
              [
                '#type' => 'link',
                '#title' => $location->label(),
                '#url' => $location->toUrl(),
              ],
            ],
            '#weight' => $display->getComponent('field_event_location')['weight'] ?? 10,
          ];
        }
      }
    }
  }

  /**
   * Groups Activity fields and attaches the owner module's validator.
   */
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
        [
          'title',
          'body',
          'field_summary',
          'field_subtitle',
          'field_editorial_category',
          'field_tags',
          'field_editorial_author',
          'field_credits',
          'field_references',
          'field_event_location',
        ],
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
