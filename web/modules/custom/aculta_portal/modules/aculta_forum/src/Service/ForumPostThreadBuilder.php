<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\aculta_portal\Domain\DomainSiteManager;

/**
 * Builds the revisionable posts attached to a public Forum topic.
 */
final class ForumPostThreadBuilder {

  public const POSTS_PER_PAGE = 50;

  /**
   * Pager element distinct from the Comment formatter's element 0.
   */
  public const PAGER_ID = 1;

  /**
   * Comment Core uses pager element 0 on public topic pages.
   */
  public const COMMENT_PAGER_ID = 0;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainSiteManager $siteManager,
  ) {}

  /**
   * Finds the public thread page containing a post in the thread sort order.
   */
  public function getPageForPost(NodeInterface $topic, ForumPost $post): ?int {
    if (!$this->isPublicForumTopic($topic)
      || !$post->isPublished()
      || $post->get('topic')->target_id != $topic->id()
      || $post->get('thread_position')->isEmpty()
      || $post->get('created')->isEmpty()
      || $post->id() === NULL) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('aculta_forum_post');
    $query = $storage->getQuery()->accessCheck(TRUE);
    $position = (int) $post->get('thread_position')->value;
    $created = (int) $post->get('created')->value;
    $postId = (int) $post->id();
    $preceding = $query->orConditionGroup()
      ->condition('thread_position', $position, '<')
      ->condition($query->andConditionGroup()
        ->condition('thread_position', $position)
        ->condition('created', $created, '<'))
      ->condition($query->andConditionGroup()
        ->condition('thread_position', $position)
        ->condition('created', $created)
        ->condition('id', $postId, '<'));
    $precedingCount = (int) $query
      ->condition('topic.target_id', $topic->id())
      ->condition('status', 1)
      ->condition($preceding)
      ->count()
      ->execute();

    return intdiv($precedingCount, self::POSTS_PER_PAGE);
  }

  /**
   * Builds pager query parameters for a link landing on a topic page.
   *
   * Search result requests have their own active pager state. A cross-route
   * link must start the topic's Comment pager at page zero and set only the
   * ForumPost pager to the requested page.
   *
   * @return array<string, string>
   *   The target topic pager query, independent of the current request.
   */
  public function getTopicPagerQuery(int $page): array {
    $pages = array_fill(0, max(self::PAGER_ID, self::COMMENT_PAGER_ID) + 1, 0);
    $pages[self::PAGER_ID] = max(0, $page);
    return ['page' => implode(',', $pages)];
  }

  /**
   * Checks that the current request and topic belong to the public Forum.
   */
  private function isPublicForumTopic(NodeInterface $topic): bool {
    return $topic->bundle() === 'aculta_forum_topic'
      && $topic->isPublished()
      && $topic->hasField('field_domain_source')
      && !$topic->get('field_domain_source')->isEmpty()
      && $this->siteManager->getCurrentSiteKey()?->value === 'forum'
      && $this->siteManager->getSiteKeyValueForDomainId((string) $topic->get('field_domain_source')->target_id) === 'forum';
  }

}
