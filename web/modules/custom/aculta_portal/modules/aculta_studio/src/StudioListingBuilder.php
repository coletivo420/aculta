<?php

declare(strict_types=1);

namespace Drupal\aculta_studio;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;

/**
 * Builds the work listings of an area's internal tabs (for example Revista: Todos, Rascunhos, Em revisão).
 *
 * The Studio stores nothing: it reads the owner's nodes with the entity query, accessCheck(TRUE), so each row
 * is still filtered by the node access of the owner. The listing carries the cacheability of the query.
 */
final class StudioListingBuilder {

  use StringTranslationTrait;

  private const LIMIT = 50;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly AccountInterface $account,
    TranslationInterface $stringTranslation,
    private readonly PagerManagerInterface $pagerManager,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Table of nodes of a bundle in a moderation state (or any state when $state is NULL).
   *
   * @param array{bundle: string, state?: string|null} $listing
   */
  public function build(array $listing, AccountInterface $account): array {
    if (($listing['entity'] ?? 'node') === 'comment') {
      return $this->buildComments($listing);
    }
    if (($listing['entity'] ?? 'node') === 'revision') {
      return $this->buildRevisions($listing);
    }
    if (($listing['entity'] ?? 'node') === 'group') {
      return $this->buildGroups($listing);
    }
    $bundle = $listing['bundle'];
    $state = $listing['state'] ?? NULL;
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->sort('changed', 'DESC');
    $mine = ($listing['scope'] ?? '') === 'mine';
    // Restrict candidate discovery at SQL level before handling revisions.
    if ($mine) {
      $query->condition('uid', (int) $account->id());
    }
    else {
      $query->range(0, self::LIMIT);
    }
    // Never enumerate all moderation state entities with accessCheck(FALSE).
    // Resolve a visible node's latest revision, then verify revision access
    // before showing its moderation state or using it for a filtered listing.
    $nids = $query->execute();
    // A personal filtered pager counts *visible matching revisions*, not raw
    // node candidates. This scan is intentionally limited to the owner's
    // authorized entity query; a scalable indexed provider is a later gate.
    $matched = [];
    if ($mine) {
      foreach (array_chunk(array_values($nids), 100) as $chunk) {
        foreach ($storage->loadMultiple($chunk) as $node) {
          if (!$node->access('view', $account)) {
            continue;
          }
          $latestId = $storage->getLatestRevisionId($node->id());
          $latest = $latestId === NULL ? NULL : $storage->loadRevision($latestId);
          if (!$latest instanceof NodeInterface || !self::canViewRevision($latest, $account)) {
            continue;
          }
          $currentState = $latest->hasField('moderation_state')
            ? (string) ($latest->get('moderation_state')->value ?? '')
            : '';
          if ($state === NULL || $currentState === $state) {
            $matched[] = $node->id();
          }
        }
      }
      $pager = $this->pagerManager->createPager(count($matched), 20);
      $nids = array_slice($matched, $pager->getCurrentPage() * 20, 20);
    }
    $rows = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      if (!$node->access('view', $account)) {
        continue;
      }
      $currentState = '';
      $latestId = $storage->getLatestRevisionId($node->id());
      if ($latestId !== NULL) {
        $latest = $storage->loadRevision($latestId);
        if ($latest instanceof NodeInterface && self::canViewRevision($latest, $account)
          && $latest->hasField('moderation_state')) {
          $currentState = (string) ($latest->get('moderation_state')->value ?? '');
        }
      }
      // Fail closed: a private latest revision must not be indexed as public
      // draft/review or cause its metadata to appear in a listing.
      if ($mine && ($latestId === NULL || !isset($latest) || !$latest instanceof NodeInterface || !self::canViewRevision($latest, $account))) {
        continue;
      }
      if ($state !== NULL && $currentState !== $state) {
        continue;
      }
      $row = [
        'title' => ['data' => ['#type' => 'link', '#title' => $node->label(), '#url' => $node->toUrl()]],
        'state' => $currentState,
        'author' => $node->getOwner()?->getDisplayName() ?? '',
        'changed' => $this->dateFormatter->format($node->getChangedTime(), 'short'),
      ];
      if (!empty($listing['editable'])) {
        $row['edit'] = $this->editLink($node);
      }
      $rows[] = $row;
    }

    // A tabela rola dentro do próprio bloco no celular, sem rolagem horizontal da página.
    $header = ['title' => $this->t('Título'), 'state' => $this->t('Situação'), 'author' => $this->t('Autor'), 'changed' => $this->t('Atualizado em')];
    if (!empty($listing['editable'])) {
      $header['edit'] = $this->t('Ações');
    }
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-listing-wrap', 'data-aculta-studio' => 'listing-wrap'],
      'table' => [
        '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('Nenhum item nesta aba.'),
      '#attributes' => ['data-portal-ui' => 'studio-listing', 'data-aculta-studio' => 'listing'],
      ],
      'pager' => $mine ? ['#type' => 'pager'] : [],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.query_args.pagers'],
        'tags' => ['node_list:' . $bundle],
      ],
    ];
    return $build;
  }


  /**
   * Table of comments of a bundle by status (0 = not published, e.g. awaiting moderation), with the node
   * they belong to. Comment access is checked per row through accessCheck(TRUE).
   *
   * @param array{entity: string, bundle: string, status: int} $listing
   */
  private function buildComments(array $listing): array {
    $storage = $this->entityTypeManager->getStorage('comment');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('comment_type', $listing['bundle'])
      ->condition('status', (int) $listing['status'])
      ->sort('created', 'DESC')
      ->range(0, self::LIMIT)
      ->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $comment) {
      $node = $comment->getCommentedEntity();
      $rows[] = [
        'title' => ['data' => ['#type' => 'link', '#title' => $comment->label() ?: $this->t('(sem assunto)'), '#url' => $comment->toUrl()]],
        'state' => $this->t('Aguardando moderação'),
        'author' => $comment->getOwner()?->getDisplayName() ?? '',
        'changed' => $this->dateFormatter->format($comment->getChangedTime(), 'short'),
        'node' => $node ? ['data' => ['#type' => 'link', '#title' => $node->label(), '#url' => $node->toUrl()]] : '',
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-listing-wrap', 'data-aculta-studio' => 'listing-wrap'],
      'table' => [
        '#type' => 'table',
        '#header' => [
          'title' => $this->t('Comentário'),
          'state' => $this->t('Situação'),
          'author' => $this->t('Autor'),
          'changed' => $this->t('Atualizado em'),
          'node' => $this->t('Discussão'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhum item nesta aba.'),
        '#attributes' => ['data-portal-ui' => 'studio-listing', 'data-aculta-studio' => 'listing'],
      ],
      '#cache' => ['contexts' => ['user', 'user.permissions'], 'tags' => ['comment_list']],
    ];
  }


  /**
   * Table of groups of a type (for example the classes of the LMS), each row checked by the Group access.
   *
   * @param array{entity: string, bundle: string} $listing
   */
  private function buildGroups(array $listing): array {
    $storage = $this->entityTypeManager->getStorage('group');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $listing['bundle'])
      ->sort('label', 'ASC')
      ->range(0, self::LIMIT);
    // Contexto de curso: só as turmas ligadas ao curso pela relação do Group (lms_course-lms_classes).
    if (isset($listing['course'])) {
      $classIds = $this->entityTypeManager->getStorage('group_relationship')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'lms_course-lms_classes')
        ->condition('gid', (int) $listing['course'])
        ->execute();
      $linked = [];
      foreach ($this->entityTypeManager->getStorage('group_relationship')->loadMultiple($classIds) as $rel) {
        $linked[] = (int) $rel->get('entity_id')->target_id;
      }
      $query->condition('id', $linked ?: [0], 'IN');
    }
    $ids = $query->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $group) {
      $rows[] = [
        'title' => ['data' => ['#type' => 'link', '#title' => $group->label(), '#url' => $group->toUrl()]],
        'state' => '',
        'author' => $group->getOwner()?->getDisplayName() ?? '',
        'changed' => $this->dateFormatter->format($group->getChangedTime(), 'short'),
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-listing-wrap', 'data-aculta-studio' => 'listing-wrap'],
      'table' => [
        '#type' => 'table',
        '#header' => ['title' => $this->t('Turma'), 'state' => $this->t('Situação'), 'author' => $this->t('Criada por'), 'changed' => $this->t('Atualizada em')],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhum item nesta aba.'),
        '#attributes' => ['data-portal-ui' => 'studio-listing', 'data-aculta-studio' => 'listing'],
      ],
      '#cache' => ['contexts' => ['user', 'user.permissions'], 'tags' => ['group_list']],
    ];
  }


  /**
   * Table of the revisions of nodes of a bundle (the Wiki's history), newest first. Each revision row is checked
   * by the node access of its node through the revision query, with accessCheck(TRUE).
   *
   * @param array{entity: string, bundle: string} $listing
   */
  private function buildRevisions(array $listing): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->allRevisions()
      ->condition('type', $listing['bundle'])
      ->sort('revision_timestamp', 'DESC')
      ->range(0, self::LIMIT)
      ->execute();
    $rows = [];
    foreach ($ids as $vid => $nid) {
      $revision = $storage->loadRevision($vid);
      // Entity-query access alone is not a substitute for per-revision access.
      if (!$revision instanceof NodeInterface || !$this->canViewRevision($revision, $this->account)) {
        continue;
      }
      $rows[] = [
        'title' => ['data' => ['#type' => 'link', '#title' => $revision->label(), '#url' => $revision->toUrl('revision')]],
        'state' => $revision->get('moderation_state')->value ?? '',
        'author' => $revision->getRevisionUser()?->getDisplayName() ?? '',
        'changed' => $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short'),
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-listing-wrap', 'data-aculta-studio' => 'listing-wrap'],
      'table' => [
        '#type' => 'table',
        '#header' => ['title' => $this->t('Revisão'), 'state' => $this->t('Situação'), 'author' => $this->t('Autor da revisão'), 'changed' => $this->t('Revisada em')],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhum item nesta aba.'),
        '#attributes' => ['data-portal-ui' => 'studio-listing', 'data-aculta-studio' => 'listing'],
      ],
      '#cache' => ['contexts' => ['user', 'user.permissions'], 'tags' => ['node_list:' . $listing['bundle']]],
    ];
  }


  /**
   * Requires both entity visibility and explicit access to the revision.
   *
   * A revision can be unpublished even when the default node is public.
   * A permission to view the node never implicitly authorizes draft history.
   */
  public static function canViewRevision(NodeInterface $revision, AccountInterface $account): bool {
    return $revision->access('view', $account)
      && $revision->access('view revision', $account);
  }

  /**
   * Edit link to the owner's edit form in the owner's domain. Access is the owner's node access, evaluated there.
   */
  private function editLink(object $node): array {
    $url = $this->domainPurposes->routeUrl('main', 'entity.node.edit_form', ['node' => $node->id()]);
    if ($url === NULL) {
      return ['data' => ''];
    }
    // The route's own access (admin route, domain and entity checks), not a copy of the rule.
    $account = $this->account;
    $access = $this->domainPurposes->runInPurpose('main', static fn (): bool => $url->access($account));
    if (!$access) {
      return ['data' => ''];
    }
    return ['data' => ['#type' => 'link', '#title' => $this->t('Editar'), '#url' => $url]];
  }

}
