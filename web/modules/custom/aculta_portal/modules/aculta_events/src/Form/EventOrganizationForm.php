<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Slug\EventSlugGenerator;
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
          'unclaimed' => $this->t('Cadastrar como organização não reivindicada (coletivo aberto ou parceiro sem titular cadastrado)'),
          'request_claim' => $this->t('Cadastrar e solicitar titularidade à moderação (análise prévia)'),
        ];
      }

      $form['is_claimed_choice'] = [
        '#type' => 'radios',
        '#title' => $this->t('Tipo de cadastro da organização'),
        '#options' => $options,
        '#default_value' => 'unclaimed',
        '#weight' => -10,
        '#description' => $this->t('Organizações não reivindicadas podem ser editadas por qualquer usuário autorizado a criar eventos e reivindicadas posteriormente mediante comprovação.'),
      ];

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
      if ($entity->isUnclaimed()) {
        $form['unclaimed_notice'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['messages', 'messages--warning']],
          '#markup' => $this->t('<strong>Atenção:</strong> Esta organização é pública e <em>não reivindicada</em>. Qualquer usuário autorizado a criar eventos pode editar suas informações até que um titular seja aprovado pela administração.'),
          '#weight' => -15,
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
        $justificativa = trim((string) $form_state->getValue('justificativa_reivindicacao'));
        if ($justificativa === '') {
          $form_state->setErrorByName('justificativa_reivindicacao', (string) $this->t('Informe a justificativa da solicitação de titularidade para análise da moderação.'));
        }
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
