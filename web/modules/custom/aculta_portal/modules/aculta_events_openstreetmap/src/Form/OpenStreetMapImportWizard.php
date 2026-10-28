<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\Form;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events_openstreetmap\OAuth\OpenStreetMapOAuthException;
use Drupal\aculta_events_openstreetmap\Osm\OpenStreetMapApiClient;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Find, select, review, and save a location against the active OSM API.
 *
 * Adaptado de OpenCulturas 3.1.4 (GPL-2.0-or-later),
 * `profile/modules/custom/openculturas_openstreetmap/src/Wizard/OSMFormWizard.php`.
 */
final class OpenStreetMapImportWizard extends FormBase {

  private const EDITABLE_TAGS = [
    'name' => 'Nome do local',
    'addr:street' => 'Rua',
    'addr:housenumber' => 'Número',
    'addr:unit' => 'Complemento',
    'addr:suburb' => 'Bairro',
    'addr:city' => 'Município',
    'addr:state' => 'Estado',
    'addr:postcode' => 'CEP',
    'opening_hours' => 'Horário de funcionamento',
  ];

  /**
   * Creates a wizard using the active OSM API and local location storage. */
  public function __construct(
    private readonly OpenStreetMapApiClient $osm,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_events_openstreetmap.api_client'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc} */
  public function getFormId(): string {
    return 'aculta_events_openstreetmap_import_wizard';
  }

  /**
   * {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $step = $form_state->get('osm_step') ?? 'find';
    $stepLabels = [
      'find' => $this->t('1 — Encontrar'),
      'select' => $this->t('2 — Selecionar'),
      'review' => $this->t('3 — Revisar'),
      'done' => $this->t('4 — Salvar'),
    ];
    $form['#tree'] = TRUE;
    $form['#cache']['max-age'] = 0;
    $form['steps'] = [
      '#type' => 'item',
      '#title' => $this->t('Etapa'),
      '#markup' => $this->t('@step de 4', ['@step' => $stepLabels[$step] ?? $this->t('1 — Encontrar')]),
    ];

    if ($step === 'find') {
      return $this->buildFindStep($form);
    }
    if ($step === 'select') {
      return $this->buildSelectStep($form, $form_state);
    }
    if ($step === 'review') {
      return $this->buildReviewStep($form, $form_state);
    }
    return $form;
  }

  /**
   * Builds the direct type/ID lookup against the active environment. */
  private function buildFindStep(array $form): array {
    $form['element_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Tipo de elemento OSM'),
      '#options' => [
        'node' => $this->t('Node'),
        'way' => $this->t('Way'),
        'relation' => $this->t('Relation'),
      ],
      '#required' => TRUE,
      '#default_value' => 'node',
    ];
    $form['element_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID do elemento OSM'),
      '#description' => $this->t('A busca consulta somente a API correspondente ao ambiente ativo do site.'),
      '#required' => TRUE,
      '#maxlength' => 19,
      '#pattern' => '[1-9][0-9]*',
    ];
    $form['actions']['find'] = [
      '#type' => 'submit',
      '#value' => $this->t('Encontrar'),
      '#submit' => ['::findSubmit'],
    ];
    return $form;
  }

  /**
   * Builds the tag selection step without exposing contact details. */
  private function buildSelectStep(array $form, FormStateInterface $form_state): array {
    $element = $form_state->get('osm_element');
    if (!is_array($element)) {
      return $this->buildFindStep($form);
    }
    $options = [];
    foreach (array_keys(self::EDITABLE_TAGS) as $tag) {
      $options[$tag] = $this->tagLabel($tag);
    }
    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Elemento encontrado'),
      '#markup' => $this->t('@type/@id (versão @version), @lat, @lon', [
        '@type' => $element['type'],
        '@id' => $element['id'],
        '@version' => $element['version'],
        '@lat' => $element['lat'],
        '@lon' => $element['lon'],
      ]),
    ];
    $form['selected_tags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Dados que serão importados e revisados'),
      '#options' => $options,
      '#default_value' => array_keys($element['tags']),
      '#required' => TRUE,
    ];
    $form['actions']['review'] = [
      '#type' => 'submit',
      '#value' => $this->t('Revisar dados'),
      '#submit' => ['::selectSubmit'],
    ];
    $form['actions']['restart'] = [
      '#type' => 'submit',
      '#value' => $this->t('Escolher outro elemento'),
      '#submit' => ['::restartSubmit'],
      '#limit_validation_errors' => [],
    ];
    return $form;
  }

  /**
   * Builds the final review with an explicit confirmation before any OSM write. */
  private function buildReviewStep(array $form, FormStateInterface $form_state): array {
    $element = $form_state->get('osm_element');
    $selected = $form_state->get('osm_selected_tags');
    if (!is_array($element) || !is_array($selected)) {
      return $this->buildFindStep($form);
    }
    $form['attribution'] = [
      '#type' => 'item',
      '#title' => $this->t('Atribuição'),
      '#markup' => $this->t('Dados © OpenStreetMap contributors, licença ODbL. Consulte <a href="https://www.openstreetmap.org/copyright">direitos autorais e licença do OpenStreetMap</a>.'),
    ];
    $form['coordinates'] = [
      '#type' => 'item',
      '#title' => $this->t('Coordenadas importadas'),
      '#markup' => $element['lat'] . ', ' . $element['lon'],
    ];
    $saved_values = $form_state->get('osm_review_values') ?? [];
    foreach ($selected as $tag) {
      if (!isset(self::EDITABLE_TAGS[$tag])) {
        continue;
      }
      $form['review'][$tag] = [
        '#type' => 'textfield',
        '#title' => $this->tagLabel($tag),
        '#required' => $tag === 'name',
        '#maxlength' => 255,
        '#default_value' => $saved_values[$tag] ?? $element['tags'][$tag] ?? '',
      ];
    }
    $form['changeset_comment'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Comentário da alteração no OpenStreetMap'),
      '#description' => $this->t('Descreva a alteração para o histórico público do OSM; máximo de 255 caracteres.'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#default_value' => $form_state->get('osm_comment') ?? '',
    ];
    $form['confirm_send'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirmo a importação deste local e o envio das alterações revisadas à API do ambiente ativo do OpenStreetMap.'),
      '#required' => TRUE,
    ];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirmar e salvar'),
      '#submit' => ['::saveSubmit'],
    ];
    $form['actions']['restart'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancelar e começar de novo'),
      '#submit' => ['::restartSubmit'],
      '#limit_validation_errors' => [],
    ];
    return $form;
  }

  /**
   * Looks up the selected element only after an explicit user submission. */
  public function findSubmit(array &$form, FormStateInterface $form_state): void {
    try {
      $element = $this->osm->find((string) $form_state->getValue('element_type'), (string) $form_state->getValue('element_id'));
      $form_state->set('osm_element', $element);
      $form_state->set('osm_step', 'select');
      $form_state->setRebuild();
    }
    catch (OpenStreetMapOAuthException $exception) {
      $form_state->setErrorByName('element_id', $this->t('Não foi possível consultar esse elemento na API do ambiente ativo. Confira tipo e ID.'));
    }
  }

  /**
   * Stores the selected allowlisted tags before the review step. */
  public function selectSubmit(array &$form, FormStateInterface $form_state): void {
    $selected = array_values(array_filter((array) $form_state->getValue('selected_tags')));
    if (!in_array('name', $selected, TRUE)) {
      $form_state->setErrorByName('selected_tags', $this->t('Selecione o nome do local para criar o registro.'));
      return;
    }
    $form_state->set('osm_selected_tags', $selected);
    $form_state->set('osm_step', 'review');
    $form_state->setRebuild();
  }

  /**
   * Persists the reviewed location and submits only changed allowlisted tags. */
  public function saveSubmit(array &$form, FormStateInterface $form_state): void {
    $element = $form_state->get('osm_element');
    $selected = $form_state->get('osm_selected_tags');
    if (!is_array($element) || !is_array($selected) || !$form_state->getValue('confirm_send')) {
      $form_state->setErrorByName('confirm_send', $this->t('A confirmação é obrigatória.'));
      return;
    }
    $review = (array) $form_state->getValue('review');
    $changes = [];
    foreach ($selected as $tag) {
      if (isset(self::EDITABLE_TAGS[$tag])) {
        $value = trim((string) ($review[$tag] ?? ''));
        if ($value !== ($element['tags'][$tag] ?? '')) {
          $changes[$tag] = $value;
        }
      }
    }

    try {
      $result = $changes === [] ? NULL : $this->osm->update($element, $changes, (string) $form_state->getValue('changeset_comment'));
      $location = $this->createLocation($element, $selected, $review, $result['version'] ?? $element['version']);
      $location->save();
      $form_state->set('osm_step', 'done');
      $this->messenger()->addStatus($this->t('Local importado. @result', [
        '@result' => $result === NULL ? 'Nenhuma diferença foi enviada ao OSM.' : 'Alterações enviadas na changeset ' . $result['changeset'] . '.',
      ]));
      $form_state->setRedirect('aculta_events_openstreetmap.status');
    }
    catch (OpenStreetMapOAuthException $exception) {
      $form_state->setErrorByName('confirm_send', $this->t('Não foi possível enviar as alterações ao OSM. O local não foi salvo. Verifique conexão, versão e credenciais do ambiente.'));
    }
    catch (\Throwable $exception) {
      $form_state->setErrorByName('confirm_send', $this->t('O local não pôde ser salvo depois da revisão. Nenhuma credencial foi exibida.'));
    }
  }

  /**
   * Resets all transient wizard data. */
  public function restartSubmit(array &$form, FormStateInterface $form_state): void {
    foreach (['osm_element', 'osm_selected_tags', 'osm_review_values', 'osm_comment'] as $key) {
      $form_state->set($key, NULL);
    }
    $form_state->set('osm_step', 'find');
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Each wizard stage defines its own submit handler on the action button.
  }

  /**
   * Saves only approved address tags and coordinates into an event location.
   */
  private function createLocation(array $element, array $selected, array $review, string $version): EventLocation {
    $tags = [];
    foreach ($selected as $key) {
      if (isset(self::EDITABLE_TAGS[$key])) {
        $tags[$key] = trim((string) ($review[$key] ?? ''));
      }
    }
    $street = trim(($tags['addr:street'] ?? '') . ' ' . ($tags['addr:housenumber'] ?? ''));
    $location = $this->entityTypeManager->getStorage('aculta_event_location')->create([
      'title' => $tags['name'] ?? 'Local OpenStreetMap ' . $element['type'] . '/' . $element['id'],
      'field_osm_id' => $element['type'] . '/' . $element['id'],
      'field_osm_version' => $version,
      'field_endereco' => [
        'country_code' => 'BR',
        'address_line1' => $street,
        'address_line2' => $tags['addr:unit'] ?? '',
        'dependent_locality' => $tags['addr:suburb'] ?? '',
        'locality' => $tags['addr:city'] ?? '',
        'administrative_area' => $tags['addr:state'] ?? '',
        'postal_code' => $tags['addr:postcode'] ?? '',
      ],
      'field_coordenadas' => [
        'lat' => $element['lat'],
        'lon' => $element['lon'],
        'value' => sprintf('POINT (%F %F)', $element['lon'], $element['lat']),
        'geo_type' => 'Point',
      ],
      'field_horario' => $tags['opening_hours'] ?? '',
    ]);
    if (!$location instanceof EventLocation) {
      throw new \RuntimeException('Could not create the event location entity.');
    }
    return $location;
  }

  /**
   * Translates the finite set of editable OSM tag labels. */
  private function tagLabel(string $tag): string {
    return match ($tag) {
      'name' => (string) $this->t('Nome do local'),
      'addr:street' => (string) $this->t('Rua'),
      'addr:housenumber' => (string) $this->t('Número'),
      'addr:unit' => (string) $this->t('Complemento'),
      'addr:suburb' => (string) $this->t('Bairro'),
      'addr:city' => (string) $this->t('Município'),
      'addr:state' => (string) $this->t('Estado'),
      'addr:postcode' => (string) $this->t('CEP'),
      'opening_hours' => (string) $this->t('Horário de funcionamento'),
      default => throw new \InvalidArgumentException('Unsupported OpenStreetMap tag.'),
    };
  }

}
