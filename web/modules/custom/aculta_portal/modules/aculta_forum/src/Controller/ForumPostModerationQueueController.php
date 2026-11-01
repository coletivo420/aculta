<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Lists the current review revisions of revisionable Forum posts.
 */
final class ForumPostModerationQueueController extends ControllerBase {

  private const ITEMS_PER_PAGE = 50;

  public function __construct(
    private readonly EntityTypeManagerInterface $forumEntityTypeManager,
    private readonly AccountProxyInterface $forumCurrentUser,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Builds a moderation queue using Core's Content Moderation state storage.
   */
  public function queue(): array {
    $moderationStateStorage = $this->forumEntityTypeManager->getStorage('content_moderation_state');
    $result = $moderationStateStorage->getAggregateQuery()
      ->accessCheck(TRUE)
      ->aggregate('content_entity_id', 'MAX')
      ->groupBy('content_entity_revision_id')
      ->condition('content_entity_type_id', 'aculta_forum_post')
      ->condition('workflow', 'aculta_forum_post')
      ->condition('moderation_state', 'review')
      ->sort('content_entity_revision_id', 'DESC')
      ->pager(self::ITEMS_PER_PAGE)
      ->execute();
    $revisionIds = array_column($result, 'content_entity_revision_id');
    $postStorage = $this->forumEntityTypeManager->getStorage('aculta_forum_post');
    $revisions = $revisionIds === [] ? [] : $postStorage->loadMultipleRevisions($revisionIds);

    $rows = [];
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts([
        'domain',
        'user',
        'user.permissions',
        'url.query_args.pagers:0',
        'languages:language_interface',
      ])
      ->addCacheTags(['aculta_forum_post_list', 'content_moderation_state_list', 'config:system.date']);
    foreach ($revisions as $revisionId => $post) {
      $access = $post->access('view', $this->forumCurrentUser, TRUE);
      $cacheability->addCacheableDependency($post)->addCacheableDependency($access);
      if (!$access->isAllowed() || $post->get('moderation_state')->value !== 'review') {
        continue;
      }

      $topic = $post->get('topic')->entity;
      if (!$topic instanceof NodeInterface
        || $topic->bundle() !== 'aculta_forum_topic'
        || !$topic->isPublished()
        || !$topic->hasField('field_domain_source')
        || $topic->get('field_domain_source')->isEmpty()
        || $this->domainPurposeManager->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id) !== 'forum') {
        continue;
      }
      $topicAccess = $topic->access('view', $this->forumCurrentUser, TRUE);
      $cacheability->addCacheableDependency($topic)->addCacheableDependency($topicAccess);
      if (!$topicAccess->isAllowed()) {
        continue;
      }
      $owner = $post->getOwner();
      if ($owner !== NULL) {
        $cacheability->addCacheableDependency($owner);
      }

      $rows[] = [
        ['data' => ['#plain_text' => (string) $post->label()]],
        ['data' => ['#plain_text' => $topic?->label() ?? $this->t('Tópico indisponível')]],
        ['data' => ['#plain_text' => $owner?->getDisplayName() ?? $this->t('Pessoa anônima')]],
        ['data' => ['#plain_text' => $this->dateFormatter->format((int) $post->get('created')->value, 'short')]],
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'review' => [
                'title' => $this->t('Revisar'),
                'url' => Url::fromRoute('aculta_forum.post_moderate', ['revision_id' => (string) $revisionId]),
              ],
              'history' => [
                'title' => $this->t('Histórico'),
                'url' => Url::fromRoute('aculta_forum.post_revision_history', ['aculta_forum_post' => $post->id()]),
              ],
            ],
          ],
        ],
      ];
    }

    $build = [
      '#type' => 'table',
      '#header' => [
        $this->t('Publicação'),
        $this->t('Tópico'),
        $this->t('Autoria'),
        $this->t('Criada'),
        $this->t('Ação'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Não há publicações aguardando moderação.'),
      'pager' => [
        '#type' => 'pager',
      ],
    ];
    $cacheability->applyTo($build);
    return $build;
  }

}
