<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_forum\Service\ForumCommentMover;
use Drupal\comment\CommentInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Confirms moving a public Forum reply and its descendants. */
final class ForumCommentMoveForm extends FormBase {

  public function __construct(
    protected readonly ForumCommentMover $mover,
    protected readonly AccountInterface $account,
  ) {}

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_forum.comment_mover'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc} */
  public function getFormId(): string {
    return 'aculta_forum_comment_move';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   * @return array<string, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?CommentInterface $comment = NULL): array {
    if (!$comment instanceof CommentInterface || !$this->mover->access($comment, $this->account)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $options = $this->mover->destinations($comment, $this->account);
    if ($options === []) {
      throw new AccessDeniedHttpException('No accessible open public Forum topic is available.');
    }

    try {
      $subtree_count = $this->mover->subtreeCount($comment);
    }
    catch (\InvalidArgumentException $exception) {
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    }

    $host = $comment->getCommentedEntity();
    $host_label = $host ? (string) $host->label() : '';
    $form_state->set('comment_id', (int) $comment->id());
    $count_text = (string) $this->formatPlural($subtree_count, '1 comentário', '@count comentários');
    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Comentário selecionado'),
      '#plain_text' => $this->t('“@subject” em “@topic”; serão movidos @count, incluindo todas as respostas descendentes.', [
        '@subject' => (string) $comment->getSubject(),
        '@topic' => $host_label,
        '@count' => $count_text,
      ]),
    ];
    $form['preservation'] = [
      '#type' => 'item',
      '#plain_text' => $this->t('Autoria, conteúdo, data, estado de moderação, IDs e relações entre respostas serão preservados. Links antigos que apontem ao tópico de origem e à âncora do comentário não são redirecionados.'),
    ];
    $form['destination'] = [
      '#type' => 'select',
      '#title' => $this->t('Tópico público de destino'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['confirm_move'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirmo mover o comentário e todas as respostas indicadas.'),
      '#required' => TRUE,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Mover comentários'),
        '#button_type' => 'primary',
      ],
      'cancel' => [
        '#type' => 'aculta_portal_button',
        '#props' => [
          'text' => (string) $this->t('Cancelar'),
          'url' => Url::fromRoute('aculta_forum.admin')->toString(),
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
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $destination_id = filter_var($form_state->getValue('destination'), FILTER_VALIDATE_INT);
    if ($destination_id === FALSE || $destination_id <= 0 || !$form_state->getValue('confirm_move')) {
      $form_state->setErrorByName('destination', $this->t('Selecione um destino e confirme a movimentação.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $comment_id = filter_var($form_state->get('comment_id'), FILTER_VALIDATE_INT);
    $destination_id = filter_var($form_state->getValue('destination'), FILTER_VALIDATE_INT);
    if ($comment_id === FALSE || $destination_id === FALSE) {
      $form_state->setErrorByName('destination', $this->t('Identificador inválido.'));
      return;
    }
    try {
      $count = $this->mover->move(
        (int) $comment_id,
        (int) $destination_id,
        $this->account,
      );
    }
    catch (\InvalidArgumentException $exception) {
      $form_state->setErrorByName('destination', $this->t('A movimentação não foi realizada: @reason', [
        '@reason' => $exception->getMessage(),
      ]));
      return;
    }
    catch (\Throwable) {
      $this->messenger()->addError($this->t('Não foi possível mover os comentários. Nenhuma alteração foi mantida; tente novamente ou encaminhe a operação à equipe técnica.'));
      return;
    }

    $this->messenger()->addStatus($this->formatPlural($count, '1 comentário foi movido.', '@count comentários foram movidos.'));
    $form_state->setRedirect('aculta_forum.admin');
  }

}
