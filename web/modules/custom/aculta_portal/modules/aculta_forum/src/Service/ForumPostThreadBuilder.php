<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;

/**
 * Builds the revisionable posts attached to a public Forum topic.
 */
final class ForumPostThreadBuilder {

  public const POSTS_PER_PAGE = 50;

  /**
   * Pager element distinct from the Comment formatter's element 0.
   */
  public const PAGER_ID = 1;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly DiscussionContextResolver $discussionContextResolver,
    private readonly PagerManagerInterface $pagerManager,
  ) {}

  /**
   * Builds the published thread for a public Forum topic.
   *
   * @return array
   *   A cacheable render array. An empty array means that the topic is outside
   *   the public Forum context or is not viewable by the current account.
   */
  public function build(NodeInterface $topic): array {
    if ($topic->bundle() !== 'aculta_forum_topic'
      || !$topic->isPublished()
      || !$topic->hasField('field_domain_source')
      || $topic->get('field_domain_source')->isEmpty()
      || $this->domainPurposeManager->getCurrentPurpose() !== 'forum'
      || $this->domainPurposeManager->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id) !== 'forum') {
      return [];
    }

    $context = $this->discussionContextResolver->resolve($topic, $this->currentUser);
    $topicAccess = $context?->getReadAccess();
    if ($context === NULL || $topicAccess === NULL || !$topicAccess->isAllowed()) {
      return [];
    }

    $cacheability = CacheableMetadata::createFromObject($context)
      ->addCacheContexts(['domain', 'user', 'user.permissions', 'url.query_args.pagers:' . self::PAGER_ID])
      ->addCacheTags(['aculta_forum_post_list']);
    $canReply = $context->getWriteAccess()->isAllowed();
    $build = [
      '#type' => 'container',
      '#weight' => 90,
      '#attributes' => [
        'data-aculta-discussion-thread' => '',
        'data-history-node-id' => (string) $topic->id(),
      ],
    ];
    $build['#attached']['library'][] = 'aculta_forum/discussion-progress';
    if ($this->currentUser->isAuthenticated()) {
      $build['#attached']['library'][] = 'aculta_forum/forum-post-history';
    }
    if ($context->getWriteAccess()->isAllowed()) {
      $build['actions']['create'] = Link::fromTextAndUrl(
        new TranslatableMarkup('Responder à discussão'),
        Url::fromRoute('aculta_forum.post_create', [], ['query' => ['topico' => $topic->uuid()]]),
      )->toRenderable();
    }

    $query = $this->entityTypeManager->getStorage('aculta_forum_post')->getQuery()
      ->accessCheck(TRUE)
      ->condition('topic.target_id', $topic->id())
      ->condition('status', 1)
      ->sort('thread_position', 'ASC')
      ->sort('created', 'ASC')
      ->sort('id', 'ASC');
    $countQuery = clone $query;
    $totalItems = (int) $countQuery->count()->execute();
    if ($totalItems === 0) {
      $cacheability->applyTo($build);
      return $build;
    }

    $pager = $this->pagerManager->createPager($totalItems, self::POSTS_PER_PAGE, self::PAGER_ID);
    $query->range(self::POSTS_PER_PAGE * $pager->getCurrentPage(), self::POSTS_PER_PAGE);
    $ids = $query->execute();
    if ($ids === []) {
      $cacheability->applyTo($build);
      return $build;
    }

    $storage = $this->entityTypeManager->getStorage('aculta_forum_post');
    $posts = $storage->loadMultiple($ids);
    $viewable = [];
    foreach ($posts as $post) {
      if (!$post->isPublished() || !$post->isDefaultRevision()) {
        continue;
      }
      $access = $post->access('view', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($post)->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      $viewable[] = $post;
    }

    if ($viewable === []) {
      $cacheability->applyTo($build);
      return $build;
    }

    $build['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => new TranslatableMarkup('Respostas da discussão'),
    ];
    $viewableIds = array_fill_keys(array_map(static fn ($post): string => (string) $post->id(), $viewable), TRUE);
    $externalParentIds = [];
    foreach ($viewable as $post) {
      if (!$post->get('parent')->isEmpty()) {
        $parentId = (string) $post->get('parent')->target_id;
        if (!isset($viewableIds[$parentId])) {
          $externalParentIds[$parentId] = $parentId;
        }
      }
    }
    $parentEntities = $storage->loadMultiple($externalParentIds);
    foreach ($viewable as $post) {
      $postId = (string) $post->id();
      $renderedPost = $this->entityTypeManager->getViewBuilder('aculta_forum_post')
        ->view($post, 'default');
      $parent = NULL;
      if (!$post->get('parent')->isEmpty()) {
        $parentId = (string) $post->get('parent')->target_id;
        $parent = isset($viewableIds[$parentId])
          ? ($posts[$parentId] ?? NULL)
          : ($parentEntities[$parentId] ?? NULL);
        if ($parent !== NULL
          && (!$parent->isPublished()
            || !$parent->isDefaultRevision()
            || (string) $parent->get('topic')->target_id !== (string) $topic->id())) {
          $parent = NULL;
        }
        if ($parent !== NULL) {
          $parentAccess = $parent->access('view', $this->currentUser, TRUE);
          $cacheability->addCacheableDependency($parent)->addCacheableDependency($parentAccess);
          if (!$parentAccess->isAllowed()) {
            $parent = NULL;
          }
        }
      }
      $attributes = [
        'id' => 'forum-post-' . $postId,
        'data-aculta-forum-post-timestamp' => (string) $post->get('first_published')->value,
      ];
      $build['posts'][$postId] = [
        '#type' => 'html_tag',
        '#tag' => 'article',
        '#attributes' => $attributes,
        'content' => $renderedPost,
      ];
      if ($parent !== NULL) {
        $parentLabel = new TranslatableMarkup('Em resposta a: @subject', [
          '@subject' => $parent->label(),
        ]);
        $build['posts'][$postId]['reply_context'] = [
          '#type' => 'container',
        ];
        if (isset($viewableIds[(string) $parent->id()])) {
          $build['posts'][$postId]['reply_context']['parent'] = Link::fromTextAndUrl(
            $parentLabel,
            Url::fromRoute('entity.node.canonical', ['node' => $topic->id()], [
              'fragment' => 'forum-post-' . $parent->id(),
            ]),
          )->toRenderable();
        }
        else {
          $parentPage = $this->findPostPage($topic, $parent);
          if ($parentPage !== NULL) {
            $query = $parentPage === $pager->getCurrentPage()
              ? []
              : $this->pagerManager->getUpdatedParameters([], self::PAGER_ID, $parentPage);
            $build['posts'][$postId]['reply_context']['parent'] = Link::fromTextAndUrl(
              $parentLabel,
              Url::fromRoute('entity.node.canonical', ['node' => $topic->id()], [
                'query' => $query,
                'fragment' => 'forum-post-' . $parent->id(),
              ]),
            )->toRenderable();
          }
          else {
            $build['posts'][$postId]['reply_context']['parent'] = [
              '#plain_text' => $parentLabel,
            ];
          }
        }
      }
      if ($canReply) {
        $build['posts'][$postId]['reply'] = Link::fromTextAndUrl(
          new TranslatableMarkup('Responder a esta publicação'),
          Url::fromRoute('aculta_forum.post_create', [], [
            'query' => [
              'topico' => $topic->uuid(),
              'resposta' => $post->uuid(),
            ],
          ]),
        )->toRenderable();
      }
      $editAccess = $post->access('update', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($editAccess);
      if ($editAccess->isAllowed()) {
        $build['posts'][$postId]['edit'] = Link::fromTextAndUrl(
          new TranslatableMarkup('Editar publicação'),
          Url::fromRoute('aculta_forum.post_edit', [], [
            'query' => ['post' => $post->uuid()],
          ]),
        )->toRenderable();
      }
    }
    if ($totalItems > self::POSTS_PER_PAGE) {
      $build['pager'] = [
        '#type' => 'pager',
        '#element' => self::PAGER_ID,
      ];
    }

    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Finds the pager page of a public parent using the thread's sort order.
   */
  private function findPostPage(NodeInterface $topic, ForumPost $parent): ?int {
    $storage = $this->entityTypeManager->getStorage('aculta_forum_post');
    $query = $storage->getQuery()->accessCheck(TRUE);
    $position = (int) $parent->get('thread_position')->value;
    $created = (int) $parent->get('created')->value;
    $parentId = (int) $parent->id();
    $preceding = $query->orConditionGroup()
      ->condition('thread_position', $position, '<')
      ->condition($query->andConditionGroup()
        ->condition('thread_position', $position)
        ->condition('created', $created, '<'))
      ->condition($query->andConditionGroup()
        ->condition('thread_position', $position)
        ->condition('created', $created)
        ->condition('id', $parentId, '<'));
    $precedingCount = (int) $query
      ->condition('topic.target_id', $topic->id())
      ->condition('status', 1)
      ->condition($preceding)
      ->count()
      ->execute();

    return intdiv($precedingCount, self::POSTS_PER_PAGE);
  }

}
