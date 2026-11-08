<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\aculta_cgu\Service\ContributionLifecycle;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Reviews a generic contribution for an unclaimed organization profile. */
final class EventOrganizationContributionReviewForm extends FormBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    protected readonly EntityTypeManagerInterface $entities,
    #[Autowire(service: 'aculta_contributions.lifecycle')]
    protected readonly ContributionLifecycle $lifecycle,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_organization_contribution_review_form';
  }

  /**
   * Builds the review form for an authorized Organization contribution.
   *
   * @param array $form
   *   Existing form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   * @param \Drupal\aculta_cgu\Entity\Contribution|null $aculta_contribution
   *   Proposal being reviewed.
   *
   * @return array
   *   Render array for the review form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Contribution $aculta_contribution = NULL): array {
    $source = $aculta_contribution instanceof Contribution ? (string) $aculta_contribution->get('source')->value : '';
    $intent = $aculta_contribution instanceof Contribution ? (string) $aculta_contribution->get('intent')->value : '';
    $isOrganization = $source === 'events_organization'
      && in_array($intent, ['organization_update', 'organization_create'], TRUE);
    $isLocation = $source === 'events_location'
      && in_array($intent, ['location_update', 'location_create'], TRUE);
    $isEvent = $source === 'events_event'
      && in_array($intent, ['event_update', 'event_create'], TRUE);
    if (!$aculta_contribution instanceof Contribution
      || (!$isOrganization && !$isLocation && !$isEvent)
      || $aculta_contribution->get('state')->value !== 'submitted'
      || !$aculta_contribution->access('view', $this->currentUser())) {
      throw new NotFoundHttpException();
    }
    $form_state->set('contribution_id', (int) $aculta_contribution->id());
    $form_state->set('contribution_intent', $intent);
    $form_state->set('contribution_source', $source);
    $form['revision_id'] = ['#type' => 'value', '#value' => (int) $aculta_contribution->getRevisionId()];
    $target = NULL;
    $targetUuid = (string) $aculta_contribution->get('target_uuid')->value;
    if ($targetUuid !== '') {
      $targetType = $isEvent ? 'aculta_event' : ($isLocation ? 'aculta_event_location' : 'aculta_event_organization');
      $matches = $this->entities->getStorage($targetType)->loadByProperties(['uuid' => $targetUuid]);
      $target = reset($matches);
    }
    $payload = $aculta_contribution->getPayload();
    $rows = [];
    foreach ($payload as $field => $proposed) {
      if (!in_array($field, $this->allowedFields($source), TRUE)) {
        continue;
      }
      $isCreate = in_array($intent, ['organization_create', 'location_create', 'event_create'], TRUE);
      $current = $isCreate
        ? $this->t('Será criada após aprovação')
        : ($isEvent && $field === 'dates'
          ? $this->t('Datas oficiais existentes serão preservadas; as propostas são aditivas')
        : ($target !== FALSE && $target !== NULL
        ? ($field === 'title' ? $target->label() : $target->get($field)->value)
        : $this->t('Registro removido')));
      $rows[] = [
        'field' => ['#plain_text' => $this->fieldLabel($field)],
        'current' => ['#plain_text' => $this->displayValue($current, $field)],
        'proposed' => ['#plain_text' => $this->displayValue($proposed, $field)],
      ];
    }
    $form['changes'] = [
      '#type' => 'table',
      '#header' => [$this->t('Campo'), $this->t('Atual'), $this->t('Proposto')],
      '#rows' => $rows,
    ];
    if ($isLocation && array_key_exists('field_endereco', $payload)) {
      $form['reveal_private_address'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Revelar o endereço completo para esta revisão'),
        '#default_value' => FALSE,
        '#description' => $this->t('Endereço e CEP são dados privados. Revele somente se necessário para avaliar esta proposta.'),
      ];
      if (!$form_state->getValue('reveal_private_address')
        || !$this->currentUser()->hasPermission('administer aculta events')) {
        foreach ($form['changes']['#rows'] as &$row) {
          if (($row['field']['#plain_text'] ?? '') === $this->fieldLabel('field_endereco')) {
            $row['proposed']['#plain_text'] = (string) $this->t('Endereço privado — revele somente se necessário');
          }
        }
        unset($row);
      }
    }
    $form['rationale'] = [
      '#type' => 'item',
      '#title' => $this->t('Justificativa privada'),
      '#plain_text' => (string) $aculta_contribution->get('rationale')->value,
    ];
    $form['decision'] = [
      '#type' => 'select',
      '#title' => $this->t('Decisão'),
      '#options' => [
        'needs_changes' => $this->t('Pedir ajustes'),
        'declined' => $this->t('Recusar'),
        'accepted' => $this->t('Aceitar campos selecionados'),
      ],
      '#required' => TRUE,
    ];
    $form['selected_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Campos a aplicar'),
      '#options' => $this->fieldOptions($payload, $source),
      '#states' => [
        'visible' => [':input[name="decision"]' => ['value' => 'accepted']],
      ],
    ];
    $form['decision_note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Retorno para a pessoa autora'),
      '#required' => TRUE,
      '#maxlength' => 5000,
    ];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Registrar decisão')];
    return $form;
  }

  /**
   * Validates the reviewer decision and required selected fields.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $decision = (string) $form_state->getValue('decision');
    $note = trim((string) $form_state->getValue('decision_note'));
    if (!in_array($decision, ['needs_changes', 'declined', 'accepted'], TRUE) || $note === '' || mb_strlen($note) > 5000) {
      $form_state->setErrorByName('decision_note', $this->t('Escolha uma decisão e informe um retorno de até 5.000 caracteres.'));
    }
    if ($decision === 'accepted' && array_filter((array) $form_state->getValue('selected_fields')) === []) {
      $form_state->setErrorByName('selected_fields', $this->t('Selecione ao menos um campo para aplicar.'));
    }
    $createIntents = ['organization_create', 'location_create', 'event_create'];
    if ($decision === 'accepted' && in_array($form_state->get('contribution_intent'), $createIntents, TRUE)
      && !in_array('title', array_filter((array) $form_state->getValue('selected_fields')), TRUE)) {
      $message = $form_state->get('contribution_source') === 'events_location'
        ? $this->t('Selecione o nome para criar o Local.')
        : ($form_state->get('contribution_source') === 'events_event'
          ? $this->t('Selecione o título para criar o Evento.')
          : $this->t('Selecione o nome para criar a organização.'));
      $form_state->setErrorByName('selected_fields', $message);
    }
  }

  /**
   * Records the decision through the shared contribution lifecycle.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $proposal = $this->entities->getStorage('aculta_contribution')->load((int) $form_state->get('contribution_id'));
    if (!$proposal instanceof Contribution || !$proposal->access('view', $this->currentUser())) {
      throw new AccessDeniedHttpException();
    }
    try {
      $fields = array_values(array_filter((array) $form_state->getValue('selected_fields')));
      $this->lifecycle->decide(
        $proposal,
        (string) $form_state->getValue('decision'),
        (string) $form_state->getValue('decision_note'),
        $fields,
        (int) $form_state->getValue('revision_id'),
      );
      $this->messenger()->addStatus($this->t('A decisão foi registrada.'));
    }
    catch (ConflictHttpException | UnprocessableEntityHttpException $exception) {
      $form_state->setErrorByName('decision', $exception->getMessage());
      return;
    }
    $route = match ($form_state->get('contribution_source')) {
      'events_location' => 'aculta_events.admin_location_contributions',
      'events_event' => 'aculta_events.admin_event_contributions',
      default => 'aculta_events.admin_organization_suggestions',
    };
    $form_state->setRedirect($route);
  }

  /**
   * Returns a translated label for an allowlisted profile field.
   *
   * @param string $field
   *   Field machine name.
   *
   * @return string
   *   Human-readable field label.
   */
  private function fieldLabel(string $field): string {
    return match ($field) {
      'title' => (string) $this->t('Nome'),
      'body' => (string) $this->t('Descrição'),
      'imagem' => (string) $this->t('Imagem ou logotipo'),
      'links' => (string) $this->t('Links oficiais'),
      'localidade' => (string) $this->t('Cidade'),
      'uf' => (string) $this->t('UF'),
      'ocultar_localidade' => (string) $this->t('Ocultar localidade'),
      'field_tipo' => (string) $this->t('Tipo do Local'),
      'field_endereco' => (string) $this->t('Endereço e CEP (Brasil)'),
      'field_precisao' => (string) $this->t('Precisão pública'),
      'field_horario' => (string) $this->t('Horário de funcionamento'),
      'field_descricao' => (string) $this->t('Descrição'),
      'field_telefone' => (string) $this->t('Telefone de contato'),
      'field_site' => (string) $this->t('Site ou página do Local'),
      'field_subtitulo' => (string) $this->t('Subtítulo'),
      'field_url' => (string) $this->t('Site do Evento'),
      'field_organizacao' => (string) $this->t('Organização responsável'),
      'field_coorganizadores' => (string) $this->t('Organizações coorganizadoras'),
      'dates' => (string) $this->t('Datas e horários'),
      default => $field,
    };
  }

  /**
   * Formats a scalar value for safe review display.
   *
   * @param mixed $value
   *   Value from an allowlisted profile field.
   * @param string $field
   *   Field machine name for structured value formatting.
   *
   * @return string
   *   Display string.
   */
  private function displayValue(mixed $value, string $field = ''): string {
    if (is_array($value) && in_array($field, ['field_organizacao', 'field_coorganizadores', 'dates'], TRUE)) {
      return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
    if ($field === 'field_tipo' && is_array($value)) {
      $targetId = $value[0]['target_id'] ?? $value['target_id'] ?? NULL;
      $term = $targetId ? $this->entities->getStorage('taxonomy_term')->load($targetId) : NULL;
      return $term ? (string) $term->label() : '';
    }
    if ($field === 'field_endereco' && is_array($value)) {
      $address = array_is_list($value) ? ($value[0] ?? []) : $value;
      if (!is_array($address)) {
        return '';
      }
      return implode(', ', array_filter([
        $address['address_line1'] ?? '',
        $address['address_line2'] ?? '',
        $address['dependent_locality'] ?? '',
        $address['locality'] ?? '',
        $address['administrative_area'] ?? '',
        $address['postal_code'] ?? '',
      ], static fn(mixed $part): bool => is_string($part) && trim($part) !== ''));
    }
    if (is_bool($value) || $value === 0 || $value === 1) {
      return $value ? (string) $this->t('Sim') : (string) $this->t('Não');
    }
    return is_scalar($value) ? (string) $value : '';
  }

  /**
   * Builds labels only for the adapter's public profile allowlist. */
  private function fieldOptions(array $payload, string $source): array {
    $fields = array_intersect(array_keys($payload), $this->allowedFields($source));
    $options = [];
    foreach ($fields as $field) {
      $options[$field] = $this->fieldLabel($field);
    }
    return $options;
  }

  /**
   * Returns the explicit field allowlist for a proposal owner.
   *
   * @param string $source
   *   Stable adapter source ID.
   *
   * @return list<string>
   *   Allowed field machine names.
   */
  private function allowedFields(string $source): array {
    if ($source === 'events_event') {
      return ['title', 'field_subtitulo', 'body', 'field_url', 'field_organizacao', 'field_coorganizadores', 'dates'];
    }
    if ($source === 'events_location') {
      return [
        'title',
        'field_tipo',
        'field_endereco',
        'field_precisao',
        'field_horario',
        'field_descricao',
        'field_telefone',
        'field_site',
      ];
    }
    return ['title', 'body', 'imagem', 'links', 'localidade', 'uf', 'ocultar_localidade'];
  }

}
