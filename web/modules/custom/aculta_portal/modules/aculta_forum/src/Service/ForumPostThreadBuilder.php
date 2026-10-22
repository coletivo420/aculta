<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\user\AccountProxyInterface;

/**
 * Builds the revisionable posts attached to a public Forum topic.
 */
final class ForumPostThreadBuilder {

  private const POSTS_PER_PAGE = 50;

  /**
   * Pager element distinct from the Comment formatter's element 0.
   */
  private const PAGER_ID = 1;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
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
        'class' => ['aculta-forum-revisionable-posts'],
      ],
    ];
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
    $renderedPosts = $this->entityTypeManager->getViewBuilder('aculta_forum_post')
      ->viewMultiple($viewable, 'default');
    foreach ($viewable as $delta => $post) {
      $postId = (string) $post->id();
      $attributes = [
        'id' => 'forum-post-' . $postId,
        'class' => ['aculta-forum-post'],
      ];
      if (!$post->get('parent')->isEmpty()) {
        $parentId = (string) $post->get('parent')->target_id;
        if (isset($viewableIds[$parentId])) {
          $attributes['data-forum-post-parent'] = $parentId;
        }
      }
      $build['posts'][$postId] = [
        '#type' => 'html_tag',
        '#tag' => 'article',
        '#attributes' => $attributes,
        'content' => $renderedPosts[$delta],
      ];
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

}
