<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Service;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\aculta_wiki\Entity\WikiProposal;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides the contributor's own Wiki work to WE SATIVA.
 *
 * The service is owned by Wiki. Studio receives only authorized entities and
 * render links; it does not define contributor access or store editorial data.
 */
final class WikiContributorWorkspace {

  use StringTranslationTrait;

  private const PAGE_SIZE = 20;

  /**
   * Number of own Node candidates inspected to fill a filtered page.
   */
  private const NODE_CANDIDATE_BATCH_SIZE = 100;

  /**
   * Bounded scan cap so stale or rare states cannot create unbounded queries.
   */
  private const MAX_NODE_CANDIDATES_PER_PAGE = 500;

  /**
   * Contributor-visible workflow filters for their own entries.
   *
   * @var array<string, array{state: string|null}>
   */
  private const ENTRY_FILTERS = [
    'in_progress' => ['state' => 'draft'],
    'review' => ['state' => 'review'],
    'published' => ['state' => 'published'],
    'all' => ['state' => NULL],
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly DomainPurposeManager $domainPurposes,
    TranslationInterface $stringTranslation,
    private readonly RequestStack $requestStack,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Cacheable access to the contributor experience.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    if (!$account->isAuthenticated()) {
      return AccessResult::forbidden()->addCacheContexts(['user', 'user.permissions']);
    }

    $result = $this->domainPurposes->runInPurpose('wiki', function () use ($account): AccessResultInterface {
      $create = $this->entities->getAccessControlHandler('node')
        ->createAccess('wiki_entry', $account, [], TRUE);
      $node = $this->entities->getStorage('node')->getQuery()
        // This is a candidate-only existence check restricted to the exact
        // owner UID. Every loaded Node is revalidated with view Entity Access
        // before any title or link is rendered below.
        ->accessCheck(FALSE)
        ->condition('type', 'wiki_entry')
        ->condition('uid', $account->id())
        ->range(0, 1)
        ->execute();
      $proposal = $this->entities->getStorage('aculta_wiki_proposal')->getQuery()
        // Like Node discovery, this only checks whether the exact author UID
        // has a candidate. Proposal access is revalidated before rendering.
        ->accessCheck(FALSE)
        ->condition('uid', $account->id())
        ->range(0, 1)
        ->execute();
      return $create->orIf(AccessResult::allowedIf($node !== [] || $proposal !== []))
        ->addCacheContexts(['user', 'user.permissions', 'domain'])
        ->addCacheTags(['node_list:wiki_entry', 'aculta_wiki_proposal_list']);
    });
    if (!$result instanceof AccessResultInterface) {
      return AccessResult::forbidden()->addCacheContexts(['user', 'user.permissions', 'domain']);
    }
    return $result->addCacheContexts(['user', 'user.permissions']);
  }

  /**
   * Builds the private, bounded "Meu trabalho" page.
   */
  public function build(AccountInterface $account): array {
    $access = $this->access($account);
    if (!$access->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $cacheability = CacheableMetadata::createFromObject($access)
      ->addCacheContexts([
        'user',
        'user.permissions',
        'domain',
        'url.query_args:wiki_node_cursor',
        'url.query_args:wiki_node_state',
        'url.query_args:wiki_proposal_cursor',
      ])
      ->addCacheTags(['node_list:wiki_entry', 'aculta_wiki_proposal_list']);
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'we-sativa-workspace'],
      'header' => [
        '#type' => 'component',
        '#component' => 'aculta420:context-header',
        '#props' => [
          'eyebrow' => (string) $this->t('Wiki420'),
          'title' => (string) $this->t('WE SATIVA'),
          'subtitle' => (string) $this->t('Acompanhe seus verbetes e propostas para a Wiki420.'),
        ],
      ],
    ];

    $startUrl = $this->domainPurposes->routeUrl('account', 'aculta_studio.we_sativa_start');
    if ($startUrl !== NULL) {
      $startAccess = $this->domainPurposes->runInPurpose('account', fn () => $startUrl->access($account, TRUE));
      if ($startAccess instanceof AccessResultInterface) {
        $cacheability->addCacheableDependency($startAccess);
        if ($startAccess->isAllowed()) {
          $build['header']['#slots']['back_link'] = Link::fromTextAndUrl($this->t('Início WE SATIVA'), $startUrl)->toRenderable();
        }
      }
    }

    $create = $this->domainPurposes->runInPurpose('wiki', fn () => $this->entities
      ->getAccessControlHandler('node')->createAccess('wiki_entry', $account, [], TRUE));
    $create ??= AccessResult::forbidden()->addCacheContexts(['user', 'user.permissions', 'domain']);
    $cacheability->addCacheableDependency($create);
    if ($create->isAllowed()) {
      $url = $this->domainPurposes->routeUrl('wiki', 'node.add', ['node_type' => 'wiki_entry']);
      if ($url !== NULL) {
        $createLink = Link::fromTextAndUrl($this->t('Criar verbete'), $url)->toRenderable();
        $createLink['#attributes']['data-portal-ui'] = 'we-sativa-create';
        $build['header']['#slots']['actions'] = $createLink;
      }
    }

    $nodeCursor = $this->requestCursor('wiki_node_cursor');
    $nodeState = $this->requestEntryState();
    $nodePage = $this->domainPurposes->runInPurpose('wiki', function () use ($account, $nodeCursor, $nodeState, $cacheability): array {
      $items = [];
      $page = $this->ownNodes($account, $nodeCursor, $nodeState);
      foreach ($page['items'] as $node) {
        if (!$node instanceof NodeInterface || $node->bundle() !== 'wiki_entry') {
          continue;
        }
        $view = $node->access('view', $account, TRUE);
        $cacheability->addCacheableDependency($node)->addCacheableDependency($view);
        $items[] = [$node, $view];
      }
      return ['items' => $items, 'next_cursor' => $page['next_cursor']];
    });
    $nodeLinks = [];
    foreach ($nodePage['items'] ?? [] as [$node, $view]) {
      if (!$view->isAllowed()) {
        continue;
      }
      $url = $this->domainPurposes->routeUrl('wiki', 'entity.node.canonical', ['node' => $node->id()]);
      if ($url !== NULL) {
        $item = [
          '#type' => 'component',
          '#component' => 'aculta420:card',
          '#slots' => [
            'header' => Link::fromTextAndUrl((string) $node->label(), $url)->toRenderable(),
            'content' => ['#plain_text' => $this->nodeState($node)],
          ],
        ];
        $update = $this->domainPurposes->runInPurpose('wiki', fn () => $node->access('update', $account, TRUE));
        if ($update instanceof AccessResultInterface) {
          $cacheability->addCacheableDependency($update);
          if ($update->isAllowed()) {
            $editUrl = $this->domainPurposes->routeUrl('wiki', 'entity.node.edit_form', ['node' => $node->id()]);
            if ($editUrl !== NULL) {
              $item['#slots']['footer'] = Link::fromTextAndUrl($this->t('Editar'), $editUrl)->toRenderable();
            }
          }
        }
        $nodeLinks[] = $item;
      }
    }
    $nodeNextPage = NULL;
    if (is_string($nodePage['next_cursor'] ?? NULL)) {
      $nodeNextPage = $this->nextPageLink($this->t('Mais verbetes'), 'wiki_node_cursor', $nodePage['next_cursor']);
    }
    $emptyEntriesMessage = match ($nodeState) {
      'in_progress' => $this->t('Você não tem rascunhos em andamento.'),
      'review' => $this->t('Você não tem verbetes em revisão.'),
      'published' => $this->t('Você ainda não tem verbetes publicados.'),
      default => $this->t('Você ainda não criou verbetes.'),
    };
    $build['entries'] = $this->listSection($this->t('Meus verbetes'), $nodeLinks, $emptyEntriesMessage, $nodeNextPage);
    $entryBody = $build['entries']['#slots']['body'];
    $build['entries']['#slots']['body'] = [
      '#type' => 'container',
      'filters' => $this->entryFilters($nodeState),
      ...$entryBody,
    ];

    $proposalCursor = $this->requestCursor('wiki_proposal_cursor');
    $proposalPage = $this->domainPurposes->runInPurpose('wiki', function () use ($account, $proposalCursor, $cacheability): array {
      $items = [];
      $page = $this->ownProposals($account, $proposalCursor);
      foreach ($page['items'] as $proposal) {
        if (!$proposal instanceof WikiProposal) {
          continue;
        }
        $view = $proposal->access('view', $account, TRUE);
        $cacheability->addCacheableDependency($proposal)->addCacheableDependency($view);
        $items[] = [$proposal, $view];
      }
      return ['items' => $items, 'next_cursor' => $page['next_cursor']];
    });
    $proposalLinks = [];
    foreach ($proposalPage['items'] ?? [] as [$proposal, $view]) {
      if (!$view->isAllowed()) {
        continue;
      }
      $url = $this->domainPurposes->routeUrl('wiki', 'aculta_wiki.proposal_status', ['proposal' => $proposal->id()]);
      if ($url !== NULL) {
        $proposalLinks[] = [
          '#type' => 'component',
          '#component' => 'aculta420:card',
          '#slots' => [
            'header' => Link::fromTextAndUrl((string) $proposal->label(), $url)->toRenderable(),
            'content' => ['#plain_text' => $this->proposalState($proposal)],
          ],
        ];
      }
    }
    $proposalNextPage = NULL;
    if (is_string($proposalPage['next_cursor'] ?? NULL)) {
      $proposalNextPage = $this->nextPageLink($this->t('Mais propostas'), 'wiki_proposal_cursor', $proposalPage['next_cursor']);
    }
    $build['proposals'] = $this->listSection($this->t('Minhas propostas'), $proposalLinks, $this->t('Você ainda não enviou propostas.'), $proposalNextPage);
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Builds the WE SATIVA start page with actions authorized by their owners.
   *
   * @return array<string, mixed>
   *   Start page render array.
   */
  public function buildStart(AccountInterface $account): array {
    $access = $this->access($account);
    if (!$access->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $cacheability = CacheableMetadata::createFromObject($access)
      ->addCacheContexts(['user', 'user.permissions', 'domain'])
      ->addCacheTags(['node_list:wiki_entry', 'aculta_wiki_proposal_list']);
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'we-sativa-start'],
      'header' => [
        '#type' => 'component',
        '#component' => 'aculta420:context-header',
        '#props' => [
          'eyebrow' => (string) $this->t('Wiki420'),
          'title' => (string) $this->t('Seu espaço de contribuição'),
          'subtitle' => (string) $this->t('Crie um verbete ou continue um trabalho que já começou.'),
        ],
      ],
    ];

    $create = $this->domainPurposes->runInPurpose('wiki', fn () => $this->entities
      ->getAccessControlHandler('node')->createAccess('wiki_entry', $account, [], TRUE));
    if ($create instanceof AccessResultInterface) {
      $cacheability->addCacheableDependency($create);
      if ($create->isAllowed()) {
        $createUrl = $this->domainPurposes->routeUrl('wiki', 'node.add', ['node_type' => 'wiki_entry']);
        if ($createUrl !== NULL) {
          $routeAccess = $this->domainPurposes->runInPurpose('wiki', fn () => $createUrl->access($account, TRUE));
          if ($routeAccess instanceof AccessResultInterface) {
            $cacheability->addCacheableDependency($routeAccess);
            if ($routeAccess->isAllowed()) {
              $build['header']['#slots']['actions'] = Link::fromTextAndUrl($this->t('Criar verbete'), $createUrl)->toRenderable();
            }
          }
        }
      }
    }

    $drafts = $this->domainPurposes->runInPurpose('wiki', fn () => $this->ownNodes($account, NULL, 'in_progress'));
    foreach ($drafts['items'] as $draft) {
      if (!$draft instanceof NodeInterface) {
        continue;
      }
      $update = $this->domainPurposes->runInPurpose('wiki', fn () => $draft->access('update', $account, TRUE));
      $cacheability->addCacheableDependency($draft);
      if (!$update instanceof AccessResultInterface) {
        continue;
      }
      $cacheability->addCacheableDependency($update);
      if (!$update->isAllowed()) {
        continue;
      }
      $editUrl = $this->domainPurposes->routeUrl('wiki', 'entity.node.edit_form', ['node' => $draft->id()]);
      if ($editUrl === NULL) {
        continue;
      }
      $routeAccess = $this->domainPurposes->runInPurpose('wiki', fn () => $editUrl->access($account, TRUE));
      if (!$routeAccess instanceof AccessResultInterface) {
        continue;
      }
      $cacheability->addCacheableDependency($routeAccess);
      if (!$routeAccess->isAllowed()) {
        continue;
      }
      $build['continue'] = [
        '#type' => 'component',
        '#component' => 'aculta420:content-section',
        '#props' => ['title' => (string) $this->t('Continuar de onde parou')],
        '#slots' => [
          'body' => [
            '#type' => 'component',
            '#component' => 'aculta420:card',
            '#slots' => [
              'header' => Link::fromTextAndUrl($this->t('Continuar: @title', ['@title' => $draft->label()]), $editUrl)->toRenderable(),
              'content' => ['#plain_text' => $this->t('Rascunho salvo. Você pode continuar editando antes de enviar para revisão.')],
            ],
          ],
        ],
      ];
      break;
    }

    $workUrl = $this->domainPurposes->routeUrl('account', 'aculta_studio.we_sativa');
    if ($workUrl !== NULL) {
      $workAccess = $this->domainPurposes->runInPurpose('account', fn () => $workUrl->access($account, TRUE));
      if ($workAccess instanceof AccessResultInterface) {
        $cacheability->addCacheableDependency($workAccess);
        if ($workAccess->isAllowed()) {
          $build['work'] = Link::fromTextAndUrl($this->t('Ver meu trabalho'), $workUrl)->toRenderable();
        }
      }
    }

    $build['guide'] = [
      '#type' => 'component',
      '#component' => 'aculta420:content-section',
      '#props' => ['title' => (string) $this->t('Contribua com cuidado')],
      '#slots' => [
        'body' => [
          '#type' => 'component',
          '#component' => 'aculta420:card',
          '#slots' => [
            'header' => ['#plain_text' => $this->t('Use fontes que outras pessoas possam conferir')],
            'content' => ['#plain_text' => $this->t('Sugestões para verbetes publicados passam por revisão editorial. Indique de onde vem a informação e explique a mudança proposta.')],
          ],
        ],
      ],
    ];
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Finds a bounded set of candidate Nodes authored by the account.
   *
   * @return array{items: array<int, \Drupal\node\NodeInterface>, next_cursor: string|null}
   *   Candidate Nodes and a continuation position; callers still check access.
   */
  private function ownNodes(AccountInterface $account, ?string $cursor, string $state): array {
    $storage = $this->entities->getStorage('node');
    $workflowState = self::ENTRY_FILTERS[$state]['state'];
    $position = $this->parseCursor($cursor);
    $items = [];
    $scanCursor = $position;
    $candidateCount = 0;
    $hasMoreCandidates = FALSE;
    while ($candidateCount < self::MAX_NODE_CANDIDATES_PER_PAGE) {
      $batchSize = min(self::NODE_CANDIDATE_BATCH_SIZE, self::MAX_NODE_CANDIDATES_PER_PAGE - $candidateCount);
      $query = $storage->getQuery()
        // Owner-scoped candidates only; loaded Nodes still pass view access
        // in build() before any title or workflow state is rendered.
        ->accessCheck(FALSE)
        ->condition('type', 'wiki_entry')
        ->condition('uid', $account->id())
        ->sort('changed', 'DESC')
        ->sort('nid', 'DESC')
        ->range(0, $batchSize);
      if ($workflowState === 'published') {
        $query->condition('status', 1);
      }
      elseif ($workflowState !== NULL) {
        // Moderation state is provided by Content Moderation and is not an
        // Entity Query property on Node. Publication status bounds candidates;
        // the actual state is checked after loading each entity below.
        $query->condition('status', 0);
      }
      if ($scanCursor !== NULL) {
        $query->condition($query->orConditionGroup()
          ->condition('changed', $scanCursor[0], '<')
          ->condition($query->andConditionGroup()
            ->condition('changed', $scanCursor[0])
            ->condition('nid', $scanCursor[1], '<')));
      }
      $ids = array_map('intval', array_values($query->execute()));
      if ($ids === []) {
        $hasMoreCandidates = FALSE;
        break;
      }
      $hasMoreCandidates = count($ids) === $batchSize;
      foreach ($storage->loadMultiple($ids) as $node) {
        if (!$node instanceof NodeInterface) {
          continue;
        }
        $scanCursor = [(int) $node->getChangedTime(), (int) $node->id()];
        $candidateCount++;
        $nodeState = $node->hasField('moderation_state') ? (string) $node->get('moderation_state')->value : '';
        if ($workflowState === NULL || $nodeState === $workflowState) {
          $items[] = $node;
          if (count($items) > self::PAGE_SIZE) {
            break 2;
          }
        }
      }
      if (!$hasMoreCandidates) {
        break;
      }
    }

    $hasExtra = count($items) > self::PAGE_SIZE;
    if ($hasExtra) {
      $items = array_slice($items, 0, self::PAGE_SIZE);
      $last = $items[array_key_last($items)];
      $nextCursor = $this->formatCursor((int) $last->getChangedTime(), (int) $last->id());
    }
    elseif ($hasMoreCandidates && $scanCursor !== NULL) {
      // A bounded scan may fill less than a page after state filtering. Advance
      // past the final inspected candidate so the next request can continue.
      $nextCursor = $this->formatCursor($scanCursor[0], $scanCursor[1]);
    }
    else {
      $nextCursor = NULL;
    }
    return [
      'items' => $items,
      'next_cursor' => $nextCursor,
    ];
  }

  /**
   * Finds a bounded set of candidate proposals authored by the account.
   *
   * @return array{items: array<int, \Drupal\aculta_wiki\Entity\WikiProposal>, next_cursor: string|null}
   *   Candidate proposals and a continuation position; access is rechecked.
   */
  private function ownProposals(AccountInterface $account, ?string $cursor): array {
    $storage = $this->entities->getStorage('aculta_wiki_proposal');
    $query = $storage->getQuery()
      // Owner-scoped candidates only; every proposal is revalidated below.
      ->accessCheck(FALSE)
      ->condition('uid', $account->id())
      ->sort('changed', 'DESC')
      ->sort('id', 'DESC')
      ->range(0, self::PAGE_SIZE + 1);
    $position = $this->parseCursor($cursor);
    if ($position !== NULL) {
      $query->condition($query->orConditionGroup()
        ->condition('changed', $position[0], '<')
        ->condition($query->andConditionGroup()
          ->condition('changed', $position[0])
          ->condition('id', $position[1], '<')));
    }
    $ids = array_values($query->execute());
    $hasMore = count($ids) > self::PAGE_SIZE;
    $ids = array_slice($ids, 0, self::PAGE_SIZE);
    $items = array_values($storage->loadMultiple($ids));
    $last = $items === [] ? NULL : $items[array_key_last($items)];
    return [
      'items' => $items,
      'next_cursor' => $hasMore && $last instanceof WikiProposal
        ? $this->formatCursor((int) $last->get('changed')->value, (int) $last->id())
        : NULL,
    ];
  }

  /**
   * Reads a scalar pagination cursor from the active request.
   */
  private function requestCursor(string $key): ?string {
    $value = $this->requestStack->getCurrentRequest()?->query->all()[$key] ?? NULL;
    return is_string($value) ? $value : NULL;
  }

  /**
   * Returns the allowlisted own-entry filter from the current GET request.
   */
  private function requestEntryState(): string {
    $state = $this->requestStack->getCurrentRequest()?->query->all()['wiki_node_state'] ?? NULL;
    return is_string($state) && isset(self::ENTRY_FILTERS[$state]) ? $state : 'all';
  }

  /**
   * Builds account-domain filter links and marks the active filter accessibly.
   */
  private function entryFilters(string $active): array {
    $links = [];
    foreach (self::ENTRY_FILTERS as $state => $filter) {
      $url = $this->domainPurposes->routeUrl('account', 'aculta_studio.we_sativa');
      if ($url === NULL) {
        continue;
      }
      $query = [];
      if ($state !== 'all') {
        $query['wiki_node_state'] = $state;
      }
      $requestQuery = $this->requestStack->getCurrentRequest()?->query->all() ?? [];
      if (isset($requestQuery['wiki_proposal_cursor']) && is_string($requestQuery['wiki_proposal_cursor'])) {
        $query['wiki_proposal_cursor'] = $requestQuery['wiki_proposal_cursor'];
      }
      $url->setOption('query', $query);
      $attributes = $state === $active ? ['aria-current' => 'page'] : [];
      $url->setOption('attributes', $attributes);
      $label = match ($state) {
        'in_progress' => $this->t('Em andamento'),
        'review' => $this->t('Em revisão'),
        'published' => $this->t('Publicados'),
        default => $this->t('Todos'),
      };
      $links[] = Link::fromTextAndUrl($label, $url)->toRenderable();
    }
    return [
      '#type' => 'component',
      '#component' => 'aculta420:context-tabs',
      '#props' => ['label' => (string) $this->t('Filtrar meus verbetes')],
      '#slots' => ['items' => ['#theme' => 'item_list', '#items' => $links]],
    ];
  }

  /**
   * Parses a timestamp-ID keyset position; malformed cursors restart at page 1.
   *
   * @return array{0: int, 1: int}|null
   *   Position or NULL when absent/invalid.
   */
  private function parseCursor(?string $cursor): ?array {
    if (!is_string($cursor) || preg_match('/^([0-9]{1,12})-([1-9][0-9]{0,12})$/D', $cursor, $parts) !== 1) {
      return NULL;
    }
    return [(int) $parts[1], (int) $parts[2]];
  }

  /**
   * Formats the keyset position for either owner-scoped listing.
   */
  private function formatCursor(int $changed, int $id): string {
    return $changed . '-' . $id;
  }

  /**
   * Creates a normal GET continuation link in the account-purpose Domain.
   *
   * Other list cursors are preserved so each section can advance independently.
   *
   * @param string $label
   *   Link label.
   * @param string $cursorKey
   *   Query argument associated with the list.
   * @param string $cursor
   *   New position.
   *
   * @return array<string, mixed>|null
   *   Renderable link or NULL when the account Domain is unavailable.
   */
  private function nextPageLink(\Stringable|string $label, string $cursorKey, string $cursor): ?array {
    $url = $this->domainPurposes->routeUrl('account', 'aculta_studio.we_sativa');
    if ($url === NULL) {
      return NULL;
    }
    $requestQuery = $this->requestStack->getCurrentRequest()?->query->all() ?? [];
    $query = [];
    foreach (['wiki_node_cursor', 'wiki_node_state', 'wiki_proposal_cursor'] as $key) {
      if (isset($requestQuery[$key]) && is_string($requestQuery[$key])) {
        $query[$key] = $requestQuery[$key];
      }
    }
    $query[$cursorKey] = $cursor;
    $url->setOption('query', $query);
    return Link::fromTextAndUrl($label, $url)->toRenderable();
  }

  /**
   * Returns the user-facing state of a Wiki entry.
   */
  private function nodeState(NodeInterface $node): string {
    $state = $node->hasField('moderation_state') ? (string) $node->get('moderation_state')->value : '';
    return match ($state) {
      'draft' => (string) $this->t('Rascunho'),
      'review' => (string) $this->t('Em revisão'),
      'published' => (string) $this->t('Publicado'),
      default => (string) $this->t('Em edição'),
    };
  }

  /**
   * Returns the user-facing state of a proposal.
   */
  private function proposalState(WikiProposal $proposal): string {
    return match ((string) $proposal->get('state')->value) {
      'submitted' => (string) $this->t('Enviada'),
      'needs_changes' => (string) $this->t('Precisa de ajustes'),
      'accepted' => (string) $this->t('Aceita'),
      'declined' => (string) $this->t('Recusada'),
      'withdrawn' => (string) $this->t('Retirada'),
      default => (string) $this->t('Estado desconhecido'),
    };
  }

  /**
   * Builds a titled list with an explicit empty state.
   *
   * @param string|\Stringable $title
   *   Section heading.
   * @param array<int, array<string, mixed>> $items
   *   Renderable links authorized by the owner module.
   * @param string|\Stringable $emptyMessage
   *   Text shown when no authorized item is available.
   * @param array<string, mixed>|null $nextPage
   *   Optional authorized continuation link.
   *
   * @return array<string, mixed>
   *   Render array for the section.
   */
  private function listSection(\Stringable|string $title, array $items, \Stringable|string $emptyMessage, ?array $nextPage = NULL): array {
    $bodyItems = $items === []
      ? [
        '#type' => 'component',
        '#component' => 'aculta420:empty-state',
        '#slots' => ['message' => ['#plain_text' => $emptyMessage]],
      ]
      : [
        '#type' => 'component',
        '#component' => 'aculta420:content-grid',
        '#props' => ['columns' => 2],
        '#slots' => ['items' => $items],
      ];
    $body = [
      '#type' => 'container',
      'items' => $bodyItems,
    ];
    if ($nextPage !== NULL) {
      $body['next_page'] = $nextPage;
    }
    return [
      '#type' => 'component',
      '#component' => 'aculta420:content-section',
      '#props' => ['title' => (string) $title],
      '#slots' => ['body' => $body],
    ];
  }

}
