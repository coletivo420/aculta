<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\aculta_user\Entity\OwnershipClaim;
use Drupal\aculta_user\Organization\OwnershipClaimService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Formulário de julgamento e decisão sobre reivindicação de posse (ARQ-05).
 */
final class OwnershipClaimDecisionForm extends FormBase {

  /**
   * Reivindicação sob avaliação.
   */
  protected ?OwnershipClaim $claim = NULL;

  /**
   * Construtor do formulário.
   */
  public function __construct(
    protected readonly OwnershipClaimService $claimService,
    protected readonly EntityTypeManagerInterface $entityTypeMgr,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('aculta_user.ownership_claim_service'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_user_ownership_claim_decision_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Estrutura do formulário.
   *
   * @return array<string, mixed>
   *   Estrutura do formulário.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?OwnershipClaim $aculta_ownership_claim = NULL): array {
    if ($aculta_ownership_claim === NULL) {
      throw new NotFoundHttpException();
    }
    $this->claim = $aculta_ownership_claim;

    $targetType = $this->textValue($this->claim->get('target_type')->value);
    $targetId = $this->intValue($this->claim->get('target_id')->value);
    $claimantUid = (int) $this->claim->get('uid')->target_id;

    $userStorage = $this->entityTypeMgr->getStorage('user');
    $claimant = $userStorage->load($claimantUid);
    $claimantText = $claimant !== NULL ? $claimant->getDisplayName() : sprintf('UID #%d', $claimantUid);

    $targetLabel = sprintf('%s #%d', $targetType, $targetId);
    if ($this->entityTypeMgr->hasDefinition($targetType)) {
      $targetEntity = $this->entityTypeMgr->getStorage($targetType)->load($targetId);
      if ($targetEntity !== NULL && $targetEntity->label()) {
        $targetLabel = sprintf('%s (%s)', $targetEntity->label(), $targetLabel);
      }
    }

    $status = $this->textValue($this->claim->get('status')->value);
    $created = $this->intValue($this->claim->get('created')->value);

    $form['summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Dados do pedido de reivindicação #@id', ['@id' => $this->textValue($this->claim->id())]),
      '#open' => TRUE,
      'content' => [
        '#theme' => 'table',
        '#rows' => [
          [$this->t('Entidade reivindicada:'), $targetLabel],
          [$this->t('Solicitante:'), $claimantText],
          [$this->t('Data da solicitação:'), $created > 0 ? $this->dateFormatter->format($created, 'medium') : '-'],
          [$this->t('Situação atual:'), ucfirst($status)],
          [$this->t('Contato / Documento:'), $this->textValue($this->claim->get('contato')->value)],
          [$this->t('Justificativa informada:'), $this->textValue($this->claim->get('justificativa')->value)],
        ],
      ],
    ];

    if ($status !== 'pending') {
      $decidedByUid = $this->intValue($this->claim->get('decided_by')->value);
      $decidedBy = $decidedByUid > 0 ? $userStorage->load($decidedByUid) : NULL;
      $decidedByName = $decidedBy !== NULL ? $decidedBy->getDisplayName() : ($decidedByUid > 0 ? sprintf('UID #%d', $decidedByUid) : '-');
      $decidedAt = $this->intValue($this->claim->get('decided_at')->value);

      $form['decision_info'] = [
        '#type' => 'details',
        '#title' => $this->t('Decisão proferida'),
        '#open' => TRUE,
        'content' => [
          '#theme' => 'table',
          '#rows' => [
            [$this->t('Moderador:'), $decidedByName],
            [$this->t('Data da decisão:'), $decidedAt > 0 ? $this->dateFormatter->format($decidedAt, 'medium') : '-'],
            [$this->t('Parecer:'), $this->textValue($this->claim->get('decision_notes')->value)],
          ],
        ],
      ];

      $form['actions'] = [
        '#type' => 'actions',
        'back' => [
          '#type' => 'aculta_portal_button',
          '#props' => [
            'text' => $this->t('Voltar para a lista'),
            'url' => Url::fromRoute('aculta_user.ownership_claims')->toString(),
            'variant' => 'secondary',
          ],
        ],
      ];
      return $form;
    }

    $form['decision'] = [
      '#type' => 'radios',
      '#title' => $this->t('Decisão'),
      '#required' => TRUE,
      '#options' => [
        'approved' => $this->t('Aprovar reivindicação (transfere titularidade ou concede administração)'),
        'rejected' => $this->t('Rejeitar reivindicação'),
      ],
      '#default_value' => 'approved',
    ];

    $form['decision_notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Parecer da moderação'),
      '#description' => $this->t('Justificativa técnica ou editorial registrada para auditoria.'),
      '#required' => TRUE,
      '#maxlength' => 2000,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Registrar decisão'),
        '#button_type' => 'primary',
      ],
      'cancel' => [
        '#type' => 'aculta_portal_button',
        '#props' => [
          'text' => $this->t('Cancelar'),
          'url' => Url::fromRoute('aculta_user.ownership_claims')->toString(),
          'variant' => 'secondary',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Estrutura do formulário.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->claim === NULL) {
      return;
    }

    $decision = $this->textValue($form_state->getValue('decision'));
    $notes = $this->textValue($form_state->getValue('decision_notes'));
    $approved = ($decision === 'approved');

    try {
      $this->claimService->decideClaim($this->claim, $this->currentUser(), $approved, $notes);
      $this->messenger()->addStatus($this->t('A decisão sobre a reivindicação #@id foi registrada com sucesso.', [
        '@id' => $this->textValue($this->claim->id()),
      ]));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('Erro ao registrar decisão: @message', ['@message' => $e->getMessage()]));
    }

    $form_state->setRedirect('aculta_user.ownership_claims');
  }

  /**
   * Texto de um valor de campo ou de formulário; vazio quando o valor não é escalar.
   */
  private function textValue(mixed $value): string {
    return is_scalar($value) ? (string) $value : '';
  }

  /**
   * Inteiro de um valor de campo; zero quando o valor não é numérico.
   */
  private function intValue(mixed $value): int {
    return is_numeric($value) ? (int) $value : 0;
  }

}
