<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Service\ForumPostThreadBuilder;
use Drupal\aculta_forum\Service\ForumPostUnreadManager;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Renders the Forum's History-backed reading summary on public topic pages.
 *
 * History is a visit receipt rather than a per-post scrolling event. Therefore
 * this UI presents "recent publications since your last visit", not a false
 * pixel-accurate Discourse reading timeline.
 */
final class ForumReadingTimelineHooks {

  public function __construct(
    private readonly ForumPostUnreadManager $unreadManager,
    private readonly ForumPostThreadBuilder $threadBuilder,
    private readonly EntityTypeManagerInterface $entities,
    private readonly DomainSiteManager $sites,
    private readonly AccountProxyInterface $account,
  ) {}

  /**
   * Adds authorized navigation to the first unread and last post pages.
   *
   * @param array<string, mixed> $build
   *   Node render array.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, string $viewMode): void {
    if ($viewMode !== 'full'
      || $node->bundle() !== 'aculta_forum_topic'
      || !$node->isPublished()
      || $this->account->isAnonymous()
      || $this->sites->getCurrentSiteKey()?->value !== 'forum'
      || !$node->hasField('field_domain_source')
      || $node->get('field_domain_source')->isEmpty()
      || $this->sites->getSiteKeyValueForDomainId((string) $node->get('field_domain_source')->target_id) !== 'forum') {
      return;
    }
    $access = $node->access('view', $this->account, TRUE);
    if (!$access->isAllowed() || !is_numeric($node->id())) {
      return;
    }

    $topicId = (int) $node->id();
    $total = (int) $this->entities->getStorage('aculta_forum_post')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('topic.target_id', $topicId)
      ->condition('status', 1)
      ->count()
      ->execute();
    if ($total < 1) {
      return;
    }

    $receipt = $this->unreadManager->getUnreadByTopics([$topicId])[$topicId] ?? NULL;
    $unread = min($total, max(0, (int) ($receipt['count'] ?? 0)));
    $read = $total - $unread;
    $navigation = [
      '#type' => 'container',
      '#attributes' => ['class' => ['forum-reading-timeline__navigation']],
      'start' => Link::fromTextAndUrl(
        t('Início do tópico'),
        Url::fromRoute('entity.node.canonical', ['node' => $topicId]),
      )->toRenderable(),
    ];

    if ($unread > 0 && is_array($receipt)
      && (int) $receipt['post_id'] > 0 && (int) $receipt['page'] >= 0) {
      $firstUrl = Url::fromRoute('entity.node.canonical', ['node' => $topicId], [
        'query' => $this->threadBuilder->getTopicPagerQuery((int) $receipt['page']),
        'fragment' => 'forum-post-' . (int) $receipt['post_id'],
      ]);
      $navigation['unread'] = Link::fromTextAndUrl(
        t('Primeira publicação não lida'),
        $firstUrl,
      )->toRenderable();
    }

    $lastPage = intdiv($total - 1, ForumPostThreadBuilder::POSTS_PER_PAGE);
    $navigation['last'] = Link::fromTextAndUrl(
      t('Última página de publicações'),
      Url::fromRoute('entity.node.canonical', ['node' => $topicId], [
        'query' => $this->threadBuilder->getTopicPagerQuery($lastPage),
      ]),
    )->toRenderable();

    $timeline = [
      '#theme' => 'portal_component',
      '#name' => 'forum-reading-timeline',
      '#props' => [
        'label' => (string) t('Acompanhamento de novas publicações'),
        'percent' => self::progressPercent($total, $unread),
        'progress_text' => (string) t('@read de @total publicações anteriores à última visita', [
          '@read' => (string) $read,
          '@total' => (string) $total,
        ]),
      ],
      '#slots' => ['navigation' => $navigation],
      '#weight' => 18,
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'user.node_grants:view', 'domain', 'languages:language_interface'],
        'max-age' => 0,
      ],
    ];
    CacheableMetadata::createFromObject($node)
      ->addCacheableDependency($access)
      ->addCacheTags(['aculta_forum_post_list'])
      ->applyTo($timeline);
    $build['aculta_forum_reading_timeline'] = $timeline;
  }

  /**
   * Returns a bounded ratio derived from the existing per-topic History receipt.
   */
  public static function progressPercent(int $total, int $unread): int {
    if ($total <= 0) {
      return 0;
    }
    return (int) round(100 * ($total - min($total, max(0, $unread))) / $total);
  }

}
