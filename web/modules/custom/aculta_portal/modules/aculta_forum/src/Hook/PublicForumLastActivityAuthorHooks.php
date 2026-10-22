<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Service\ForumPublicPostStatistics;
use Drupal\aculta_user\Entity\LegacyAuthor;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\UserInterface;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Prepares the author of the latest public topic activity.
 */
final class PublicForumLastActivityAuthorHooks {

  /**
   * Loaded ForumPost entities used for activity cards in this request.
   *
   * @var \Drupal\aculta_forum\Entity\ForumPost[]
   */
  private array $forumPosts = [];

  /**
   * User accounts needed for activity cards, keyed by account ID.
   *
   * @var \Drupal\user\UserInterface[]
   */
  private array $accounts = [];

  /**
   * Historical authors needed for activity cards, keyed by entity ID.
   *
   * @var \Drupal\aculta_user\Entity\LegacyAuthor[]
   */
  private array $legacyAuthors = [];

  /**
   * Activity and identity batches already prepared for a View render.
   *
   * @var \WeakMap<\Drupal\views\ViewExecutable, array<int, array{count: int, last_activity: int, last_post_id: int}>>
   */
  private \WeakMap $preparedViews;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_user.author_card_builder')]
    private readonly AuthorCardBuilder $authorCardBuilder,
    private readonly ForumPublicPostStatistics $postStatistics,
  ) {
    $this->preparedViews = new \WeakMap();
  }

  /**
   * Adds a cacheable identity card to public forum topic rows.
   *
   * Core Comment statistics and the ForumPost aggregate identify the latest
   * response. Identity and historical-author presentation remain owned by
   * aculta_user; Drupal login names are never used for presentation.
   */
  #[Hook('preprocess_views_view_fields')]
  public function preprocessViewsViewFields(array &$variables): void {
    $view = $variables['view'] ?? NULL;
    if ($view === NULL || $view->id() !== 'aculta_forum_public_topics') {
      return;
    }

    $row = $variables['row'] ?? NULL;
    if (!$row instanceof ResultRow || !isset($view->field['nid'], $view->field['last_comment_uid'], $view->field['last_comment_timestamp'], $view->field['last_comment_count'], $view->field['last_updated'])) {
      return;
    }

    if (isset($this->preparedViews[$view])) {
      $statistics = $this->preparedViews[$view];
    }
    else {
      $topicIds = [];
      foreach ($view->result as $resultRow) {
        if ($resultRow instanceof ResultRow) {
          $topicIds[] = (int) $view->field['nid']->getValue($resultRow);
        }
      }
      $statistics = $this->postStatistics->getByTopics($topicIds);
      $postIds = array_values(array_unique(array_filter(array_map(
        static fn (array $item): int => (int) $item['last_post_id'],
        $statistics,
      ))));
      $missingPostIds = array_values(array_diff($postIds, array_keys($this->forumPosts)));
      if ($missingPostIds !== []) {
        $this->forumPosts += $this->entityTypeManager->getStorage('aculta_forum_post')->loadMultiple($missingPostIds);
      }
      $this->preloadAuthors($view, $statistics);
      $this->preparedViews[$view] = $statistics;
    }

    $topicId = (int) $view->field['nid']->getValue($row);
    $commentCount = (int) $view->field['last_comment_count']->getValue($row);
    $commentTimestamp = $commentCount > 0 ? (int) $view->field['last_comment_timestamp']->getValue($row) : 0;
    $latestActivity = (int) $view->field['last_updated']->getValue($row);
    $post = $statistics[$topicId] ?? ['count' => 0, 'last_activity' => 0, 'last_post_id' => 0];
    $postTimestamp = (int) $post['last_activity'];
    $latestResponse = max($commentTimestamp, $postTimestamp);

    // A topic edit can be the latest activity without a latest responder.
    if ($topicId < 1 || $latestResponse < 1 || $latestActivity > $latestResponse) {
      return;
    }

    if ($postTimestamp >= $commentTimestamp && (int) $post['last_post_id'] > 0) {
      $postEntity = $this->forumPosts[(int) $post['last_post_id']] ?? NULL;
      if ($postEntity instanceof ForumPost && $postEntity->hasField('legacy_author') && !$postEntity->get('legacy_author')->isEmpty()) {
        $legacyAuthor = $this->legacyAuthors[(int) $postEntity->get('legacy_author')->target_id] ?? NULL;
        if ($legacyAuthor instanceof LegacyAuthor) {
          $variables['last_comment_author'] = $this->authorCardBuilder->buildLegacy($legacyAuthor);
        }
        return;
      }

      $accountId = $postEntity instanceof ForumPost ? (int) $postEntity->getOwnerId() : 0;
    }
    else {
      $accountId = (int) $view->field['last_comment_uid']->getValue($row);
    }

    $account = $this->accounts[$accountId] ?? NULL;
    if (!$account instanceof UserInterface) {
      return;
    }

    if (!$account->isActive()) {
      $placeholder = ['#markup' => ''];
      CacheableMetadata::createFromObject($account)->applyTo($placeholder);
      $variables['last_comment_author_cache'] = $placeholder;
      return;
    }

    $card = $this->authorCardBuilder->build($account, 'author_forum');
    if ($card !== NULL) {
      $variables['last_comment_author'] = $card;
      return;
    }

    // Keep the no-card result fresh if a public nickname/profile is added.
    $placeholder = ['#markup' => ''];
    CacheableMetadata::createFromObject($account)->applyTo($placeholder);
    $placeholder['#cache']['max-age'] = 0;
    $variables['last_comment_author_cache'] = $placeholder;
  }

  /**
   * Loads all public response authors for the current View page in batches.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The public topic View.
   * @param array<int, array{count: int, last_activity: int, last_post_id: int}> $statistics
   *   Published ForumPost aggregates keyed by topic ID.
   */
  private function preloadAuthors(ViewExecutable $view, array $statistics): void {
    $accountIds = [];
    $legacyAuthorIds = [];
    foreach ($view->result as $resultRow) {
      if (!$resultRow instanceof ResultRow) {
        continue;
      }
      $topicId = (int) $view->field['nid']->getValue($resultRow);
      $commentCount = (int) $view->field['last_comment_count']->getValue($resultRow);
      $commentTimestamp = $commentCount > 0 ? (int) $view->field['last_comment_timestamp']->getValue($resultRow) : 0;
      $latestActivity = (int) $view->field['last_updated']->getValue($resultRow);
      $post = $statistics[$topicId] ?? ['count' => 0, 'last_activity' => 0, 'last_post_id' => 0];
      $postTimestamp = (int) $post['last_activity'];
      $latestResponse = max($commentTimestamp, $postTimestamp);
      if ($latestResponse < 1 || $latestActivity > $latestResponse) {
        continue;
      }

      if ($postTimestamp >= $commentTimestamp && (int) $post['last_post_id'] > 0) {
        $postEntity = $this->forumPosts[(int) $post['last_post_id']] ?? NULL;
        if ($postEntity instanceof ForumPost && $postEntity->hasField('legacy_author') && !$postEntity->get('legacy_author')->isEmpty()) {
          $legacyAuthorId = (int) $postEntity->get('legacy_author')->target_id;
          if ($legacyAuthorId > 0) {
            $legacyAuthorIds[] = $legacyAuthorId;
          }
        }
        elseif ($postEntity instanceof ForumPost && $postEntity->getOwnerId() > 0) {
          $accountIds[] = (int) $postEntity->getOwnerId();
        }
      }
      else {
        $uid = (int) $view->field['last_comment_uid']->getValue($resultRow);
        if ($uid > 0) {
          $accountIds[] = $uid;
        }
      }
    }

    $accountIds = array_values(array_diff(array_unique($accountIds), array_keys($this->accounts)));
    if ($accountIds !== []) {
      $this->accounts += $this->entityTypeManager->getStorage('user')->loadMultiple($accountIds);
    }
    $legacyAuthorIds = array_values(array_diff(array_unique($legacyAuthorIds), array_keys($this->legacyAuthors)));
    if ($legacyAuthorIds !== []) {
      $this->legacyAuthors += $this->entityTypeManager->getStorage('aculta_legado_autor')->loadMultiple($legacyAuthorIds);
    }
  }

}
