<?php

declare(strict_types=1);

namespace Drupal\aculta_studio;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_wiki\Service\WikiEditorialQueue;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
    private readonly RequestStack $requestStack,
    private readonly ?WikiEditorialQueue $wikiEditorialQueue = NULL,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Table of nodes of a bundle in a moderation state (or any state when $state is NULL).
   *
   * @param array{bundle: string, state?: string|null, purpose?: string} $listing
   *   Listing definition selected by the area owner.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account that receives the rendered listing.
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
    if (($listing['entity'] ?? '') === 'wiki_proposal_queue') {
      return $this->buildWikiProposalQueue($account);
    }
    if (($listing['entity'] ?? '') === 'wiki_entry_review_queue') {
      return $this->buildWikiEntryReviewQueue($account);
    }
    $bundle = $listing['bundle'];
    $state = $listing['state'] ?? NULL;
    $storage = $this->entityTypeManager->getStorage('node');
    $mine = ($listing['scope'] ?? '') === 'mine';
    $nids = [];
    $nextCursor = NULL;
    if ($mine) {
      // Seek pagination: never enumerate the entire author's work to compute
      // a total. The cursor is an untrusted position, never an access token.
      $request = $this->requestStack->getCurrentRequest();
      $rawCursor = $request?->query->get('studio_cursor');
      $cursor = is_string($rawCursor) && preg_match('/^([0-9]{1,12})-([1-9][0-9]{0,12})$/D', $rawCursor, $parts)
        ? [(int) $parts[1], (int) $parts[2]]
        : NULL;
      $finished = FALSE;
      // Keep work per HTTP request bounded, including sparse state filters.
      for ($batch = 0; $batch < 10 && count($nids) < 20; $batch++) {
        $query = $storage->getQuery()
          ->accessCheck(TRUE)
          ->condition('type', $bundle)
          ->condition('uid', (int) $account->id())
          ->sort('changed', 'DESC')
          ->sort('nid', 'DESC')
          ->range(0, 100);
        if ($cursor !== NULL) {
          $seek = $query->orConditionGroup()
            ->condition('changed', $cursor[0], '<')
            ->condition($query->andConditionGroup()
              ->condition('changed', $cursor[0])
              ->condition('nid', $cursor[1], '<'));
          $query->condition($seek);
        }
        $batchIds = array_values($query->execute());
        if ($batchIds === []) {
          $finished = TRUE;
          break;
        }
        // loadMultiple is not guaranteed to preserve input query ordering.
        $loaded = $storage->loadMultiple($batchIds);
        foreach ($batchIds as $nid) {
          $node = $loaded[$nid] ?? NULL;
          if (!$node instanceof NodeInterface) {
            continue;
          }
          $cursor = [$node->getChangedTime(), (int) $node->id()];
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
            $nids[] = $node->id();
          }
          if (count($nids) === 20) {
            break;
          }
        }
        if (count($batchIds) < 100) {
          $finished = TRUE;
          break;
        }
      }
      if (!$finished && $cursor !== NULL) {
        $nextCursor = implode('-', $cursor);
      }
    }
    else {
      $nids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $bundle)
        ->sort('changed', 'DESC')
        ->range(0, self::LIMIT)
        ->execute();
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
        $row['edit'] = $this->editLink($node, $listing['purpose'] ?? 'main');
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
      'pager' => $mine && $nextCursor !== NULL
        ? [
          '#type' => 'link',
          '#title' => $this->t('Carregar mais publicações'),
          '#url' => Url::fromUri('internal:' . ($this->requestStack->getCurrentRequest()?->getPathInfo() ?? '/studio/ganjalab'), [
            'query' => ['studio_cursor' => $nextCursor],
          ]),
        ]
        : [],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.query_args:studio_cursor'],
        'tags' => ['node_list:' . $bundle],
      ],
    ];
    return $build;
  }

  /**
   * Renders the Wiki owner's access-checked pending proposal page.
   */
  private function buildWikiProposalQueue(AccountInterface $account): array {
    if (!$this->wikiEditorialQueue) {
      return $this->emptyWikiQueue();
    }
    $request = $this->requestStack->getCurrentRequest();
    $cursor = $request?->query->get('wiki_proposal_cursor');
    try {
      $page = $this->wikiEditorialQueue->proposals(is_string($cursor) ? $cursor : NULL);
    }
    catch (AccessDeniedHttpException) {
      return $this->emptyWikiQueue();
    }

    $rows = [];
    foreach ($page->getItems() as $proposal) {
      $rows[] = [
        'proposal' => [
          'data' => [
            '#type' => 'link',
            '#title' => $proposal->label(),
            '#url' => Url::fromRoute('aculta_wiki.proposal_decide', ['proposal' => $proposal->id()]),
          ],
        ],
        'state' => $proposal->get('state')->value,
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-listing-wrap', 'data-aculta-studio' => 'listing-wrap'],
      'table' => [
        '#type' => 'table',
        '#header' => ['proposal' => $this->t('Proposta'), 'state' => $this->t('Situação')],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhuma proposta aguarda avaliação.'),
        '#attributes' => ['data-portal-ui' => 'studio-listing', 'data-aculta-studio' => 'listing'],
      ],
      'pager' => $page->getNextCursor() !== NULL ? [
        '#type' => 'link',
        '#title' => $this->t('Carregar mais propostas'),
        '#url' => Url::fromRoute('aculta_studio.tab', [
          'aba' => 'wiki',
          'secao' => 'moderacao',
          'sub' => 'propostas',
        ], ['query' => ['wiki_proposal_cursor' => $page->getNextCursor()]]),
      ] : [],
    ];
    CacheableMetadata::createFromObject($page)
      ->addCacheContexts(['url.query_args:wiki_proposal_cursor'])
      ->applyTo($build);
    return $build;
  }

  /**
   * Renders the Wiki owner's access-checked current review revisions.
   */
  private function buildWikiEntryReviewQueue(AccountInterface $account): array {
    if (!$this->wikiEditorialQueue) {
      return $this->emptyWikiQueue();
    }
    $request = $this->requestStack->getCurrentRequest();
    $cursor = $request?->query->get('wiki_revision_cursor');
    try {
      $page = $this->wikiEditorialQueue->entryRevisions(is_string($cursor) ? $cursor : NULL);
    }
    catch (AccessDeniedHttpException) {
      return $this->emptyWikiQueue();
    }

    $cacheability = CacheableMetadata::createFromObject($page)
      ->addCacheContexts(['url.query_args:wiki_revision_cursor']);
    $rows = [];
    foreach ($page->getItems() as $revision) {
      $url = $this->domainPurposes->routeUrl('wiki', 'entity.node.revision', [
        'node' => $revision->id(),
        'node_revision' => $revision->getRevisionId(),
      ]);
      if ($url === NULL) {
        continue;
      }
      $revisionAccess = $this->domainPurposes->runInPurpose('wiki', static fn (): bool => $url->access($account));
      if (!$revisionAccess) {
        continue;
      }
      $rows[] = [
        'entry' => ['data' => ['#type' => 'link', '#title' => $revision->label(), '#url' => $url]],
        'state' => $revision->get('moderation_state')->value,
        'changed' => $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short'),
        'action' => $this->wikiRevisionEditLink($revision, $account),
      ];
      $cacheability->addCacheableDependency($revision);
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-listing-wrap', 'data-aculta-studio' => 'listing-wrap'],
      'table' => [
        '#type' => 'table',
        '#header' => [
          'entry' => $this->t('Verbete'),
          'state' => $this->t('Situação'),
          'changed' => $this->t('Revisada em'),
          'action' => $this->t('Ação'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhum verbete aguarda revisão.'),
        '#attributes' => ['data-portal-ui' => 'studio-listing', 'data-aculta-studio' => 'listing'],
      ],
      'pager' => $page->getNextCursor() !== NULL ? [
        '#type' => 'link',
        '#title' => $this->t('Carregar mais verbetes'),
        '#url' => Url::fromRoute('aculta_studio.tab', [
          'aba' => 'wiki',
          'secao' => 'moderacao',
          'sub' => 'revisoes',
        ], ['query' => ['wiki_revision_cursor' => $page->getNextCursor()]]),
      ] : [],
    ];
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Empty, fail-closed listing used when the Wiki service is unavailable.
   */
  private function emptyWikiQueue(): array {
    return [
      '#type' => 'container',
      '#cache' => ['contexts' => ['user', 'user.permissions', 'url.path']],
      'empty' => ['#plain_text' => $this->t('A fila da Wiki não está disponível.')],
    ];
  }

  /**
   * Links to the owner's native form after checking route access.
   *
   * @param \Drupal\node\NodeInterface $revision
   *   The current pending Node revision.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The editor that will open the native form.
   *
   * @return array
   *   A route link or an empty table cell when access is denied.
   */
  private function wikiRevisionEditLink(NodeInterface $revision, AccountInterface $account): array {
    $url = $this->domainPurposes->routeUrl('wiki', 'entity.node.edit_form', ['node' => $revision->id()]);
    if ($url === NULL || !$this->domainPurposes->runInPurpose('wiki', static fn (): bool => $url->access($account))) {
      return ['data' => ''];
    }
    return [
      'data' => [
        '#type' => 'link',
        '#title' => $this->t('Abrir para revisão'),
        '#url' => $url,
      ],
    ];
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
   *
   * The listing declares the purpose of the content it shows; without one,
   * the main domain is kept for listings that are not tied to a purpose.
   */
  private function editLink(object $node, string $purpose = 'main'): array {
    $url = $this->domainPurposes->routeUrl($purpose, 'entity.node.edit_form', ['node' => $node->id()]);
    if ($url === NULL) {
      return ['data' => ''];
    }
    // The route's own access (admin route, domain and entity checks), not a copy of the rule.
    $account = $this->account;
    $access = $this->domainPurposes->runInPurpose($purpose, static fn (): bool => $url->access($account));
    if (!$access) {
      return ['data' => ''];
    }
    return ['data' => ['#type' => 'link', '#title' => $this->t('Editar'), '#url' => $url]];
  }

}
