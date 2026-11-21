<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Form;

use Drupal\aculta_cgu\Exception\ReportNotAllowedException;
use Drupal\aculta_cgu\Service\ReportService;
use Drupal\aculta_cgu\Service\ReportTargetResolver;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Accessible report/confirmation form, usable in popup and without JS.
 *
 * CSRF, reason, description, access and persistence stay in Drupal Form API.
 */
final class CguReportCreateForm extends FormBase {

  public function __construct(
    private readonly ReportTargetResolver $targets,
    private readonly ReportService $reports,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_cgu.report_target_resolver'),
      $container->get('aculta_cgu.report_service'),
    );
  }

  public function getFormId(): string {
    return 'aculta_cgu_report_create_form';
  }

  /**
   * @param array<string, mixed> $form
   *
   * @return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $entity_type = '', string $entity_id = ''): array {
    $target = $this->target($entity_type, $entity_id);
    $form['#attributes']['data-aculta-cgu-report-form'] = '';
    $form['#cache'] = [
      'contexts' => ['user', 'user.permissions', 'domain'],
      'max-age' => 0,
    ];
    $form['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Descreva o problema para a equipe de moderação. A denúncia não remove automaticamente o conteúdo.'),
    ];
    $form['reason'] = [
      '#type' => 'select',
      '#title' => $this->t('Motivo da denúncia'),
      '#empty_option' => $this->t('Selecione um motivo'),
      '#empty_value' => '',
      '#options' => self::reasonOptions(),
      '#required' => TRUE,
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Descrição da denúncia'),
      '#description' => $this->t('Explique o que ocorreu e, se possível, indique o trecho. Não inclua dados pessoais desnecessários.'),
      '#required' => TRUE,
      '#maxlength' => ReportService::DESCRIPTION_MAX_LENGTH,
      '#rows' => 5,
      '#attributes' => [
        'minlength' => 10,
        'autocomplete' => 'off',
      ],
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'cancel' => [
        '#type' => 'portal_button',
        '#props' => [
          'text' => $this->t('Cancelar'),
          'url' => $this->returnUrl($target)->toString(),
          'variant' => 'secondary',
        ],
        '#attributes' => ['data-aculta-dialog-close' => ''],
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Confirmar denúncia'),
        '#button_type' => 'primary',
      ],
    ];
    return $form;
  }

  /**
   * User-facing, translated labels for the exact CGU reason codes.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   */
  public static function reasonOptions(): array {
    return [
      'spam' => t('Spam ou publicidade indevida'),
      'ofensa' => t('Ofensa, assédio ou ataque pessoal'),
      'conteudo_inadequado' => t('Conteúdo inadequado'),
      'direitos_autorais' => t('Violação de direitos autorais'),
      'desinformacao' => t('Desinformação'),
      'outro' => t('Outro motivo'),
    ];
  }

  /**
   * @param array<string, mixed> $form
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $reason = $form_state->getValue('reason');
    if (!is_string($reason) || !in_array($reason, ReportService::REASONS, TRUE)) {
      $form_state->setErrorByName('reason', $this->t('Selecione um motivo válido.'));
    }
    $description = $form_state->getValue('description');
    $length = is_string($description) ? mb_strlen(trim($description)) : 0;
    if ($length < 10 || $length > ReportService::DESCRIPTION_MAX_LENGTH) {
      $form_state->setErrorByName('description', $this->t('A descrição deve ter entre 10 e @max caracteres.', [
        '@max' => ReportService::DESCRIPTION_MAX_LENGTH,
      ]));
    }
  }

  /**
   * @param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $route = $this->getRouteMatch();
    $entityType = (string) $route->getParameter('entity_type');
    $entityId = (string) $route->getParameter('entity_id');
    $target = $this->target($entityType, $entityId);
    try {
      $this->reports->createReport(
        $target,
        $this->currentUser(),
        (string) $form_state->getValue('reason'),
        NULL,
        (string) $form_state->getValue('description'),
      );
      $this->messenger()->addStatus($this->t('Denúncia encaminhada à moderação. Obrigado por relatar.'));
      $form_state->setRedirectUrl($this->returnUrl($target));
    }
    catch (ReportNotAllowedException) {
      // Never expose content existence, duplicate state or moderation details.
      $this->messenger()->addError($this->t('Não foi possível registrar a denúncia. Verifique se você já denunciou este conteúdo.'));
      $form_state->setRebuild(TRUE);
    }
  }

  private function target(string $type, string $id): ContentEntityInterface {
    $target = $this->targets->load($type, $id);
    if ($target === NULL
      || !$this->targets->canViewContext($target, $this->currentUser())
      || !$this->reports->canReport($target, $this->currentUser())) {
      throw new AccessDeniedHttpException();
    }
    return $target;
  }

  private function returnUrl(ContentEntityInterface $target): Url {
    if ($target instanceof CommentInterface) {
      $host = $target->getCommentedEntity();
      if ($host instanceof ContentEntityInterface && $host->access('view', $this->currentUser())
        && $host->hasLinkTemplate('canonical')) {
        return $host->toUrl('canonical');
      }
    }
    return $target->hasLinkTemplate('canonical')
      ? $target->toUrl('canonical')
      : Url::fromRoute('<front>');
  }

  private function getRouteMatch(): \Drupal\Core\Routing\RouteMatchInterface {
    return \Drupal::routeMatch();
  }

}
