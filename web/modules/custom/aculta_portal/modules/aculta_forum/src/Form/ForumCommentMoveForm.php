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
    private readonly ForumCommentMover $mover,
    private readonly AccountInterface $account,
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
   * {@inheritdoc} */
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
    $form_state->set('comment_id', (int) $comment->id());
    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Comentário selecionado'),
      '#plain_text' => $this->t('“@subject” em “@topic”; serão movidos @count comentários, incluindo todas as respostas descendentes.', [
        '@subject' => $comment->getSubject(),
        '@topic' => $host->label(),
        '@count' => $subtree_count,
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
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Mover comentários'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancelar'),
      '#url' => Url::fromRoute('view.aculta_forum_admin_comments.page_forum_comments'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $destination_id = filter_var($form_state->getValue('destination'), FILTER_VALIDATE_INT);
    if ($destination_id === FALSE || $destination_id <= 0 || !$form_state->getValue('confirm_move')) {
      $form_state->setErrorByName('destination', $this->t('Selecione um destino e confirme a movimentação.'));
    }
  }

  /**
   * {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $count = $this->mover->move(
        (int) $form_state->get('comment_id'),
        (int) $form_state->getValue('destination'),
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
    $form_state->setRedirect('view.aculta_forum_admin_comments.page_forum_comments');
  }

}
