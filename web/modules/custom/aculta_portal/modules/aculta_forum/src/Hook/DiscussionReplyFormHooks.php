<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Hook\Order\Order;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Service\DiscussionReplyIdempotencyManager;
use Drupal\aculta_forum\Service\DiscussionReplyUrlBuilder;
use Drupal\aculta_forum\Service\DiscussionThreadDepthResolver;
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
    #[Autowire(service: 'aculta_forum.discussion_thread_depth_resolver')]
    private readonly DiscussionThreadDepthResolver $depthResolver,
  ) {}

  /**
   * Alters comment forms for discussion reply handling.
   */
  #[Hook('form_comment_form_alter', order: Order::Last)]
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
    if ($parentId === NULL && $comment->isNew()) {
      $request = $this->requestStack->getCurrentRequest();
      $requestedPid = $request ? ($request->query->get('resposta') ?? $request->request->get('pid') ?? $request->query->get('pid')) : NULL;
      if ($requestedPid && is_numeric($requestedPid)) {
        $parent = \Drupal::entityTypeManager()->getStorage('comment')->load((int) $requestedPid);
        if ($parent instanceof CommentInterface) {
          $parentId = $this->depthResolver->resolveEffectiveParentId($parent);
          if ($parentId) {
            $comment->set('pid', $parentId);
          }
        }
      }
    }

    // Resposta e edição não têm Assunto nem a marca "Rejeitado": o assunto é do tópico e a
    // rejeição é da moderação.
    unset($form['subject'], $form['field_aculta_rejected']);

    // No popup, o formulário do comentário mostra só o texto e as ações. Autoria, nome,
    // data, hora, status e idioma ficam fora (são campos administrativos do core).
    $keep = ['comment_body', 'actions', 'form_id', 'form_build_id', 'form_token'];
    foreach (array_keys($form) as $key) {
      if (!str_starts_with((string) $key, '#') && !in_array($key, $keep, TRUE)) {
        unset($form[$key]);
      }
    }

    // Expose hidden pid element and entity builder so Drupal form state carries the thread.
    if ($comment->isNew()) {
      $form['pid'] = [
        '#type' => 'hidden',
        '#default_value' => $parentId !== NULL ? (string) $parentId : '',
      ];
      $form['#entity_builders'][] = [self::class, 'buildCommentEntityWithPid'];
    }

    // Resposta nova vai ao tópico. Edição volta ao endpoint do popup (?editar=CID): a página
    // de edição do comentário não existe mais (ver ForumPopupOnlyAccess).
    $targetUrl = $comment->isNew()
      ? $this->urlBuilder->buildHostReplyUrl($host, $parentId)
      : Url::fromRoute('aculta_forum.discussion_composer', [
        'entity_type' => $host->getEntityTypeId(),
        'entity_id' => $host->id(),
      ], ['query' => ['editar' => (int) $comment->id()]]);
    $form['#action'] = $targetUrl->toString();

    // Attach native HTMX support to form.
    $composerId = 'discussion-composer-' . $host->getEntityTypeId() . '-' . $host->id() . ($parentId ? '-' . $parentId : '');
    $form['#attributes']['id'] = $composerId . '-form';

    // Prevent destination manipulation.
    $formState->setIgnoreDestination(TRUE);

    // Ações do comentário: sem Visualizar; "Limpar" zera o formulário (reset nativo, sem JavaScript).
    unset($form['actions']['preview']);
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#value'] = $comment->isNew() ? t('Publicar comentário') : t('Salvar alterações');
      $form['actions']['submit']['#button_type'] = 'primary';
    }
    $form['actions']['clear'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => t('Limpar'),
      '#attributes' => [
        'type' => 'button',
        'class' => ['button', 'button--secondary'],
        'data-aculta-reply-clear' => '',
      ],
      '#weight' => 50,
    ];
    // "Cancelar" (secundário, como Limpar) fecha o popup de resposta.
    $form['actions']['cancel'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => t('Cancelar'),
      '#attributes' => [
        'type' => 'button',
        'class' => ['button', 'button--secondary'],
        'data-aculta-dialog-close' => '',
      ],
      '#weight' => 51,
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
   * Entity builder that assigns normalized parent ID to new comment entities.
   */
  public static function buildCommentEntityWithPid(string $entityType, EntityInterface $entity, array &$form, FormStateInterface $formState): void {
    if (!$entity instanceof CommentInterface || !$entity->isNew()) {
      return;
    }
    $pidVal = $formState->getValue('pid');
    if (($pidVal === NULL || $pidVal === '') && \Drupal::hasService('request_stack')) {
      $req = \Drupal::requestStack()->getCurrentRequest();
      $pidVal = $req ? ($req->request->get('pid') ?? $req->query->get('resposta') ?? $req->query->get('pid')) : NULL;
    }
    if ($pidVal !== NULL && $pidVal !== '' && is_numeric($pidVal)) {
      /** @var \Drupal\aculta_forum\Service\DiscussionThreadDepthResolver $depthResolver */
      $depthResolver = \Drupal::service('aculta_forum.discussion_thread_depth_resolver');
      $parent = \Drupal::entityTypeManager()->getStorage('comment')->load((int) $pidVal);
      if ($parent instanceof CommentInterface) {
        $effectivePid = $depthResolver->resolveEffectiveParentId($parent);
        $entity->set('pid', $effectivePid);
      }
    }
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
    // Permalink do comentário no próprio tópico: o construtor do módulo calcula a página da
    // paginação e a âncora do comentário (o link público é /resposta/ID).
    if (!$comment->isNew()) {
      $permalinkUrl = \Drupal::service('aculta_forum.discussion_comment_permalink_builder')->build($comment);
      if ($permalinkUrl === NULL) {
        return;
      }
      $permalinkUrl->setAbsolute(TRUE);
      $permalink = $permalinkUrl->toString();
      // Envio pelo HTMX (popup): o HX-Redirect leva o navegador ao permalink do comentário novo.
      if (\Drupal::request()->headers->has('HX-Request')) {
        $formState->setResponse(new \Symfony\Component\HttpFoundation\Response('', 200, ['HX-Redirect' => $permalink]));
        return;
      }
      $formState->setRedirectUrl($permalinkUrl);
    }
  }

}
