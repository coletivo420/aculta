<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_forum\Service\TopicDeletionModerationManager;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirms topic deletion decisions; only Form API POST changes state.
 *
 * The same confirmation form handles both decision routes without tokenized
 * action URLs. A GET or HEAD request only renders a confirmation.
 */
final class TopicDeletionDecisionForm extends ConfirmFormBase {

  /**
   * Decision selected by the current route.
   */
  private string $decision = '';

  public function __construct(
    private readonly TopicDeletionModerationManager $moderationManager,
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_forum.topic_deletion_moderation_manager'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_forum_topic_deletion_decision_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    return $this->decision === 'approve'
      ? (string) $this->t('Excluir definitivamente este tópico?')
      : (string) $this->t('Rejeitar a exclusão e restaurar este tópico?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string {
    return $this->decision === 'approve'
      ? (string) $this->t('Aprovar e excluir')
      : (string) $this->t('Rejeitar e restaurar');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('aculta_forum.topic_deletion_queue');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->decision = match ($this->routeMatch->getRouteName()) {
      'aculta_forum.topic_deletion_approve' => 'approve',
      'aculta_forum.topic_deletion_reject' => 'reject',
      default => '',
    };
    $rawId = $this->routeMatch->getRawParameter('id');
    if ($this->decision === '' || !is_scalar($rawId) || !ctype_digit((string) $rawId)
      || (int) $rawId < 1) {
      throw new NotFoundHttpException();
    }
    $form_state->set('deletion_request_id', (int) $rawId);
    $form_state->set('deletion_decision', $this->decision);
    $form = parent::buildForm($form, $form_state);
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $account = $this->currentUser();
    if (!$account->hasPermission('moderate aculta forum topic deletions')
      && !$account->hasPermission('administer nodes')) {
      throw new AccessDeniedHttpException();
    }
    $id = $form_state->get('deletion_request_id');
    $decision = $form_state->get('deletion_decision');
    if (!is_int($id) || $id < 1 || !in_array($decision, ['approve', 'reject'], TRUE)) {
      throw new AccessDeniedHttpException();
    }
    $success = $decision === 'approve'
      ? $this->moderationManager->approveDeletion($id, $account)
      : $this->moderationManager->rejectDeletion($id, $account);

    if ($success) {
      $this->messenger()->addStatus($decision === 'approve'
        ? $this->t('Tópico excluído após confirmação.')
        : $this->t('Exclusão rejeitada e tópico restaurado.'));
    }
    else {
      $this->messenger()->addError($this->t('Não foi possível concluir a decisão. A fila não foi alterada por esta confirmação.'));
    }
    $form_state->setRedirect('aculta_forum.topic_deletion_queue');
  }

}
