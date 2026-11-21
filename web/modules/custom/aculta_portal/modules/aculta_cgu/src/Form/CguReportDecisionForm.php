<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\aculta_cgu\Entity\CguReport;
use Drupal\aculta_cgu\ModerationScope;
use Drupal\aculta_cgu\Service\ReportService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Formulário de avaliação e decisão de moderação sobre denúncia UGC (ADM1).
 */
final class CguReportDecisionForm extends FormBase {

  /**
   * Denúncia sob julgamento.
   */
  protected ?CguReport $report = NULL;

  /**
   * Construtor do formulário.
   */
  public function __construct(
    protected readonly ReportService $reportService,
    protected readonly EntityTypeManagerInterface $entityTypeMgr,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly ?RouteMatchInterface $routeMatch = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $service = $container->has('aculta_cgu.report_service')
      ? $container->get('aculta_cgu.report_service')
      : $container->get('aculta_cgu.report_service');
    return new self(
      $service,
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_cgu_report_decision_form';
  }

  /**
   * Checagem de acesso à decisão de denúncia por escopo do moderador.
   */
  public function access(AccountInterface $account, ?CguReport $aculta_cgu_report = NULL): AccessResultInterface {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->cachePerUser();
    }
    if ($aculta_cgu_report === NULL) {
      return AccessResult::forbidden()->cachePerUser();
    }
    $purposeValue = $aculta_cgu_report->get('purpose')->value;
    $purpose = is_scalar($purposeValue) ? (string) $purposeValue : '';
    if (ModerationScope::allows($account, $purpose)) {
      return AccessResult::allowed()->cachePerUser();
    }
    return AccessResult::forbidden('Sem permissão para moderar denúncias deste setor.')->cachePerUser();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   * @param ?CguReport $aculta_cgu_report
   *   Value used for $aculta_cgu_report.
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?CguReport $aculta_cgu_report = NULL): array {
    if ($aculta_cgu_report === NULL) {
      throw new NotFoundHttpException();
    }
    $this->report = $aculta_cgu_report;

    $targetTypeValue = $this->report->get('target_type')->value;
    $targetType = is_scalar($targetTypeValue) ? (string) $targetTypeValue : '';
    $targetIdValue = $this->report->get('target_id')->value;
    $targetId = is_numeric($targetIdValue) ? (int) $targetIdValue : 0;
    $purposeValue = $this->report->get('purpose')->value;
    $purpose = is_scalar($purposeValue) ? (string) $purposeValue : '';
    $statusValue = $this->report->get('status')->value;
    $status = is_scalar($statusValue) ? (string) $statusValue : '';
    $motivoValue = $this->report->get('motivo')->value;
    $motivo = is_scalar($motivoValue) ? (string) $motivoValue : '';
    $createdValue = $this->report->get('created')->value;
    $created = is_numeric($createdValue) ? (int) $createdValue : 0;

    $targetLabel = sprintf('%s #%d', $targetType, $targetId);
    if ($this->entityTypeMgr->hasDefinition($targetType)) {
      $targetEntity = $this->entityTypeMgr->getStorage($targetType)->load($targetId);
      if ($targetEntity !== NULL && $targetEntity->label()) {
        $targetLabel = sprintf('%s (%s)', (string) $targetEntity->label(), $targetLabel);
      }
    }

    $reportId = (string) $this->report->id();
    $form['summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Dados da denúncia #@id', ['@id' => $reportId]),
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

    $storage = $this->entityTypeMgr->getStorage('aculta_cgu_report');
    $userStorage = $this->entityTypeMgr->getStorage('user');

    if ($status === ReportService::STATUS_UPHELD || $status === ReportService::STATUS_REJECTED) {
      $decidedByValue = $this->report->get('decided_by')->value;
      $decidedByUid = is_numeric($decidedByValue) ? (int) $decidedByValue : 0;
      $decidedBy = $decidedByUid > 0 ? $userStorage->load($decidedByUid) : NULL;
      $decidedByName = $decidedBy !== NULL ? $decidedBy->getDisplayName() : ($decidedByUid > 0 ? sprintf('UID #%d', $decidedByUid) : '-');
      $decidedAtValue = $this->report->get('decided_at')->value;
      $decidedAt = is_numeric($decidedAtValue) ? (int) $decidedAtValue : 0;

      $decisionNotesValue = $this->report->get('decision_notes')->value;
      $decisionNotes = is_scalar($decisionNotesValue) ? (string) $decisionNotesValue : '';

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
            [$this->t('Parecer registrado:'), $decisionNotes],
          ],
        ],
      ];

      $form['actions'] = [
        '#type' => 'actions',
        'back' => [
          '#type' => 'portal_button',
          '#props' => [
            'text' => $this->t('Voltar para a fila'),
            'url' => Url::fromRoute($this->queueRoute())->toString(),
            'variant' => 'secondary',
          ],
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
          '@count' => (string) $otherPendingCount,
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
        '#type' => 'portal_button',
        '#props' => [
          'text' => $this->t('Cancelar'),
          'url' => Url::fromRoute($this->queueRoute())->toString(),
          'variant' => 'secondary',
        ],
      ],
    ];

    return $form;
  }

  /**
   * Returns the own CGU queue matching the current administrative/frontend UI.
   */
  private function queueRoute(): string {
    $route = $this->routeMatch?->getRouteName();
    // Keep report decisions in the owner experience. The CGU remains the
    // single decision engine for Wiki and Forum, with distinct frontend ACLs.
    return match ($route) {
      'aculta_cgu.forum_marley_decide' => 'aculta_cgu.forum_marley_queue',
      'aculta_wiki.moderation_report_decide' => 'aculta_wiki.moderation_reports',
      default => 'aculta_cgu.admin_reports',
    };
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $form_state
   *   Current form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->report === NULL) {
      return;
    }

    $decisionVal = $form_state->getValue('decision');
    $decision = is_scalar($decisionVal) ? (string) $decisionVal : '';
    $notesVal = $form_state->getValue('decision_notes');
    $notes = is_scalar($notesVal) ? (string) $notesVal : '';
    $batchApply = (bool) $form_state->getValue('batch_apply');
    $moderator = $this->currentUser();

    try {
      $this->reportService->decideReport($this->report, $moderator, $decision, $notes);

      if ($batchApply) {
        $storage = $this->entityTypeMgr->getStorage('aculta_cgu_report');
        $targetTypeValue = $this->report->get('target_type')->value;
        $targetType = is_scalar($targetTypeValue) ? (string) $targetTypeValue : '';
        $targetIdValue = $this->report->get('target_id')->value;
        $targetId = is_numeric($targetIdValue) ? (int) $targetIdValue : 0;
        $purposeValue = $this->report->get('purpose')->value;
        $purpose = is_scalar($purposeValue) ? (string) $purposeValue : '';

        $otherPendingIds = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('target_type', $targetType)
          ->condition('target_id', $targetId)
          ->condition('purpose', $purpose)
          ->condition('status', 'pendente')
          ->condition('id', (int) $this->report->id(), '<>')
          ->execute();

        if (!empty($otherPendingIds)) {
          /** @var \Drupal\aculta_cgu\Entity\CguReport[] $otherReports */
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

    $form_state->setRedirect($this->queueRoute());
  }

}
