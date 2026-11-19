<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\Form;

use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events_openstreetmap\OAuth\OpenStreetMapOAuthException;
use Drupal\aculta_events_openstreetmap\Osm\OpenStreetMapApiClient;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
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
   * OpenStreetMap tags mapped through the shared Brazilian address widget.
   */
  private const ADDRESS_TAGS = [
    'addr:street' => 'address_line1',
    'addr:unit' => 'address_line2',
    'addr:suburb' => 'dependent_locality',
    'addr:city' => 'locality',
    'addr:state' => 'administrative_area',
    'addr:postcode' => 'postal_code',
  ];

  /**
   * Creates a wizard using the active OSM API and local location storage.
   */
  public function __construct(
    protected readonly OpenStreetMapApiClient $osm,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_events_openstreetmap.api_client'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_openstreetmap_import_wizard';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   *
   * @return array<mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $rawStep = $form_state->get('osm_step');
    $step = is_string($rawStep) && $rawStep !== '' ? $rawStep : 'find';
    $stepLabels = [
      'find' => $this->t('1 — Encontrar'),
      'select' => $this->t('2 — Selecionar'),
      'review' => $this->t('3 — Revisar'),
      'done' => $this->t('4 — Salvar'),
    ];
    $form['#tree'] = TRUE;
    if (!isset($form['#cache']) || !is_array($form['#cache'])) {
      $form['#cache'] = [];
    }
    $form['#cache']['max-age'] = 0;
    $label = isset($stepLabels[$step]) ? $stepLabels[$step] : $this->t('1 — Encontrar');
    $form['steps'] = [
      '#type' => 'item',
      '#title' => $this->t('Etapa'),
      '#markup' => $this->t('@step de 4', ['@step' => $label]),
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
   * Builds the direct type/ID lookup against the active environment.
   *
   * @param array<mixed> $form
   *
   * @return array<mixed>
   */
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
    if (!isset($form['actions']) || !is_array($form['actions'])) {
      $form['actions'] = [];
    }
    $form['actions']['find'] = [
      '#type' => 'submit',
      '#value' => $this->t('Encontrar'),
      '#submit' => ['::findSubmit'],
    ];
    return $form;
  }

  /**
   * Builds the tag selection step without exposing contact details.
   *
   * @param array<mixed> $form
   *
   * @return array<mixed>
   */
  private function buildSelectStep(array $form, FormStateInterface $form_state): array {
    /** @var array<string, mixed>|null $element */
    $element = $form_state->get('osm_element');
    if (!is_array($element)) {
      return $this->buildFindStep($form);
    }
    $options = [];
    foreach (array_keys(self::EDITABLE_TAGS) as $tag) {
      $options[$tag] = $this->tagLabel($tag);
    }
    $type = isset($element['type']) && is_scalar($element['type']) ? (string) $element['type'] : '';
    $id = isset($element['id']) && is_scalar($element['id']) ? (string) $element['id'] : '';
    $version = isset($element['version']) && is_scalar($element['version']) ? (string) $element['version'] : '';
    $lat = isset($element['lat']) && is_scalar($element['lat']) ? (string) $element['lat'] : '';
    $lon = isset($element['lon']) && is_scalar($element['lon']) ? (string) $element['lon'] : '';

    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Elemento encontrado'),
      '#markup' => $this->t('@type/@id (versão @version), @lat, @lon', [
        '@type' => $type,
        '@id' => $id,
        '@version' => $version,
        '@lat' => $lat,
        '@lon' => $lon,
      ]),
    ];
    /** @var array<string, string> $elementTags */
    $elementTags = isset($element['tags']) && is_array($element['tags']) ? $element['tags'] : [];
    $form['selected_tags'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Dados que serão importados e revisados'),
      '#options' => $options,
      '#default_value' => array_keys($elementTags),
      '#required' => TRUE,
    ];
    if (!isset($form['actions']) || !is_array($form['actions'])) {
      $form['actions'] = [];
    }
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
   * Builds the final review with an explicit confirmation before any OSM write.
   *
   * @param array<mixed> $form
   *
   * @return array<mixed>
   */
  private function buildReviewStep(array $form, FormStateInterface $form_state): array {
    /** @var array<string, mixed>|null $element */
    $element = $form_state->get('osm_element');
    /** @var list<string>|null $selected */
    $selected = $form_state->get('osm_selected_tags');
    if (!is_array($element) || !is_array($selected)) {
      return $this->buildFindStep($form);
    }
    $form['attribution'] = [
      '#type' => 'item',
      '#title' => $this->t('Atribuição'),
      '#markup' => $this->t('Dados © OpenStreetMap contributors, licença ODbL. Consulte <a href="https://www.openstreetmap.org/copyright">direitos autorais e licença do OpenStreetMap</a>.'),
    ];
    $lat = isset($element['lat']) && is_scalar($element['lat']) ? (string) $element['lat'] : '';
    $lon = isset($element['lon']) && is_scalar($element['lon']) ? (string) $element['lon'] : '';
    $form['coordinates'] = [
      '#type' => 'item',
      '#title' => $this->t('Coordenadas importadas'),
      '#markup' => Html::escape($lat) . ', ' . Html::escape($lon),
    ];
    /** @var array<string, string> $saved_values */
    $saved_values = is_array($form_state->get('osm_review_values')) ? $form_state->get('osm_review_values') : [];
    /** @var array<string, string> $elementTags */
    $elementTags = isset($element['tags']) && is_array($element['tags']) ? $element['tags'] : [];

    $selectedAddressTags = array_intersect(array_merge(array_keys(self::ADDRESS_TAGS), ['addr:housenumber']), $selected);
    if (!isset($form['review']) || !is_array($form['review'])) {
      $form['review'] = [];
    }
    if ($selectedAddressTags !== []) {
      $address = ['country_code' => 'BR'];
      foreach (self::ADDRESS_TAGS as $tag => $key) {
        $address[$key] = $saved_values[$tag] ?? $elementTags[$tag] ?? '';
      }
      $form['review']['address'] = [
        '#type' => 'address',
        '#title' => $this->t('Endereço do local'),
        '#default_value' => $address,
      ];
      AccountAddressForm::apply($form['review']['address'], '', FALSE, FALSE);
      if (!isset($form['#attached']) || !is_array($form['#attached'])) {
        $form['#attached'] = [];
      }
      if (!isset($form['#attached']['library']) || !is_array($form['#attached']['library'])) {
        $form['#attached']['library'] = [];
      }
      $form['#attached']['library'][] = AccountAddressForm::LIBRARY;

      // OSM aceita números alfanuméricos. Mantém o número em seu vocabulário,
      // enquanto os demais campos usam o widget central e a busca CEP.
      if (in_array('addr:housenumber', $selected, TRUE)) {
        $form['review']['housenumber'] = [
          '#type' => 'textfield',
          '#title' => $this->tagLabel('addr:housenumber'),
          '#maxlength' => 64,
          '#default_value' => $saved_values['addr:housenumber'] ?? $elementTags['addr:housenumber'] ?? '',
        ];
      }
    }
    foreach ($selected as $tag) {
      if (!isset(self::EDITABLE_TAGS[$tag]) || isset(self::ADDRESS_TAGS[$tag]) || $tag === 'addr:housenumber') {
        continue;
      }
      $form['review'][$tag] = [
        '#type' => 'textfield',
        '#title' => $this->tagLabel($tag),
        '#required' => $tag === 'name',
        '#maxlength' => 255,
        '#default_value' => $saved_values[$tag] ?? $elementTags[$tag] ?? '',
      ];
    }
    $rawComment = $form_state->get('osm_comment');
    $form['changeset_comment'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Comentário da alteração no OpenStreetMap'),
      '#description' => $this->t('Descreva a alteração para o histórico público do OSM; máximo de 255 caracteres.'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#default_value' => is_scalar($rawComment) ? (string) $rawComment : '',
    ];
    $form['confirm_send'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirmo a importação deste local e o envio das alterações revisadas à API do ambiente ativo do OpenStreetMap.'),
      '#required' => TRUE,
    ];
    if (!isset($form['actions']) || !is_array($form['actions'])) {
      $form['actions'] = [];
    }
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
      '#attributes' => ['data-portal-ui' => 'button-secondary'],
    ];
    return $form;
  }

  /**
   * Looks up the selected element only after an explicit user submission.
   *
   * @param array<mixed> $form
   */
  public function findSubmit(array &$form, FormStateInterface $form_state): void {
    try {
      $rawType = $form_state->getValue('element_type');
      $rawId = $form_state->getValue('element_id');
      $type = is_scalar($rawType) ? (string) $rawType : '';
      $id = is_scalar($rawId) ? (string) $rawId : '';
      $element = $this->osm->find($type, $id);
      $form_state->set('osm_element', $element);
      $form_state->set('osm_step', 'select');
      $form_state->setRebuild();
    }
    catch (OpenStreetMapOAuthException $exception) {
      $form_state->setErrorByName('element_id', $this->t('Não foi possível consultar esse elemento na API do ambiente ativo. Confira tipo e ID.'));
    }
  }

  /**
   * Stores the selected allowlisted tags before the review step.
   *
   * @param array<mixed> $form
   */
  public function selectSubmit(array &$form, FormStateInterface $form_state): void {
    $rawSelected = $form_state->getValue('selected_tags');
    $selected = [];
    if (is_array($rawSelected)) {
      foreach ($rawSelected as $item) {
        if (is_scalar($item) && (string) $item !== '') {
          $selected[] = (string) $item;
        }
      }
    }
    if (!in_array('name', $selected, TRUE)) {
      $form_state->setErrorByName('selected_tags', $this->t('Selecione o nome do local para criar o registro.'));
      return;
    }
    $form_state->set('osm_selected_tags', $selected);
    $form_state->set('osm_step', 'review');
    $form_state->setRebuild();
  }

  /**
   * Persists the reviewed location and submits only changed allowlisted tags.
   *
   * @param array<mixed> $form
   */
  public function saveSubmit(array &$form, FormStateInterface $form_state): void {
    /** @var array<string, mixed>|null $element */
    $element = $form_state->get('osm_element');
    /** @var list<string>|null $selected */
    $selected = $form_state->get('osm_selected_tags');
    if (!is_array($element) || !is_array($selected) || !$form_state->getValue('confirm_send')) {
      $form_state->setErrorByName('confirm_send', $this->t('A confirmação é obrigatória.'));
      return;
    }
    $rawReview = $form_state->getValue('review');
    /** @var array<string, mixed> $review */
    $review = is_array($rawReview) ? $rawReview : [];
    $reviewTags = $this->reviewedTags($selected, $review);
    /** @var array<string, string> $elementTags */
    $elementTags = isset($element['tags']) && is_array($element['tags']) ? $element['tags'] : [];
    $changes = [];
    foreach ($selected as $tag) {
      if (isset(self::EDITABLE_TAGS[$tag])) {
        $value = $reviewTags[$tag] ?? '';
        if ($value !== ($elementTags[$tag] ?? '')) {
          $changes[$tag] = $value;
        }
      }
    }

    try {
      $rawComment = $form_state->getValue('changeset_comment');
      $comment = is_scalar($rawComment) ? (string) $rawComment : '';
      $type = isset($element['type']) && is_scalar($element['type']) ? (string) $element['type'] : '';
      $id = isset($element['id']) && is_scalar($element['id']) ? (string) $element['id'] : '';
      $oldVersion = isset($element['version']) && is_scalar($element['version']) ? (string) $element['version'] : '';
      $updateTarget = [
        'type' => $type,
        'id' => $id,
        'version' => $oldVersion,
      ];
      $result = $changes === [] ? NULL : $this->osm->update($updateTarget, $changes, $comment);
      $newVersion = isset($result['version']) ? (string) $result['version'] : '';
      $version = $newVersion !== '' ? $newVersion : $oldVersion;
      $location = $this->createLocation($element, $selected, $review, $version);
      $location->save();
      $form_state->set('osm_step', 'done');
      $changesetMsg = isset($result['changeset'])
        ? 'Alterações enviadas na changeset ' . (string) $result['changeset'] . '.'
        : '';
      $this->messenger()->addStatus($this->t('Local importado. @result', [
        '@result' => $result === NULL ? 'Nenhuma diferença foi enviada ao OSM.' : $changesetMsg,
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
   * Resets all transient wizard data.
   *
   * @param array<mixed> $form
   */
  public function restartSubmit(array &$form, FormStateInterface $form_state): void {
    foreach (['osm_element', 'osm_selected_tags', 'osm_review_values', 'osm_comment'] as $key) {
      $form_state->set($key, NULL);
    }
    $form_state->set('osm_step', 'find');
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Each wizard stage defines its own submit handler on the action button.
  }

  /**
   * Saves only approved address tags and coordinates into an event location.
   *
   * @param array<string, mixed> $element
   * @param list<string> $selected
   * @param array<string, mixed> $review
   */
  private function createLocation(array $element, array $selected, array $review, string $version): EventLocation {
    $tags = $this->reviewedTags($selected, $review);
    $street = trim(($tags['addr:street'] ?? '') . ' ' . ($tags['addr:housenumber'] ?? ''));
    $type = isset($element['type']) && is_scalar($element['type']) ? (string) $element['type'] : '';
    $id = isset($element['id']) && is_scalar($element['id']) ? (string) $element['id'] : '';
    $lat = isset($element['lat']) && (is_numeric($element['lat']) || is_string($element['lat'])) ? (float) $element['lat'] : 0.0;
    $lon = isset($element['lon']) && (is_numeric($element['lon']) || is_string($element['lon'])) ? (float) $element['lon'] : 0.0;
    $title = $tags['name'] ?? ('Local OpenStreetMap ' . $type . '/' . $id);

    $location = $this->entityTypeManager->getStorage('aculta_event_location')->create([
      'title' => $title,
      'field_osm_id' => $type . '/' . $id,
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
        'lat' => $lat,
        'lon' => $lon,
        'value' => sprintf('POINT (%F %F)', $lon, $lat),
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
   * Maps the shared address widget's values back to the selected OSM tags.
   *
   * @param list<string> $selected
   *   OSM address tags selected for import/update.
   * @param array<string, mixed> $review
   *   Submitted values of the review step.
   *
   * @return array<string, string>
   *   Selected tag names mapped to reviewed values.
   */
  private function reviewedTags(array $selected, array $review): array {
    $rawAddress = $review['address'] ?? [];
    /** @var array<string, mixed> $address */
    $address = is_array($rawAddress) ? $rawAddress : [];
    $tags = [];
    foreach ($selected as $key) {
      if (isset(self::EDITABLE_TAGS[$key])) {
        if (isset(self::ADDRESS_TAGS[$key])) {
          $val = $address[self::ADDRESS_TAGS[$key]] ?? '';
          $tags[$key] = trim(is_scalar($val) ? (string) $val : '');
        }
        elseif ($key === 'addr:housenumber') {
          $val = $review['housenumber'] ?? '';
          $tags[$key] = trim(is_scalar($val) ? (string) $val : '');
        }
        else {
          $val = $review[$key] ?? '';
          $tags[$key] = trim(is_scalar($val) ? (string) $val : '');
        }
      }
    }
    return $tags;
  }

  /**
   * Translates the finite set of editable OSM tag labels.
   */
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
