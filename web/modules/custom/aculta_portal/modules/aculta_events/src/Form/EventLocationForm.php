<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\aculta_events\Access\EventPermissionPolicy;
use Drupal\aculta_events\Adapter\LocationContributionAdapter;
use Drupal\aculta_cgu\Service\ContributionLifecycle;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulário nativo para criação e edição de locais de evento (LOC-02).
 */
final class EventLocationForm extends ContentEntityForm {

  /**
   * Shared lifecycle for private community proposals.
   */
  protected ?ContributionLifecycle $contributions = NULL;

  /**
   * Owner adapter for Location payload validation and application.
   */
  protected ?LocationContributionAdapter $locationAdapter = NULL;

  /**
   * Constructs the Event Location form.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    protected readonly EventPermissionPolicy $permissionPolicy,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('aculta_events.permission_policy'),
    );
    $form->contributions = $container->has('aculta_cgu.contribution_lifecycle')
      ? $container->get('aculta_cgu.contribution_lifecycle')
      : NULL;
    $form->locationAdapter = $container->has('aculta_events.location_contribution_adapter')
      ? $container->get('aculta_events.location_contribution_adapter')
      : NULL;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    /** @var \Drupal\aculta_events\Entity\EventLocation $entity */
    $entity = $this->entity;

    // Se o organizador estiver vazio e não for admin, preenche com
    // o usuário corrente.
    if ($entity->isNew() && $entity->get('field_organizador')->isEmpty()) {
      $entity->set('field_organizador', $this->currentUser()->id());
    }

    // Aplica o formulário central de endereço brasileiro (D-54/D-55).
    if (isset($form['field_endereco'])) {
      $parts = AccountAddressForm::savedParts($entity->isNew() ? NULL : $entity, 'field_endereco');
      AccountAddressForm::apply($form['field_endereco'], $parts['number'], $parts['sn']);
      $form['#entity_builders'][] = [AccountAddressForm::class, 'buildStreetNumber'];
    }

    // Ao criar, quem não pode publicar fica restrito a rascunho. A edição de
    // um Local já publicado não é rebaixada por efeito colateral.
    if ($entity->isNew() && isset($form['field_status'])
      && !$this->permissionPolicy->canPublishLocation($this->currentUser())) {
      unset($form['field_status']['widget']['#options']['publicado']);
      $form['field_status']['widget']['#default_value'] = 'rascunho';
      $entity->set('field_status', 'rascunho');
    }

    if (!$this->currentUser()->hasPermission('administer aculta events')) {
      $form['contribution_review_notice'] = [
        '#type' => 'item',
        '#markup' => '<p>' . $this->t('A criação ou alteração será enviada para revisão. Um Local novo ficará em rascunho após aprovação e não será publicado automaticamente.') . '</p>',
        '#weight' => -100,
      ];
      $form['contribution_rationale'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Por que este Local deve ser criado ou alterado?'),
        '#description' => $this->t('O endereço e esta justificativa ficam privados para a equipe revisora até a decisão.'),
        '#required' => TRUE,
        '#maxlength' => 5000,
        '#weight' => 90,
      ];
      if (!$form_state->has('location_contribution_token')) {
        $form_state->set('location_contribution_token', bin2hex(random_bytes(32)));
      }
      $form['contribution_submission_token'] = [
        '#type' => 'value',
        '#value' => $form_state->get('location_contribution_token'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): ContentEntityInterface {
    $entity = parent::validateForm($form, $form_state);
    $site = trim((string) ($form_state->getValue(['field_site', 0, 'uri']) ?? ''));
    if ($site !== '') {
      $scheme = parse_url($site, PHP_URL_SCHEME);
      if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], TRUE)
        || preg_match('/[\x00-\x20<\x22\x3e\x7f]/', $site) === 1
        || filter_var($site, FILTER_VALIDATE_URL) === FALSE) {
        $form_state->setErrorByName('field_site', (string) $this->t('O site do Local precisa ser uma URL HTTP ou HTTPS válida.'));
      }
    }
    $values = $form_state->getValue('field_endereco');
    if (is_array($values)) {
      foreach ($values as $item) {
        $country = is_array($item) ? ($item['country_code'] ?? ($item['address']['country_code'] ?? NULL)) : NULL;
        if ($country !== NULL && $country !== '' && $country !== 'BR') {
          $form_state->setErrorByName('field_endereco', (string) $this->t('Apenas endereços no Brasil (BR) são suportados.'));
        }
      }
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\aculta_events\Entity\EventLocation $entity */
    $entity = $this->entity;

    $isAdmin = $this->currentUser()->hasPermission('administer aculta events');
    if (!$isAdmin) {
      if ($this->contributions === NULL || $this->locationAdapter === NULL) {
        throw new \LogicException('O serviço de propostas de Locais não está disponível.');
      }
      $intent = $entity->isNew() ? 'location_create' : 'location_update';
      $stored = $entity->isNew()
        ? NULL
        : $this->entityTypeManager->getStorage('aculta_event_location')->loadUnchanged($entity->id());
      $payload = [];
      $proposalFields = [
        'title',
        'field_tipo',
        'field_endereco',
        'field_precisao',
        'field_horario',
        'field_descricao',
        'field_telefone',
        'field_site',
      ];
      foreach ($proposalFields as $field) {
        if ($entity->hasField($field)) {
          $fieldValues = $entity->get($field)->getValue();
          $payload[$field] = in_array($field, ['field_tipo', 'field_endereco'], TRUE)
            ? $fieldValues
            : (string) ($fieldValues[0]['value'] ?? '');
        }
      }
      $this->contributions->submit(
        'events_location',
        $intent,
        $entity->isNew() ? NULL : (string) $entity->uuid(),
        (string) $this->t('@intent de Local: @name', [
          '@intent' => $entity->isNew() ? $this->t('Criação') : $this->t('Alteração'),
          '@name' => $entity->label(),
        ]),
        (string) $form_state->getValue('contribution_rationale'),
        $payload,
        (string) $form_state->getValue('contribution_submission_token'),
        [],
        $stored !== NULL ? $this->locationAdapter->currentVersion('location_update', $stored) : NULL,
      );
      $this->messenger()->addStatus($this->t('Sua proposta foi enviada para revisão. Nenhum dado público do Local foi alterado.'));
      $form_state->setRedirect('aculta_events.organizer_dashboard');
      return $entity->isNew() ? SAVED_NEW : SAVED_UPDATED;
    }

    if ($entity->isNew() && !$this->permissionPolicy->canPublishLocation($this->currentUser())) {
      $entity->set('field_status', 'rascunho');
    }

    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('O local %label foi cadastrado com sucesso.', [
        '%label' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('O local %label foi atualizado com sucesso.', [
        '%label' => $entity->label(),
      ]));
    }

    if ($entity->access('view', $this->currentUser())) {
      $form_state->setRedirect('aculta_events.location_canonical', [
        'aculta_event_location' => $entity->id(),
      ]);
    }
    else {
      $form_state->setRedirect('aculta_events.locations');
    }

    return $status;
  }

}
