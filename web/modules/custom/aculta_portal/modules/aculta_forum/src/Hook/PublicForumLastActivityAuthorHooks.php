<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Service\ForumPublicPostStatistics;
use Drupal\aculta_user\Entity\LegacyAuthor;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\UserInterface;
use Drupal\views\ResultRow;
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

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_user.author_card_builder')]
    private readonly AuthorCardBuilder $authorCardBuilder,
    private readonly ForumPublicPostStatistics $postStatistics,
  ) {}

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
      if ($postEntity !== NULL && $postEntity->hasField('legacy_author') && !$postEntity->get('legacy_author')->isEmpty()) {
        $legacyAuthor = $postEntity->get('legacy_author')->entity;
        if ($legacyAuthor instanceof LegacyAuthor) {
          $variables['last_comment_author'] = $this->authorCardBuilder->buildLegacy($legacyAuthor);
        }
        return;
      }

      $account = $postEntity?->getOwner();
    }
    else {
      $uid = (int) $view->field['last_comment_uid']->getValue($row);
      $account = $uid > 0 ? $this->entityTypeManager->getStorage('user')->load($uid) : NULL;
    }

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

}
