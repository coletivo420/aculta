<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Form;

use Drupal\aculta_magazine\Access\EditorialCapability;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\comment\CommentInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Comment moderation adapter using Comment entity operations and access. */
final class EditorialCommentModerationForm extends FormBase {

  public function __construct(private readonly EditorialCapability $capabilities, private readonly DiscussionContextResolver $discussionContexts, private readonly AccountInterface $account) {}

  /**
   * Creates the contextual moderation form with Magazine and Forum services.
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_magazine.capability'), $container->get('aculta_forum.discussion_context_resolver'), $container->get('current_user'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_magazine_comment_moderation';
  }

  /**
   * Builds only operations allowed for this Comment's current state.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?CommentInterface $comment = NULL): array {
    if (!$comment || !$this->allowed($comment)) {
      throw new AccessDeniedHttpException();
    }
    $options = [];
    if (!$comment->isPublished() && $comment->access('approve', $this->account) && $comment->get('status')->access('edit', $this->account)) {
      $options['approve'] = $this->t('Aprovar');
    }
    if ($comment->isPublished() && $comment->access('update', $this->account) && $comment->get('status')->access('edit', $this->account)) {
      $options['unpublish'] = $this->t('Despublicar');
    }
    if ($comment->access('delete', $this->account)) {
      $options['delete'] = $this->t('Remover');
    }
    if ($options === []) {
      throw new AccessDeniedHttpException();
    }
    $form['comment_id'] = ['#type' => 'value', '#value' => $comment->id()];
    $form['operation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Ação de moderação'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirmo esta ação; remover é permanente.'),
      '#required' => TRUE,
    ];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Confirmar')];
    return $form;
  }

  /**
   * Revalidates contextual and Comment access immediately before mutation.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $comment = $this->entityTypeManager()->getStorage('comment')->load($form_state->getValue('comment_id'));
    if (!$comment instanceof CommentInterface || !$this->allowed($comment)) {
      throw new AccessDeniedHttpException();
    }
    $operation = (string) $form_state->getValue('operation');
    if ($operation === 'approve' && !$comment->isPublished() && $comment->access('approve', $this->account) && $comment->get('status')->access('edit', $this->account)) {
      $comment->setPublished()->save();
    }
    elseif ($operation === 'unpublish' && $comment->isPublished() && $comment->access('update', $this->account) && $comment->get('status')->access('edit', $this->account)) {
      $comment->setUnpublished()->save();
    }
    elseif ($operation === 'delete' && $comment->access('delete', $this->account)) {
      $comment->delete();
    }
    else {
      throw new AccessDeniedHttpException();
    }
    $this->messenger()->addStatus($this->t('A ação de moderação foi aplicada.'));
    $form_state->setRedirect('aculta_studio.tab', ['aba' => 'revista', 'secao' => 'comentarios', 'sub' => 'pendentes']);
  }

  /**
   * Requires the Magazine Forum context, capability, and native permissions.
   */
  private function allowed(CommentInterface $comment): bool {
    $node = $comment->getCommentedEntity();
    $context = $node ? $this->discussionContexts->resolve($node, $this->account) : NULL;
    return $node && $node->bundle() === 'revista' && $comment->getFieldName() === 'field_aculta_discussion'
      && $context !== NULL && $context->getType() === 'publication'
      && $context->getReadAccess()->isAllowed() && $context->getModerationAccess()->isAllowed()
      && $this->capabilities->access(EditorialCapability::COMMENTS, $this->account)->isAllowed()
      && $this->account->hasPermission('administer comments')
      && $node->access('view', $this->account)
      && $node->get('field_aculta_discussion')->access('view', $this->account)
      && $comment->access('view', $this->account);
  }

}
