<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Service\DiscussionReplyAccess;
use Drupal\aculta_forum\Service\DiscussionReplyUrlBuilder;
use Drupal\comment\CommentInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds unified inline reply actions to discussion comments.
 */
final class DiscussionReplyActionsHooks {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'aculta_forum.discussion_reply_access')]
    private readonly DiscussionReplyAccess $replyAccess,
    #[Autowire(service: 'aculta_forum.discussion_reply_url_builder')]
    private readonly DiscussionReplyUrlBuilder $urlBuilder,
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Implements hook_comment_links_alter().
   */
  #[Hook('comment_links_alter')]
  public function commentLinksAlter(array &$links, CommentInterface $comment, array &$context): void {
    if ($comment->bundle() !== 'aculta_discussion_post') {
      return;
    }
    // O "Responder" de texto do core aponta para o formulário antigo de resposta (quebrado). A ação
    // de Responder do fórum é o botão com ícone, que abre o popup.
    unset($links['#links']['comment-reply'], $links['comment']['#links']['comment-reply']);
    if (!$comment->isPublished()) {
      return;
    }
    $host = $comment->getCommentedEntity();
    if (!$host instanceof EntityInterface) {
      return;
    }

    $this->markEditLink($links, $comment, $host);

    $access = $this->replyAccess->checkAccess($host, $this->currentUser, (int) $comment->id());
    if (!$access->isAllowed()) {
      return;
    }

    $fallbackUrl = $this->urlBuilder->buildHostReplyUrl($host, (int) $comment->id(), 'responder');
    $composerId = 'discussion-composer-' . $host->getEntityTypeId() . '-' . $host->id() . '-' . $comment->id();

    // HTMX action to load reply composer inline under this comment.
    $endpointUrl = Url::fromRoute('aculta_forum.discussion_composer', [
      'entity_type' => $host->getEntityTypeId(),
      'entity_id' => $host->id(),
    ], [
      'query' => [
        'resposta' => (int) $comment->id(),
        'modo' => 'responder',
      ],
    ]);

    $replyLink = [
      'title' => $this->translation->translate('Responder'),
      'url' => $fallbackUrl,
      'attributes' => [
        'class' => ['resposta-acao', 'resposta-acao--responder', 'use-htmx'],
        'data-action' => 'resposta-responder',
        'data-parent-id' => (string) $comment->id(),
        'data-hx-get' => $endpointUrl->toString(),
        'data-hx-target' => '#' . $composerId,
        'data-hx-swap' => 'outerHTML',
        'aria-label' => (string) $this->translation->translate('Responder ao comentário #@id', ['@id' => $comment->id()]),
      ],
    ];

    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($comment)
      ->addCacheableDependency($access);

    if (isset($links['comment'])) {
      $links['comment']['#links']['resposta-responder'] = $replyLink;
      $cacheability->applyTo($links['comment']);
    }
    else {
      $links['#links']['resposta-responder'] = $replyLink;
      $cacheability->applyTo($links);
    }
  }

  /**
   * Editar abre o popup do compositor (mesmo comportamento de Responder e Citar).
   *
   * O link de edição do core continua no href (acesso sem JavaScript); o clique carrega o
   * formulário de edição no diálogo pelo endpoint do compositor com ?editar=CID.
   */
  private function markEditLink(array &$links, CommentInterface $comment, EntityInterface $host): void {
    $endpoint = Url::fromRoute('aculta_forum.discussion_composer', [
      'entity_type' => $host->getEntityTypeId(),
      'entity_id' => $host->id(),
    ], [
      'query' => ['editar' => (int) $comment->id()],
    ])->toString();

    if (isset($links['comment']['#links']['comment-edit'])) {
      $this->applyEditAttributes($links['comment']['#links']['comment-edit'], $endpoint);
    }
    if (isset($links['#links']['comment-edit'])) {
      $this->applyEditAttributes($links['#links']['comment-edit'], $endpoint);
    }
  }

  /**
   * Acrescenta ao link de edição os atributos do popup.
   */
  private function applyEditAttributes(array &$link, string $endpoint): void {
    $attributes = $link['attributes'] ?? [];
    $attributes['class'] = array_merge((array) ($attributes['class'] ?? []), ['resposta-acao', 'resposta-acao--editar', 'use-htmx']);
    $attributes['data-action'] = 'resposta-editar';
    $attributes['data-hx-get'] = $endpoint;
    $link['attributes'] = $attributes;
  }

}
