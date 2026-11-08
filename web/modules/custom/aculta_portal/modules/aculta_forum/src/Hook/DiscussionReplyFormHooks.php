<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Service\DiscussionReplyIdempotencyManager;
use Drupal\aculta_forum\Service\DiscussionReplyUrlBuilder;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hooks for comment and post forms in unified discussion contexts.
 */
final class DiscussionReplyFormHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.discussion_context_resolver')]
    private readonly DiscussionContextResolver $contextResolver,
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'aculta_forum.discussion_reply_url_builder')]
    private readonly DiscussionReplyUrlBuilder $urlBuilder,
    #[Autowire(service: 'aculta_forum.discussion_reply_idempotency_manager')]
    private readonly DiscussionReplyIdempotencyManager $idempotencyManager,
    private readonly RequestStack $requestStack,
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Alters comment forms for discussion reply handling.
   */
  #[Hook('form_comment_form_alter')]
  public function commentFormAlter(array &$form, FormStateInterface $formState, string $formId): void {
    $formObject = $formState->getFormObject();
    if (!$formObject instanceof EntityFormInterface) {
      return;
    }
    $comment = $formObject->getEntity();
    if (!$comment instanceof CommentInterface || $comment->bundle() !== 'aculta_discussion_post') {
      return;
    }

    $host = $comment->getCommentedEntity();
    if (!$host instanceof EntityInterface) {
      return;
    }

    $discussion = $this->contextResolver->resolve($host, $this->currentUser);
    if ($discussion === NULL) {
      return;
    }

    // COM-05: Ensure action points to host page with Portuguese parameters instead of comment.reply.
    $parentId = $comment->hasParentComment() ? (int) $comment->getParentComment()->id() : NULL;
    $targetUrl = $this->urlBuilder->buildHostReplyUrl($host, $parentId);
    $form['#action'] = $targetUrl->toString();

    // Attach native HTMX support to form.
    $composerId = 'discussion-composer-' . $host->getEntityTypeId() . '-' . $host->id() . ($parentId ? '-' . $parentId : '');
    $form['#attributes']['id'] = $composerId . '-form';

    // Prevent destination manipulation.
    $formState->setIgnoreDestination(TRUE);

    // Ações do comentário: sem Visualizar; "Limpar" zera o formulário (reset nativo, sem JavaScript).
    unset($form['actions']['preview']);
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#value'] = t('Publicar comentário');
      $form['actions']['submit']['#button_type'] = 'primary';
    }
    $form['actions']['clear'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => t('Limpar'),
      '#attributes' => [
        'type' => 'reset',
        'class' => ['button', 'button--secondary'],
      ],
      '#weight' => 50,
    ];

    // Apply native HTMX submission to the form element.
    $htmx = new Htmx();
    $htmx->post($targetUrl)
      ->target('#' . $composerId)
      ->swap('outerHTML')
      ->onlyMainContent()
      ->applyTo($form);

    // Add validation and submission handlers for idempotency and safe redirection.
    array_unshift($form['#validate'], [self::class, 'validateIdempotency']);
    $form['actions']['submit']['#submit'][] = [self::class, 'postSubmitRedirect'];
  }

  /**
   * Validates duplicate submission within idempotency window.
   */
  public static function validateIdempotency(array &$form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();
    if (!$formObject instanceof EntityFormInterface) {
      return;
    }
    $comment = $formObject->getEntity();
    if (!$comment instanceof CommentInterface || !$comment->isNew()) {
      return;
    }

    $body = (string) $formState->getValue(['comment_body', 0, 'value'], '');
    if (trim($body) === '') {
      return;
    }

    $currentUser = \Drupal::currentUser();
    /** @var \Drupal\aculta_forum\Service\DiscussionReplyIdempotencyManager $manager */
    $manager = \Drupal::service('aculta_forum.discussion_reply_idempotency_manager');
    $parentId = $comment->hasParentComment() ? (int) $comment->getParentComment()->id() : NULL;

    $duplicateCid = $manager->findRecentDuplicate(
      $currentUser,
      $comment->getCommentedEntityTypeId(),
      $comment->getCommentedEntityId(),
      $parentId,
      $body
    );

    if ($duplicateCid !== NULL) {
      // Re-use authorized previous submission without duplicating entity or notifications.
      $formState->setErrorByName('comment_body', (string) t('Uma resposta idêntica foi enviada recentemente. Aguarde antes de reenviar.'));
    }
  }

  /**
   * Ensures submission redirects back to host on non-HTMX requests and avoids standalone comment URLs.
   */
  public static function postSubmitRedirect(array &$form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();
    if (!$formObject instanceof EntityFormInterface) {
      return;
    }
    $comment = $formObject->getEntity();
    if (!$comment instanceof CommentInterface) {
      return;
    }
    $host = $comment->getCommentedEntity();
    if ($host instanceof EntityInterface) {
      $url = $host->toUrl('canonical');
      $url->setOption('fragment', 'comment-' . $comment->id());
      $formState->setRedirectUrl($url);
    }
  }

}
