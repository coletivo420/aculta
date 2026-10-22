<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Displays revision metadata for one revisionable Forum post.
 */
final class ForumPostRevisionHistoryController extends ControllerBase {

  private const REVISIONS_PER_PAGE = 50;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Builds the administrative history table for a post.
   */
  public function history(ForumPost $aculta_forum_post): array {
    $storage = $this->entityTypeManager->getStorage('aculta_forum_post');
    assert($storage instanceof RevisionableStorageInterface);
    $entityType = $aculta_forum_post->getEntityType();
    $revisionIds = array_keys($storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($entityType->getKey('id'), $aculta_forum_post->id())
      ->sort($entityType->getKey('revision'), 'DESC')
      ->pager(self::REVISIONS_PER_PAGE)
      ->execute());
    $olderRevisionId = NULL;
    $oldestVisibleRevisionId = end($revisionIds);
    if ($oldestVisibleRevisionId !== FALSE) {
      $olderRevisionIds = $storage->getQuery()
        ->accessCheck(FALSE)
        ->allRevisions()
        ->condition($entityType->getKey('id'), $aculta_forum_post->id())
        ->condition($entityType->getKey('revision'), $oldestVisibleRevisionId, '<')
        ->sort($entityType->getKey('revision'), 'DESC')
        ->range(0, 1)
        ->execute();
      $olderRevisionId = array_key_first($olderRevisionIds);
    }
    $revisions = $storage->loadMultipleRevisions($revisionIds);
    $rows = [];
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($aculta_forum_post)
      ->addCacheContexts([
        'domain',
        'user',
        'user.permissions',
        'url.query_args.pagers:0',
        'languages:language_interface',
      ])
      ->addCacheTags(['aculta_forum_post_list', 'config:system.date']);

    foreach ($revisionIds as $index => $revisionId) {
      $revision = $revisions[$revisionId];
      $cacheability->addCacheableDependency($revision);
      $revisionUser = $revision->getRevisionUser();
      if ($revisionUser instanceof UserInterface) {
        $cacheability->addCacheableDependency($revisionUser);
      }
      $isDefault = $revision->isDefaultRevision();
      $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
      $revisionLabel = $revision->getRevisionLogMessage();
      $operations = [];
      $previousRevisionId = $revisionIds[$index + 1] ?? $olderRevisionId;
      if ($previousRevisionId !== NULL) {
        $operations[] = Link::fromTextAndUrl(
          $this->t('Comparar com a revisão anterior'),
          Url::fromRoute('aculta_forum.post_revision_compare', [
            'aculta_forum_post' => $aculta_forum_post->id(),
            'left_revision' => $previousRevisionId,
            'right_revision' => $revisionId,
          ]),
        )->toRenderable();
      }
      $rows[] = [
        ['data' => ['#plain_text' => (string) $revision->getRevisionId()]],
        ['data' => ['#plain_text' => $date]],
        [
          'data' => $revisionUser instanceof UserInterface
            ? [
              '#theme' => 'username',
              '#account' => $revisionUser,
            ]
            : ['#plain_text' => $this->t('Pessoa desconhecida')],
        ],
        ['data' => ['#plain_text' => (string) ($revision->get('moderation_state')->value ?? '')]],
        ['data' => ['#plain_text' => (string) $revision->label()]],
        ['data' => ['#plain_text' => $revisionLabel]],
        [
          'data' => [
            '#plain_text' => $isDefault ? $this->t('Revisão atual') : $this->t('Histórica'),
          ],
        ],
        ['data' => $operations],
      ];
    }

    $build = [
      '#type' => 'table',
      '#header' => [
        $this->t('ID da revisão'),
        $this->t('Data'),
        $this->t('Autoria da revisão'),
        $this->t('Estado'),
        $this->t('Assunto'),
        $this->t('Mensagem da revisão'),
        $this->t('Situação'),
        $this->t('Operações'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Esta publicação ainda não tem revisões.'),
      'pager' => ['#type' => 'pager'],
      'back' => Link::fromTextAndUrl(
        $this->t('Voltar à fila de moderação'),
        Url::fromRoute('aculta_forum.post_moderation_queue'),
      )->toRenderable(),
    ];
    $cacheability->applyTo($build);
    return $build;
  }

}
