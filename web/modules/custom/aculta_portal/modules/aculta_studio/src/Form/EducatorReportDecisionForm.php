<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Form;

use Drupal\aculta_cgu\Entity\CguReport;
use Drupal\aculta_cgu\Exception\ReportNotAllowedException;
use Drupal\aculta_cgu\Scope\ReportTargetScope;
use Drupal\aculta_cgu\Service\ReportService;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Analisa as denúncias pendentes de um conteúdo LMS no Ganjalab Educador.
 *
 * Uma decisão vale para todas as denúncias pendentes daquele alvo. Cada uma passa por
 * ReportService::decideReport(), que revalida o escopo do moderador e o status.
 */
final class EducatorReportDecisionForm extends FormBase {

  public function __construct(
    protected readonly ReportService $reportService,
    protected readonly ReportTargetScope $targetScope,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountInterface $account,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_cgu.report_service'),
      $container->get('aculta_cgu.report_target_scope'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_studio_educator_report_decision';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Render array do formulário.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado do formulário.
   * @param string|null $target_type
   *   Tipo da entidade denunciada.
   * @param int|null $target_id
   *   ID da entidade denunciada.
   *
   * @return array<string, mixed>
   *   Render array do formulário.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $target_type = NULL, ?int $target_id = NULL): array {
    $target = $this->loadAuthorizedTarget($target_type, $target_id);
    $reports = $this->pendingReports($target_type ?? '', $target_id ?? 0);

    $form['#attributes'] = ['data-aculta-studio' => 'educator-report-decision'];
    $form['target'] = [
      '#type' => 'html_tag',
      '#tag' => 'h1',
      '#value' => $this->t('Denúncias de “@label”', ['@label' => (string) $target->label()]),
    ];
    $form_state->set('report_ids', array_keys($reports));

    if ($reports === []) {
      $form['empty'] = [
        '#markup' => '<p>' . $this->t('Não há denúncias pendentes para este conteúdo.') . '</p>',
      ];
      $form['back'] = $this->backLink();
      return $form;
    }

    $form['count'] = [
      '#markup' => '<p>' . $this->formatPlural(count($reports), '1 denúncia pendente.', '@count denúncias pendentes.') . '</p>',
    ];
    $form['decision'] = [
      '#type' => 'radios',
      '#title' => $this->t('Decisão'),
      '#options' => [
        ReportService::STATUS_UPHELD => $this->t('Procedente'),
        ReportService::STATUS_REJECTED => $this->t('Improcedente'),
      ],
      '#required' => TRUE,
    ];
    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Parecer'),
      '#description' => $this->t('Até @max caracteres.', ['@max' => (string) ReportService::NOTES_MAX_LENGTH]),
      '#maxlength' => ReportService::NOTES_MAX_LENGTH,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Registrar decisão'),
        '#button_type' => 'primary',
      ],
    ];
    $form['back'] = $this->backLink();
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Render array do formulário.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado do formulário.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $decision = $form_state->getValue('decision');
    $notes = $form_state->getValue('notes');
    $notes = is_string($notes) ? $notes : '';
    $reportIds = $form_state->get('report_ids');
    if (!is_array($reportIds) || !is_string($decision)) {
      $this->messenger()->addError($this->t('Não foi possível registrar a decisão.'));
      return;
    }

    $decided = 0;
    $storage = $this->entityTypeManager->getStorage('aculta_cgu_report');
    foreach ($reportIds as $reportId) {
      $report = is_numeric($reportId) ? $storage->load((int) $reportId) : NULL;
      if (!$report instanceof CguReport) {
        continue;
      }
      try {
        $this->reportService->decideReport($report, $this->account, $decision, $notes);
        $decided++;
      }
      catch (ReportNotAllowedException | AccessDeniedHttpException) {
        // Denúncia já decidida, em análise por outra pessoa ou fora do escopo: não afeta as demais.
        continue;
      }
    }

    $this->messenger()->addStatus($this->formatPlural($decided, '1 denúncia decidida.', '@count denúncias decididas.'));
    $form_state->setRedirectUrl(Url::fromRoute('aculta_studio.educator_report_queue'));
  }

  /**
   * Carrega o alvo, exigindo permissão e escopo de Group. Fora do escopo vira 404.
   */
  private function loadAuthorizedTarget(?string $targetType, ?int $targetId): EntityInterface {
    if (!$this->account->hasPermission('moderar denuncias lms')) {
      throw new AccessDeniedHttpException();
    }
    if ($targetType === NULL || $targetId === NULL || !$this->entityTypeManager->hasDefinition($targetType)) {
      throw new NotFoundHttpException();
    }
    $target = $this->entityTypeManager->getStorage($targetType)->load($targetId);
    if (!$target instanceof EntityInterface || !$this->targetScope->decide('lms', $target, $this->account)) {
      throw new NotFoundHttpException();
    }
    return $target;
  }

  /**
   * Denúncias pendentes ou em análise do alvo, que a conta pode decidir.
   *
   * @return array<int, \Drupal\aculta_cgu\Entity\CguReport>
   *   Denúncias indexadas pelo ID.
   */
  private function pendingReports(string $targetType, int $targetId): array {
    if ($targetType === '' || $targetId <= 0) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('aculta_cgu_report');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('target_type', $targetType)
      ->condition('target_id', $targetId)
      ->condition('purpose', 'lms')
      ->condition('status', [ReportService::STATUS_PENDING, ReportService::STATUS_REVIEW], 'IN')
      ->execute();
    $reports = [];
    foreach ($storage->loadMultiple($ids) as $report) {
      if ($report instanceof CguReport && $this->targetScope->allowsReport($report, $this->account)) {
        $reports[(int) $report->id()] = $report;
      }
    }
    return $reports;
  }

  /**
   * Link de volta à fila do educador.
   *
   * @return array<string, mixed>
   *   Link de volta à fila.
   */
  private function backLink(): array {
    return [
      '#type' => 'link',
      '#title' => $this->t('Voltar às denúncias'),
      '#url' => Url::fromRoute('aculta_studio.educator_report_queue'),
    ];
  }

}
