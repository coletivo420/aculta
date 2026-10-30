<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_ugc\Entity\UgcReport;
use Drupal\aculta_ugc\ModerationScope;
use Drupal\aculta_ugc\Service\ReportService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Formulário de avaliação e decisão de moderação sobre denúncia UGC (ADM1).
 */
final class UgcReportDecisionForm extends FormBase {

  /**
   * Denúncia sob julgamento.
   */
  private ?UgcReport $report = NULL;

  /**
   * Construtor do formulário.
   */
  public function __construct(
    private readonly ReportService $reportService,
    private readonly EntityTypeManagerInterface $entityTypeMgr,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('aculta_ugc.report_service'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_ugc_report_decision_form';
  }

  /**
   * Checagem de acesso à decisão de denúncia por escopo do moderador.
   */
  public function access(AccountInterface $account, ?UgcReport $aculta_ugc_report = NULL): AccessResultInterface {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->cachePerUser();
    }
    if ($aculta_ugc_report === NULL) {
      return AccessResult::forbidden()->cachePerUser();
    }
    $purpose = (string) $aculta_ugc_report->get('purpose')->value;
    if (ModerationScope::allows($account, $purpose)) {
      return AccessResult::allowed()->cachePerUser();
    }
    return AccessResult::forbidden('Sem permissão para moderar denúncias deste setor.')->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?UgcReport $aculta_ugc_report = NULL): array {
    if ($aculta_ugc_report === NULL) {
      throw new NotFoundHttpException();
    }
    $this->report = $aculta_ugc_report;

    $targetType = (string) $this->report->get('target_type')->value;
    $targetId = (int) $this->report->get('target_id')->value;
    $purpose = (string) $this->report->get('purpose')->value;
    $status = (string) $this->report->get('status')->value;
    $motivo = (string) $this->report->get('motivo')->value;
    $created = (int) $this->report->get('created')->value;

    $targetLabel = sprintf('%s #%d', $targetType, $targetId);
    if ($this->entityTypeMgr->hasDefinition($targetType)) {
      $targetEntity = $this->entityTypeMgr->getStorage($targetType)->load($targetId);
      if ($targetEntity !== NULL && $targetEntity->label()) {
        $targetLabel = sprintf('%s (%s)', $targetEntity->label(), $targetLabel);
      }
    }

    $form['summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Dados da denúncia #@id', ['@id' => $this->report->id()]),
      '#open' => TRUE,
      'content' => [
        '#theme' => 'table',
        '#rows' => [
          [$this->t('Conteúdo denunciado:'), $targetLabel],
          [$this->t('Área / Purpose:'), ucfirst($purpose)],
          [$this->t('Motivo apontado:'), ucfirst($motivo)],
          [$this->t('Data da denúncia:'), $created > 0 ? $this->dateFormatter->format($created, 'medium') : '-'],
          [$this->t('Situação atual:'), ucfirst($status)],
        ],
      ],
    ];

    $storage = $this->entityTypeMgr->getStorage('aculta_ugc_report');
    $userStorage = $this->entityTypeMgr->getStorage('user');

    if ($status === ReportService::STATUS_UPHELD || $status === ReportService::STATUS_REJECTED) {
      $decidedByUid = (int) $this->report->get('decided_by')->value;
      $decidedBy = $decidedByUid > 0 ? $userStorage->load($decidedByUid) : NULL;
      $decidedByName = $decidedBy !== NULL ? $decidedBy->getDisplayName() : ($decidedByUid > 0 ? sprintf('UID #%d', $decidedByUid) : '-');
      $decidedAt = (int) $this->report->get('decided_at')->value;

      $form['decision_info'] = [
        '#type' => 'details',
        '#title' => $this->t('Decisão proferida'),
        '#open' => TRUE,
        'content' => [
          '#theme' => 'table',
          '#rows' => [
            [$this->t('Situação:'), ucfirst($status)],
            [$this->t('Moderador:'), $decidedByName],
            [$this->t('Data da decisão:'), $decidedAt > 0 ? $this->dateFormatter->format($decidedAt, 'medium') : '-'],
            [$this->t('Parecer registrado:'), (string) $this->report->get('decision_notes')->value],
          ],
        ],
      ];

      $form['actions'] = [
        '#type' => 'actions',
        'back' => [
          '#type' => 'link',
          '#title' => $this->t('Voltar para a fila'),
          '#url' => Url::fromRoute('aculta_ugc.admin_reports'),
          '#attributes' => ['class' => ['button']],
        ],
      ];
      return $form;
    }

    $otherPendingCount = (int) $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_type', $targetType)
      ->condition('target_id', $targetId)
      ->condition('purpose', $purpose)
      ->condition('status', 'pendente')
      ->condition('id', (int) $this->report->id(), '<>')
      ->count()
      ->execute();

    $decisionOptions = [];
    if ($status === ReportService::STATUS_PENDING) {
      $decisionOptions[ReportService::STATUS_REVIEW] = $this->t('Em análise (investigação editorial / técnica em andamento)');
    }
    $decisionOptions[ReportService::STATUS_UPHELD] = $this->t('Procedente (denúncia confirmada; medidas cabíveis)');
    $decisionOptions[ReportService::STATUS_REJECTED] = $this->t('Improcedente (denúncia rejeitada ou arquivada sem infração)');

    $form['decision'] = [
      '#type' => 'radios',
      '#title' => $this->t('Novo status'),
      '#required' => TRUE,
      '#options' => $decisionOptions,
      '#default_value' => $status === ReportService::STATUS_PENDING ? ReportService::STATUS_UPHELD : ReportService::STATUS_UPHELD,
    ];

    if ($otherPendingCount > 0) {
      $form['batch_apply'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Aplicar esta mesma decisão a todas as outras @count denúncias pendentes deste mesmo conteúdo', [
          '@count' => $otherPendingCount,
        ]),
        '#default_value' => TRUE,
      ];
    }

    $form['decision_notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Parecer da moderação'),
      '#description' => $this->t('Justificativa da decisão para auditoria e histórico interno.'),
      '#required' => TRUE,
      '#maxlength' => ReportService::NOTES_MAX_LENGTH,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Registrar decisão'),
        '#button_type' => 'primary',
      ],
      'cancel' => [
        '#type' => 'link',
        '#title' => $this->t('Cancelar'),
        '#url' => Url::fromRoute('aculta_ugc.admin_reports'),
        '#attributes' => ['class' => ['button']],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->report === NULL) {
      return;
    }

    $decision = (string) $form_state->getValue('decision');
    $notes = (string) $form_state->getValue('decision_notes');
    $batchApply = (bool) $form_state->getValue('batch_apply');
    $moderator = $this->currentUser();

    try {
      $this->reportService->decideReport($this->report, $moderator, $decision, $notes);

      if ($batchApply) {
        $storage = $this->entityTypeMgr->getStorage('aculta_ugc_report');
        $otherPendingIds = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('target_type', (string) $this->report->get('target_type')->value)
          ->condition('target_id', (int) $this->report->get('target_id')->value)
          ->condition('purpose', (string) $this->report->get('purpose')->value)
          ->condition('status', 'pendente')
          ->condition('id', (int) $this->report->id(), '<>')
          ->execute();

        if (!empty($otherPendingIds)) {
          /** @var \Drupal\aculta_ugc\Entity\UgcReport[] $otherReports */
          $otherReports = $storage->loadMultiple($otherPendingIds);
          foreach ($otherReports as $otherReport) {
            $this->reportService->decideReport($otherReport, $moderator, $decision, $notes);
          }
        }
      }

      $this->messenger()->addStatus($this->t('Decisão de moderação registrada com sucesso.'));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('Erro ao salvar decisão: @message', ['@message' => $e->getMessage()]));
    }

    $form_state->setRedirect('aculta_ugc.admin_reports');
  }

}
