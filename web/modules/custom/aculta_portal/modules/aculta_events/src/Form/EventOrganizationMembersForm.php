<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_user\Organization\OrganizationMembershipManager;
use Drupal\aculta_user\Organization\OwnershipClaimService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Gestão de membros, papéis e delegações de Organização (ARQ-05).
 */
final class EventOrganizationMembersForm extends FormBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_user.organization_membership_manager')]
    private readonly ?OrganizationMembershipManager $memberManager = NULL,
    #[Autowire(service: 'aculta_user.ownership_claim_service')]
    private readonly ?OwnershipClaimService $claimService = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->has('aculta_user.organization_membership_manager') ? $container->get('aculta_user.organization_membership_manager') : NULL,
      $container->has('aculta_user.ownership_claim_service') ? $container->get('aculta_user.ownership_claim_service') : NULL,
    );
  }

  /**
   * Obtém o serviço de gestão de membros.
   */
  private function getMemberManager(): ?OrganizationMembershipManager {
    return $this->memberManager;
  }

  /**
   * Obtém o serviço de reivindicação de posse.
   */
  private function getClaimService(): ?OwnershipClaimService {
    return $this->claimService;
  }

  /**
   * Obtém o gerenciador de tipos de entidade.
   */
  private function getEntityTypeMgr(): EntityTypeManagerInterface {
    return $this->entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_organization_members_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EventOrganization $aculta_event_organization = NULL): array {
    if ($aculta_event_organization === NULL) {
      return $form;
    }

    $form_state->set('organization', $aculta_event_organization);
    $currentUid = (int) $this->currentUser()->id();
    $orgId = (string) $aculta_event_organization->id();
    $isTitular = $aculta_event_organization->getOwnerId() === $currentUid;
    $pendingOwnerUid = (int) $aculta_event_organization->get('pending_owner_uid')->target_id;

    $form['title'] = [
      '#markup' => '<h2>' . $this->t('Membros e acessos de %org', ['%org' => $aculta_event_organization->label()]) . '</h2>',
    ];

    // Banner de convite de transferência de titularidade pendente.
    if ($pendingOwnerUid > 0 && $pendingOwnerUid === $currentUid) {
      $form['transfer_invite'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'notice' => [
          '#markup' => '<p>' . $this->t('<strong>Convite de titularidade:</strong> Você foi convidado a assumir a titularidade desta organização.') . '</p>',
        ],
        'accept_transfer' => [
          '#type' => 'submit',
          '#value' => $this->t('Aceitar titularidade'),
          '#submit' => ['::submitAcceptTransfer'],
        ],
        'reject_transfer' => [
          '#type' => 'submit',
          '#value' => $this->t('Recusar convite'),
          '#submit' => ['::submitRejectTransfer'],
        ],
      ];
    }

    $memberManager = $this->getMemberManager();
    if ($memberManager !== NULL) {

      // 1. Membros suspensos em revisão pós-reivindicação.
      $suspended = $memberManager->getOrgMembers($orgId, 'suspended');
      if (!empty($suspended) && $aculta_event_organization->getClaimStatus() === 'em_revisao') {
        $form['review_section'] = [
          '#type' => 'details',
          '#title' => $this->t('Revisão de acessos anteriores (%count pendentes)', ['%count' => count($suspended)]),
          '#open' => TRUE,
        ];
        $form['review_section']['intro'] = [
          '#markup' => '<p>' . $this->t('Após a aprovação da reivindicação, todos os acessos anteriores foram preventivamente suspensos. Confirme ou revogue cada um para garantir a segurança da organização.') . '</p>',
        ];

        foreach ($suspended as $member) {
          $memberUser = $this->getEntityTypeMgr()->getStorage('user')->load($member->get('uid')->target_id);
          $name = $memberUser ? $memberUser->getDisplayName() : ('UID ' . $member->get('uid')->target_id);
          $mId = $member->id();

          $form['review_section']['m_' . $mId] = [
            '#type' => 'fieldset',
            '#title' => $name,
            'role_' . $mId => [
              '#type' => 'select',
              '#title' => $this->t('Papel confirmado'),
              '#options' => [
                'co_owner' => $this->t('Coproprietário (edição plena e poderes configuráveis)'),
                'publisher' => $this->t('Publicador (edição e publicação de eventos)'),
                'editor' => $this->t('Editor (edição sem poderes de publicação direta)'),
                'member' => $this->t('Membro (sem poderes de edição)'),
              ],
              '#default_value' => 'editor',
            ],
            'can_manage_' . $mId => [
              '#type' => 'checkbox',
              '#title' => $this->t('Poder delegado para gerenciar membros'),
              '#default_value' => FALSE,
            ],
            'can_publish_' . $mId => [
              '#type' => 'checkbox',
              '#title' => $this->t('Poder delegado para publicar eventos'),
              '#default_value' => FALSE,
            ],
            'confirm_' . $mId => [
              '#type' => 'submit',
              '#value' => $this->t('Confirmar acesso'),
              '#submit' => ['::submitConfirmSuspended'],
              '#name' => 'btn_confirm_' . $mId,
            ],
            'revoke_' . $mId => [
              '#type' => 'submit',
              '#value' => $this->t('Revogar acesso'),
              '#submit' => ['::submitRevokeMember'],
              '#name' => 'btn_revoke_' . $mId,
            ],
          ];
        }

        if ($isTitular) {
          $form['review_section']['complete_review'] = [
            '#type' => 'submit',
            '#value' => $this->t('Concluir revisão de acessos'),
            '#submit' => ['::submitCompleteReview'],
            '#attributes' => ['class' => ['button--primary']],
          ];
        }
      }

      // 2. Tabela de membros aprovados.
      $approved = $memberManager->getOrgMembers($orgId, 'approved');
      $rows = [];
      $coOwners = [];
      foreach ($approved as $member) {
        $memberUser = $this->getEntityTypeMgr()->getStorage('user')->load($member->get('uid')->target_id);
        $name = $memberUser ? $memberUser->getDisplayName() : ('UID ' . $member->get('uid')->target_id);
        $roleLabel = match ($member->getRoleInOrg()) {
          'owner' => $this->t('Titular'),
          'co_owner' => $this->t('Coproprietário'),
          'publisher' => $this->t('Publicador'),
          'editor' => $this->t('Editor'),
          default => $this->t('Membro'),
        };

        if ($member->getRoleInOrg() === 'co_owner' && $member->get('uid')->target_id != $currentUid) {
          $coOwners[$member->get('uid')->target_id] = $name;
        }

        $powers = [];
        if ($member->getRoleInOrg() === 'owner') {
          $powers[] = $this->t('Titular (controle total)');
        }
        else {
          if ($member->canManageMembers()) {
            $powers[] = $this->t('Gerencia acessos');
          }
          if ($member->canPublish() || $member->getRoleInOrg() === 'publisher') {
            $powers[] = $this->t('Publica eventos');
          }
        }

        $rows[] = [
          'name' => $name,
          'role' => $roleLabel,
          'powers' => implode(', ', $powers) ?: '-',
        ];
      }

      $form['members_table'] = [
        '#type' => 'table',
        '#header' => [$this->t('Pessoa'), $this->t('Papel'), $this->t('Poderes')],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhum membro ativo.'),
      ];

      // 3. Adicionar novo membro ou delegado.
      $form['add_member'] = [
        '#type' => 'details',
        '#title' => $this->t('Conceder novo acesso ou delegação'),
        '#open' => FALSE,
      ];
      $form['add_member']['new_user'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'user',
        '#title' => $this->t('Conta do usuário'),
      ];
      $form['add_member']['new_role'] = [
        '#type' => 'select',
        '#title' => $this->t('Papel'),
        '#options' => [
          'co_owner' => $this->t('Coproprietário'),
          'publisher' => $this->t('Publicador'),
          'editor' => $this->t('Editor'),
          'member' => $this->t('Membro'),
        ],
        '#default_value' => 'editor',
      ];
      $form['add_member']['new_can_manage'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Conceder capacidade de gerenciar membros e acessos'),
        '#default_value' => FALSE,
      ];
      $form['add_member']['new_can_publish'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Conceder permissão de publicar ou despublicar eventos'),
        '#default_value' => FALSE,
      ];
      $form['add_member']['submit_add'] = [
        '#type' => 'submit',
        '#value' => $this->t('Adicionar membro'),
        '#submit' => ['::submitAddMember'],
      ];

      // 4. Transferência de titularidade para coproprietário.
      if ($isTitular && !empty($coOwners)) {
        $form['transfer_section'] = [
          '#type' => 'details',
          '#title' => $this->t('Transferência de titularidade'),
          '#open' => FALSE,
        ];
        $form['transfer_section']['transfer_coowner'] = [
          '#type' => 'select',
          '#title' => $this->t('Coproprietário sucessor'),
          '#options' => $coOwners,
        ];
        $form['transfer_section']['submit_transfer'] = [
          '#type' => 'submit',
          '#value' => $this->t('Iniciar transferência de titularidade'),
          '#submit' => ['::submitInitiateTransfer'],
        ];
      }
    }

    $isAdmin = str_contains((string) $this->getRouteMatch()->getRouteName(), 'admin');
    $backRoute = $isAdmin ? 'aculta_events.admin_organizations' : 'aculta_events.organizer_dashboard';
    $backTitle = $isAdmin ? $this->t('Voltar às organizações') : $this->t('Voltar ao Studio Culturas');
    $form['back'] = [
      '#type' => 'link',
      '#title' => $backTitle,
      '#url' => Url::fromRoute($backRoute),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Adiciona um novo membro à organização.
   */
  public function submitAddMember(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\aculta_events\Entity\EventOrganization $org */
    $org = $form_state->get('organization');
    $uid = (int) $form_state->getValue('new_user');
    $role = (string) $form_state->getValue('new_role', 'editor');
    $canManage = (bool) $form_state->getValue('new_can_manage', FALSE);
    $canPublish = (bool) $form_state->getValue('new_can_publish', FALSE);

    $memberManager = $this->getMemberManager();
    if ($uid > 0 && $memberManager !== NULL) {
      $memberManager->addMembership($uid, (string) $org->id(), $role, 'approved', $canManage, $canPublish);
      $this->messenger()->addStatus($this->t('Membro adicionado com sucesso.'));
    }
  }

  /**
   * Confirma um membro suspenso na revisão de pós-reivindicação.
   */
  public function submitConfirmSuspended(array &$form, FormStateInterface $form_state): void {
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    $mId = (int) str_replace('btn_confirm_', '', $trigger);
    $memberManager = $this->getMemberManager();
    if ($mId > 0 && $memberManager !== NULL) {
      /** @var \Drupal\aculta_user\Entity\OrganizationMember|null $member */
      $member = $this->getEntityTypeMgr()->getStorage('aculta_org_member')->load($mId);
      if ($member !== NULL) {
        $role = (string) $form_state->getValue('role_' . $mId, 'editor');
        $canManage = (bool) $form_state->getValue('can_manage_' . $mId, FALSE);
        $canPublish = (bool) $form_state->getValue('can_publish_' . $mId, FALSE);
        $memberManager->confirmSuspendedMember($member, $role, $canManage, $canPublish, $this->currentUser());
        $this->messenger()->addStatus($this->t('Acesso confirmado com sucesso.'));
      }
    }
  }

  /**
   * Revoga um membro durante a revisão de pós-reivindicação.
   */
  public function submitRevokeMember(array &$form, FormStateInterface $form_state): void {
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    $mId = (int) str_replace('btn_revoke_', '', $trigger);
    $memberManager = $this->getMemberManager();
    if ($mId > 0 && $memberManager !== NULL) {
      /** @var \Drupal\aculta_user\Entity\OrganizationMember|null $member */
      $member = $this->getEntityTypeMgr()->getStorage('aculta_org_member')->load($mId);
      if ($member !== NULL) {
        $memberManager->revokeMembership($member, $this->currentUser(), 'Revogado pelo titular durante revisão.');
        $this->messenger()->addStatus($this->t('Acesso revogado com sucesso.'));
      }
    }
  }

  /**
   * Conclui a revisão de pós-reivindicação da organização.
   */
  public function submitCompleteReview(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\aculta_events\Entity\EventOrganization $org */
    $org = $form_state->get('organization');
    $org->set('reivindicacao_status', 'confirmada');
    $org->save();
    $this->messenger()->addStatus($this->t('Revisão de acessos concluída. A organização está plenamente confirmada.'));
  }

  /**
   * Inicia o processo de transferência de titularidade para um coproprietário.
   */
  public function submitInitiateTransfer(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\aculta_events\Entity\EventOrganization $org */
    $org = $form_state->get('organization');
    $coOwnerUid = (int) $form_state->getValue('transfer_coowner');
    $claimService = $this->getClaimService();
    if ($coOwnerUid > 0 && $claimService !== NULL) {
      $coOwner = $this->getEntityTypeMgr()->getStorage('user')->load($coOwnerUid);
      if ($coOwner !== NULL) {
        $claimService->initiateOwnershipTransfer($org, $this->currentUser(), $coOwner);
        $this->messenger()->addStatus($this->t('Convite de transferência de titularidade enviado ao coproprietário.'));
      }
    }
  }

  /**
   * Aceita a transferência de titularidade.
   */
  public function submitAcceptTransfer(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\aculta_events\Entity\EventOrganization $org */
    $org = $form_state->get('organization');
    $claimService = $this->getClaimService();
    if ($claimService !== NULL) {
      $claimService->acceptOwnershipTransfer($org, $this->currentUser());
      $this->messenger()->addStatus($this->t('Você assumiu a titularidade desta organização com sucesso.'));
    }
  }

  /**
   * Recusa a transferência de titularidade.
   */
  public function submitRejectTransfer(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\aculta_events\Entity\EventOrganization $org */
    $org = $form_state->get('organization');
    $claimService = $this->getClaimService();
    if ($claimService !== NULL) {
      $claimService->rejectOwnershipTransfer($org, $this->currentUser());
      $this->messenger()->addStatus($this->t('Você recusou o convite de titularidade.'));
    }
  }

}
