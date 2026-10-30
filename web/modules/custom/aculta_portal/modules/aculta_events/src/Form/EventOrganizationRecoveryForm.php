<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_user\Organization\OwnershipClaimService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulário administrativo para recuperação emergencial de titularidade.
 *
 * Exclusivo de administradores do site com registro auditável.
 */
final class EventOrganizationRecoveryForm extends FormBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_user.ownership_claim_service')]
    private readonly OwnershipClaimService $claimService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_user.ownership_claim_service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_events_organization_recovery_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EventOrganization $aculta_event_organization = NULL): array {
    if (!$aculta_event_organization instanceof EventOrganization) {
      $form['message'] = [
        '#markup' => $this->t('Organização inválida.'),
      ];
      return $form;
    }

    $form_state->set('organization_id', (int) $aculta_event_organization->id());

    $titularUid = $aculta_event_organization->getOwnerId();
    $titularName = $this->t('Nenhum (não reivindicada)');
    if ($titularUid > 0) {
      /** @var \Drupal\user\UserInterface|null $titularUser */
      $titularUser = $this->entityTypeManager->getStorage('user')->load($titularUid);
      $titularName = $titularUser ? $titularUser->getDisplayName() : ('UID ' . $titularUid);
    }

    $form['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--warning']],
      'info' => [
        '#markup' => '<p>' . $this->t('A <strong>recuperação administrativa de titularidade</strong> é uma operação excepcional que transfere a titularidade direta da organização <em>%label</em> para um novo titular selecionado, atribuindo os poderes de administração e revogando a gestão anterior.', [
          '%label' => $aculta_event_organization->label(),
        ]) . '</p>',
      ],
    ];

    $form['current_state'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Estado atual da organização'),
      'title' => [
        '#type' => 'item',
        '#title' => $this->t('Nome'),
        '#markup' => htmlspecialchars((string) $aculta_event_organization->label(), ENT_QUOTES, 'UTF-8'),
      ],
      'titular' => [
        '#type' => 'item',
        '#title' => $this->t('Titular atual'),
        '#markup' => htmlspecialchars((string) $titularName, ENT_QUOTES, 'UTF-8'),
      ],
      'status' => [
        '#type' => 'item',
        '#title' => $this->t('Situação de reivindicação'),
        '#markup' => $aculta_event_organization->isClaimed() ? $this->t('Reivindicada') : $this->t('Não reivindicada'),
      ],
    ];

    $form['new_owner'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#title' => $this->t('Novo titular'),
      '#description' => $this->t('Selecione a conta do usuário que assumirá a titularidade desta organização.'),
      '#required' => TRUE,
    ];

    $form['justification'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Justificativa e notas de auditoria'),
      '#description' => $this->t('Descreva os motivos da intervenção administrativa (ex: perda de acesso, ordem legal, solicitação justificada ou reestruturação institucional).'),
      '#required' => TRUE,
      '#rows' => 4,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Confirmar recuperação de titularidade'),
        '#button_type' => 'primary',
      ],
      'cancel' => [
        '#type' => 'link',
        '#title' => $this->t('Cancelar'),
        '#url' => Url::fromRoute('aculta_events.admin_organizations'),
        '#attributes' => ['class' => ['button']],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->currentUser()->hasPermission('administer aculta events') && !$this->currentUser()->hasPermission('administer aculta claims')) {
      $form_state->setErrorByName('', (string) $this->t('Apenas administradores podem executar esta operação.'));
      return;
    }

    $newOwnerUid = (int) $form_state->getValue('new_owner');
    if ($newOwnerUid <= 0) {
      $form_state->setErrorByName('new_owner', (string) $this->t('Selecione um usuário válido para novo titular.'));
      return;
    }

    $notes = trim((string) $form_state->getValue('justification'));
    if ($notes === '') {
      $form_state->setErrorByName('justification', (string) $this->t('A justificativa da recuperação administrativa é obrigatória para fins de auditoria.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $orgId = (int) $form_state->get('organization_id');
    $org = $this->entityTypeManager->getStorage('aculta_event_organization')->load($orgId);
    if (!$org instanceof EventOrganization) {
      $this->messenger()->addError($this->t('Organização não encontrada.'));
      return;
    }

    $newOwnerUid = (int) $form_state->getValue('new_owner');
    $newOwner = $this->entityTypeManager->getStorage('user')->load($newOwnerUid);
    if (!$newOwner instanceof UserInterface) {
      $this->messenger()->addError($this->t('Usuário selecionado inválido.'));
      return;
    }

    $notes = trim((string) $form_state->getValue('justification'));
    $this->claimService->administrativeRecovery($org, $this->currentUser(), $newOwner, $notes);

    $this->messenger()->addStatus($this->t('Titularidade da organização %label transferida administrativamente para %user.', [
      '%label' => $org->label(),
      '%user' => $newOwner->getDisplayName(),
    ]));

    $form_state->setRedirect('aculta_events.admin_organizations');
  }

}
