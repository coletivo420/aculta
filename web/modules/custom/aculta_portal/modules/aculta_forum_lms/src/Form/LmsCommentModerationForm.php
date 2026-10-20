<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Form;

use Drupal\aculta_forum_lms\Access\LmsForumModerationManager;
use Drupal\comment\CommentInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Confirms an Educator's contextual LMS Comment moderation decision.
 */
final class LmsCommentModerationForm extends FormBase {

  public function __construct(
    private readonly LmsForumModerationManager $moderationManager,
    private readonly AccountInterface $account,
    private readonly EntityTypeManagerInterface $storageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('aculta_forum_lms.moderation_manager'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
    );
    $form->setMessenger($container->get('messenger'));
    $form->setStringTranslation($container->get('string_translation'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_forum_lms_comment_moderation';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $group = NULL, ?CommentInterface $comment = NULL, string $decision = ''): array {
    if (!$group instanceof GroupInterface
      || !$comment instanceof CommentInterface
      || !$this->moderationManager->commentModerationAccess($group, $comment, $decision, $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }

    $form_state->set('group_id', (int) $group->id());
    $form_state->set('comment_id', (int) $comment->id());
    $form_state->set('decision', $decision);
    $approve = $decision === 'approve';
    $form['#title'] = $approve ? $this->t('Aprovar comentário') : $this->t('Rejeitar comentário');
    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Comentário'),
      '#plain_text' => $this->t('“@subject” em “@topic”.', [
        '@subject' => $comment->getSubject(),
        '@topic' => $comment->getCommentedEntity()?->label() ?? '',
      ]),
    ];
    $form['effect'] = [
      '#type' => 'item',
      '#plain_text' => $approve
        ? $this->t('O comentário será publicado e sairá da fila de rejeitados, se estava marcado.')
        : $this->t('O comentário ficará não publicado e aparecerá na fila de rejeitados.'),
    ];
    $form['confirm'] = [
      '#type' => 'checkbox',
      '#title' => $approve
        ? $this->t('Confirmo aprovar este comentário.')
        : $this->t('Confirmo rejeitar este comentário.'),
      '#required' => TRUE,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $approve ? $this->t('Aprovar') : $this->t('Rejeitar'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancelar'),
      '#url' => Url::fromRoute('aculta_forum_lms.comments_pending', ['group' => $group->id()]),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->getValue('confirm')) {
      $form_state->setErrorByName('confirm', $this->t('Confirme a decisão para continuar.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $group = $this->storageManager->getStorage('group')->load($form_state->get('group_id'));
    $comment = $this->storageManager->getStorage('comment')->load($form_state->get('comment_id'));
    $decision = $form_state->get('decision');
    if (!$group instanceof GroupInterface || !$comment instanceof CommentInterface
      || !$this->moderationManager->applyModerationDecision($group, $comment, $decision, $this->account)) {
      throw new AccessDeniedHttpException();
    }

    $this->messenger()->addStatus($decision === 'approve'
      ? $this->t('Comentário aprovado.')
      : $this->t('Comentário rejeitado.'));
    $form_state->setRedirect('aculta_forum_lms.comments_pending', ['group' => $group->id()]);
  }

}
