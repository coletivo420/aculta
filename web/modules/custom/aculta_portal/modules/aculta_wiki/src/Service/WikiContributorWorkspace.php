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
        'url.query_args:wiki_proposal_cursor',
      ])
      ->addCacheTags(['node_list:wiki_entry', 'aculta_wiki_proposal_list']);
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'we-sativa-workspace'],
      'title' => [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'h1',
        '#value' => $this->t('WE SATIVA'),
      ],
      'intro' => ['#plain_text' => $this->t('Acompanhe seus verbetes e propostas para a Wiki420.')],
    ];

    $create = $this->domainPurposes->runInPurpose('wiki', fn () => $this->entities
      ->getAccessControlHandler('node')->createAccess('wiki_entry', $account, [], TRUE));
    $create ??= AccessResult::forbidden()->addCacheContexts(['user', 'user.permissions', 'domain']);
    $cacheability->addCacheableDependency($create);
    if ($create->isAllowed()) {
      $url = $this->domainPurposes->routeUrl('wiki', 'node.add', ['node_type' => 'wiki_entry']);
      if ($url !== NULL) {
        $build['create'] = Link::fromTextAndUrl($this->t('Criar verbete'), $url)->toRenderable();
      }
    }

    $nodeCursor = $this->requestCursor('wiki_node_cursor');
    $nodePage = $this->domainPurposes->runInPurpose('wiki', function () use ($account, $nodeCursor, $cacheability): array {
      $items = [];
      $page = $this->ownNodes($account, $nodeCursor);
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
      $label = $this->t('@title — @state', ['@title' => $node->label(), '@state' => $this->nodeState($node)]);
      $url = $this->domainPurposes->routeUrl('wiki', 'entity.node.canonical', ['node' => $node->id()]);
      if ($url !== NULL) {
        $item = [
          '#type' => 'container',
          'entry' => Link::fromTextAndUrl($label, $url)->toRenderable(),
        ];
        $update = $this->domainPurposes->runInPurpose('wiki', fn () => $node->access('update', $account, TRUE));
        if ($update instanceof AccessResultInterface) {
          $cacheability->addCacheableDependency($update);
          if ($update->isAllowed()) {
            $editUrl = $this->domainPurposes->routeUrl('wiki', 'entity.node.edit_form', ['node' => $node->id()]);
            if ($editUrl !== NULL) {
              $item['edit'] = Link::fromTextAndUrl($this->t('Editar'), $editUrl)->toRenderable();
            }
          }
        }
        $nodeLinks[] = $item;
      }
    }
    $build['entries'] = $this->listSection($this->t('Meus verbetes'), $nodeLinks, $this->t('Você ainda não criou verbetes.'));
    if (is_string($nodePage['next_cursor'] ?? NULL)) {
      $next = $this->nextPageLink($this->t('Mais verbetes'), 'wiki_node_cursor', $nodePage['next_cursor']);
      if ($next !== NULL) {
        $build['entries']['next_page'] = $next;
      }
    }

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
      $label = $this->t('@title — @state', ['@title' => $proposal->label(), '@state' => $this->proposalState($proposal)]);
      $url = $this->domainPurposes->routeUrl('wiki', 'aculta_wiki.proposal_status', ['proposal' => $proposal->id()]);
      if ($url !== NULL) {
        $proposalLinks[] = Link::fromTextAndUrl($label, $url)->toRenderable();
      }
    }
    $build['proposals'] = $this->listSection($this->t('Minhas propostas'), $proposalLinks, $this->t('Você ainda não enviou propostas.'));
    if (is_string($proposalPage['next_cursor'] ?? NULL)) {
      $next = $this->nextPageLink($this->t('Mais propostas'), 'wiki_proposal_cursor', $proposalPage['next_cursor']);
      if ($next !== NULL) {
        $build['proposals']['next_page'] = $next;
      }
    }
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Finds a bounded set of candidate Nodes authored by the account.
   *
   * @return array{items: array<int, \Drupal\node\NodeInterface>, next_cursor: string|null}
   *   Candidate Nodes and a continuation position; callers still check access.
   */
  private function ownNodes(AccountInterface $account, ?string $cursor): array {
    $storage = $this->entities->getStorage('node');
    $query = $storage->getQuery()
      // Owner-scoped candidates only; each loaded entity must pass view access
      // in build() before its fields are included in the response.
      ->accessCheck(FALSE)
      ->condition('type', 'wiki_entry')
      ->condition('uid', $account->id())
      ->sort('changed', 'DESC')
      ->sort('nid', 'DESC')
      ->range(0, self::PAGE_SIZE + 1);
    $position = $this->parseCursor($cursor);
    if ($position !== NULL) {
      $query->condition($query->orConditionGroup()
        ->condition('changed', $position[0], '<')
        ->condition($query->andConditionGroup()
          ->condition('changed', $position[0])
          ->condition('nid', $position[1], '<')));
    }
    $ids = array_values($query->execute());
    $hasMore = count($ids) > self::PAGE_SIZE;
    $ids = array_slice($ids, 0, self::PAGE_SIZE);
    $items = array_values($storage->loadMultiple($ids));
    $last = $items === [] ? NULL : $items[array_key_last($items)];
    return [
      'items' => $items,
      'next_cursor' => $hasMore && $last instanceof NodeInterface
        ? $this->formatCursor((int) $last->getChangedTime(), (int) $last->id())
        : NULL,
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
    foreach (['wiki_node_cursor', 'wiki_proposal_cursor'] as $key) {
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
   *
   * @return array<string, mixed>
   *   Render array for the section.
   */
  private function listSection(\Stringable|string $title, array $items, \Stringable|string $emptyMessage): array {
    return [
      '#type' => 'container',
      'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h2', '#value' => $title],
      'items' => $items === []
        ? ['#plain_text' => $emptyMessage]
        : ['#theme' => 'item_list', '#items' => $items],
    ];
  }

}
