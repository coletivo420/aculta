<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Slug\EventSlugGenerator;
use Drupal\aculta_events\Organization\OrganizationSuggestionService;
use Drupal\aculta_events\Adapter\OrganizationContributionAdapter;
use Drupal\aculta_cgu\Service\ContributionLifecycle;
use Drupal\aculta_user\Organization\OrganizationMembershipManager;
use Drupal\aculta_user\Organization\OwnershipClaimService;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulário para cadastro e edição de Organização Cultural (ARQ-05).
 */
final class EventOrganizationForm extends ContentEntityForm {

  /**
   * Gerenciador de membros de organização.
   */
  protected ?OrganizationMembershipManager $membershipManager = NULL;

  /**
   * Serviço de reivindicação de titularidade.
   */
  protected ?OwnershipClaimService $claimService = NULL;

  /**
   * Serviço de propostas moderadas de alteração.
   */
  protected ?OrganizationSuggestionService $suggestionService = NULL;

  /**
   * Shared proposal lifecycle for the generic contribution storage. */
  protected ?ContributionLifecycle $contributions = NULL;

  /**
   * Organization adapter used to capture the profile's base version. */
  protected ?OrganizationContributionAdapter $organizationAdapter = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    if ($container->has('aculta_user.organization_membership_manager')) {
      $instance->membershipManager = $container->get('aculta_user.organization_membership_manager');
    }
    if ($container->has('aculta_user.ownership_claim_service')) {
      $instance->claimService = $container->get('aculta_user.ownership_claim_service');
    }
    if ($container->has('aculta_events.organization_suggestion')) {
      $instance->suggestionService = $container->get('aculta_events.organization_suggestion');
    }
    if ($container->has('aculta_contributions.lifecycle')) {
      $instance->contributions = $container->get('aculta_contributions.lifecycle');
    }
    if ($container->has('aculta_events.organization_contribution_adapter')) {
      $instance->organizationAdapter = $container->get('aculta_events.organization_contribution_adapter');
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    /** @var \Drupal\aculta_events\Entity\EventOrganization $entity */
    $entity = $this->entity;

    if ($entity->isNew()) {
      $isAdmin = $this->currentUser()->hasPermission('administer aculta events');
      if ($isAdmin) {
        $options = [
          'unclaimed' => $this->t('Cadastrar como organização não reivindicada (coletivo aberto ou parceiro sem titular cadastrado)'),
          'claimed' => $this->t('Cadastrar com titularidade imediata (administrador)'),
          'request_claim' => $this->t('Cadastrar e registrar solicitação de titularidade pendente para moderação'),
        ];
      }
      else {
        $options = [
          'unclaimed' => $this->t('Propor criação como organização não reivindicada'),
        ];
      }

      $form['is_claimed_choice'] = [
        '#type' => 'radios',
        '#title' => $this->t('Tipo de cadastro da organização'),
        '#options' => $options,
        '#default_value' => 'unclaimed',
        '#weight' => -10,
        '#description' => $this->t('Alterações em organizações sem titular são enviadas como sugestões para aprovação administrativa. A titularidade exige análise própria.'),
      ];

      if (!$isAdmin) {
        $form['create_review_notice'] = [
          '#type' => 'item',
          '#markup' => '<p>' . $this->t('A organização só será criada e ficará pública após revisão. Depois da aprovação, você poderá solicitar titularidade pelo fluxo próprio de reivindicação.') . '</p>',
          '#weight' => -8,
        ];
        $form['contribution_rationale'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Por que esta organização deve ser cadastrada?'),
          '#description' => $this->t('Esta justificativa ficará privada para a equipe revisora.'),
          '#required' => TRUE,
          '#maxlength' => 5000,
          '#weight' => 80,
        ];
        if (!$form_state->has('organization_contribution_token')) {
          $form_state->set('organization_contribution_token', bin2hex(random_bytes(32)));
        }
        $form['contribution_submission_token'] = [
          '#type' => 'value',
          '#value' => $form_state->get('organization_contribution_token'),
        ];
      }

      $form['justificativa_reivindicacao'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Justificativa da solicitação de titularidade'),
        '#description' => $this->t('Explique seu vínculo ou representação oficial da organização para análise da moderação.'),
        '#rows' => 3,
        '#default_value' => (string) ($form_state->getValue('justificativa_reivindicacao') ?? ''),
        '#states' => [
          'visible' => [
            ':input[name="is_claimed_choice"]' => ['value' => 'request_claim'],
          ],
          'required' => [
            ':input[name="is_claimed_choice"]' => ['value' => 'request_claim'],
          ],
        ],
        '#weight' => -9,
      ];

      $form['contato_reivindicacao'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Contato institucional / Comprovação (opcional)'),
        '#description' => $this->t('Link oficial, e-mail institucional ou telefone para confirmação.'),
        '#default_value' => (string) ($form_state->getValue('contato_reivindicacao') ?? ''),
        '#states' => [
          'visible' => [
            ':input[name="is_claimed_choice"]' => ['value' => 'request_claim'],
          ],
        ],
        '#weight' => -8,
      ];
    }
    else {
      $isAdmin = $this->currentUser()->hasPermission('administer aculta events');
      if ($entity->isUnclaimed() && !$isAdmin) {
        $form['unclaimed_notice'] = [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'notice-warning'],
          '#markup' => $this->t('<strong>Organização sem titular:</strong> suas alterações serão enviadas como sugestão e só aparecerão no perfil público após aprovação administrativa.'),
          '#weight' => -15,
        ];
        if (isset($form['actions']['submit'])) {
          $form['actions']['submit']['#value'] = $this->t('Enviar alterações para aprovação');
        }
        if (isset($form['actions']['delete'])) {
          $form['actions']['delete']['#access'] = FALSE;
        }
        $form['contribution_rationale'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Por que o perfil deve ser atualizado?'),
          '#description' => $this->t('Esta justificativa fica privada para a equipe que revisa a proposta.'),
          '#required' => TRUE,
          '#maxlength' => 5000,
          '#weight' => 90,
        ];
        if ($this->organizationAdapter !== NULL) {
          $form['contribution_base_version'] = [
            '#type' => 'value',
            '#value' => $this->organizationAdapter->currentVersion('organization_update', $entity),
          ];
        }
        if (!$form_state->has('organization_contribution_token')) {
          $form_state->set('organization_contribution_token', bin2hex(random_bytes(32)));
        }
        $form['contribution_submission_token'] = [
          '#type' => 'value',
          '#value' => $form_state->get('organization_contribution_token'),
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    if ($this->entity->isNew()) {
      $choice = (string) $form_state->getValue('is_claimed_choice', 'unclaimed');
      $isAdmin = $this->currentUser()->hasPermission('administer aculta events');
      if ($choice === 'claimed' && !$isAdmin) {
        $form_state->setErrorByName('is_claimed_choice', (string) $this->t('Apenas administradores podem conceder titularidade imediata no cadastro.'));
      }
      if ($choice === 'request_claim') {
        if (!$isAdmin) {
          $form_state->setErrorByName('is_claimed_choice', (string) $this->t('A titularidade deve ser solicitada pelo fluxo próprio após a aprovação da criação.'));
        }
        $justificativa = trim((string) $form_state->getValue('justificativa_reivindicacao'));
        if ($justificativa === '') {
          $form_state->setErrorByName('justificativa_reivindicacao', (string) $this->t('Informe a justificativa da solicitação de titularidade para análise da moderação.'));
        }
      }
      if (!$isAdmin && $choice !== 'unclaimed') {
        $form_state->setErrorByName('is_claimed_choice', (string) $this->t('A proposta deve ser de uma organização não reivindicada.'));
      }
      if (!$isAdmin && trim((string) $form_state->getValue('contribution_rationale')) === '') {
        $form_state->setErrorByName('contribution_rationale', (string) $this->t('Informe por que esta organização deve ser cadastrada.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\aculta_events\Entity\EventOrganization $entity */
    $entity = $this->entity;
    $isNew = $entity->isNew();
    $currentUid = (int) $this->currentUser()->id();

    if ($entity->get('field_slug')->isEmpty()) {
      $entity->set('field_slug', EventSlugGenerator::fromTitle((string) $entity->label()));
    }

    $choice = 'unclaimed';
    $isAdmin = $this->currentUser()->hasPermission('administer aculta events');
    /** @var \Drupal\aculta_events\Entity\EventOrganization|null $storedOrganization */
    $storedOrganization = !$isNew
      ? $this->entityTypeManager->getStorage('aculta_event_organization')->loadUnchanged($entity->id())
      : NULL;

    if ($isNew && !$isAdmin) {
      if ($this->contributions === NULL || $this->organizationAdapter === NULL) {
        throw new \LogicException('O serviço de propostas de organizações não está disponível.');
      }
      $payload = $this->organizationAdapter->validatePayload('organization_create', [
        'title' => (string) $entity->label(),
        'body' => (string) $entity->get('body')->value,
        'imagem' => (string) $entity->get('imagem')->value,
        'links' => (string) $entity->get('links')->value,
        'localidade' => (string) $entity->get('localidade')->value,
        'uf' => strtoupper((string) $entity->get('uf')->value),
        'ocultar_localidade' => (bool) $entity->get('ocultar_localidade')->value,
      ]);
      $this->contributions->submit(
        'events_organization',
        'organization_create',
        NULL,
        (string) $this->t('Criação da organização @name', ['@name' => $entity->label()]),
        (string) $form_state->getValue('contribution_rationale'),
        $payload,
        (string) $form_state->getValue('contribution_submission_token'),
      );
      $this->messenger()->addStatus($this->t('Sua proposta foi enviada. A organização será criada somente após a aprovação.'));
      $form_state->setRedirect('aculta_events.organizer_dashboard');
      return SAVED_NEW;
    }

    if (!$isNew && !$isAdmin) {
      if ($storedOrganization === NULL) {
        throw new \RuntimeException('Esta organização não aceita edição direta por esta conta.');
      }
      if ($storedOrganization->isUnclaimed()) {
        if ($this->contributions === NULL || $this->organizationAdapter === NULL) {
          throw new \LogicException('O serviço de propostas de organizações não está disponível.');
        }
        $changes = [
          'title' => (string) $entity->label(),
          'body' => (string) $entity->get('body')->value,
          'imagem' => (string) $entity->get('imagem')->value,
          'links' => (string) $entity->get('links')->value,
          'localidade' => (string) $entity->get('localidade')->value,
          'uf' => (string) $entity->get('uf')->value,
          'ocultar_localidade' => (bool) $entity->get('ocultar_localidade')->value,
        ];
        $changes = $this->organizationAdapter->validatePayload('organization_update', $changes, $storedOrganization);
        $this->contributions->submit(
          'events_organization',
          'organization_update',
          (string) $storedOrganization->uuid(),
          (string) $this->t('Atualização do perfil de @name', ['@name' => $storedOrganization->label()]),
          (string) $form_state->getValue('contribution_rationale'),
          $changes,
          (string) $form_state->getValue('contribution_submission_token'),
          [],
          (string) $form_state->getValue('contribution_base_version'),
        );
        $this->messenger()->addStatus($this->t('Sua proposta foi enviada. O perfil público permanece igual até a aprovação administrativa.'));
        $form_state->setRedirect('aculta_events.organizer_dashboard');
        return SAVED_UPDATED;
      }
      if (!$storedOrganization->access('update', $this->currentUser())) {
        throw new \RuntimeException('Esta organização não aceita edição direta por esta conta.');
      }
    }

    if ($isNew) {
      $choice = (string) $form_state->getValue('is_claimed_choice', 'unclaimed');
      if ($choice === 'claimed' && $isAdmin) {
        $entity->set('reivindicada', TRUE);
        $entity->set('reivindicacao_status', 'confirmada');
        $entity->set('titular', $currentUid);
        $entity->set('field_organizador', $currentUid);
      }
      elseif ($choice === 'request_claim') {
        $entity->set('reivindicada', FALSE);
        $entity->set('reivindicacao_status', 'pendente');
        $entity->set('titular', NULL);
        $entity->set('field_organizador', $currentUid);
      }
      else {
        $entity->set('reivindicada', FALSE);
        $entity->set('reivindicacao_status', 'nao_reivindicada');
        $entity->set('titular', NULL);
        $entity->set('field_organizador', $currentUid);
      }
    }

    $result = parent::save($form, $form_state);

    if ($isNew && $entity->isClaimed() && $currentUid > 0) {
      if ($this->membershipManager !== NULL) {
        $this->membershipManager->addMembership($currentUid, (string) $entity->id(), 'owner', 'approved', TRUE, TRUE);
      }
    }
    elseif ($isNew && $choice === 'request_claim' && $currentUid > 0) {
      $justificativa = trim((string) $form_state->getValue('justificativa_reivindicacao'));
      $contato = trim((string) $form_state->getValue('contato_reivindicacao'));
      if ($this->claimService !== NULL) {
        $this->claimService->submitClaim($entity, $this->currentUser(), $justificativa, $contato !== '' ? $contato : NULL);
      }
      $this->messenger()->addStatus($this->t('Solicitação de titularidade registrada para moderação.'));
    }

    $this->messenger()->addStatus($this->t('Organização %label salva com sucesso.', ['%label' => $entity->label()]));
    $currentRoute = (string) $this->getRouteMatch()->getRouteName();
    if (str_contains($currentRoute, 'admin')) {
      $form_state->setRedirect('aculta_events.admin_organizations');
    }
    else {
      $form_state->setRedirect('aculta_events.organizer_dashboard');
    }

    return $result;
  }

}
