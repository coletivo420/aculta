<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Controller;

use Drupal\Core\Template\Attribute;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Search\SearchPageController;
use Drupal\domain\DomainInterface;
use Drupal\node\NodeInterface;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public landing page and bounded title/summary/body search for the Wiki.
 */
final class WikiController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
    #[Autowire(service: 'views.executable')]
    private readonly ViewExecutableFactory $viewExecutableFactory,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
    #[Autowire(service: 'database')]
    private readonly Connection $database,
    #[Autowire(service: 'date.formatter')]
    private readonly DateFormatterInterface $dateFormatter,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $account,
    #[Autowire(service: 'renderer')]
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * Builds the Wiki home from the editorial Views.
   */
  public function home(): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'wiki-home'],
      '#cache' => [
        'contexts' => ['domain', 'user.permissions', 'user.node_grants:view'],
        'tags' => ['node_list:wiki_entry', 'taxonomy_term_list'],
      ],
      'context_tabs' => $this->contextNavigation('aculta_portal.wiki_home'),
      'intro' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'wiki-intro'],
        'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h1', '#value' => $this->t('Wiki420')],
        'text' => ['#plain_text' => $this->t('Wiki420 é uma wiki antiproibicionista, participativa e baseada em fontes. Aqui reunimos conhecimento sobre maconha, direitos, cultura, ciência, saúde, história, redução de danos e luta pela legalização.')],
      ],
      'browse' => [
        '#type' => 'link',
        '#title' => $this->t('Ver todos os verbetes'),
        '#url' => Url::fromUserInput('/wiki/verbetes'),
      ],
      'collaboration' => [
        '#type' => 'link',
        '#title' => $this->t('Quer colaborar? Proponha um verbete.'),
        '#url' => Url::fromRoute('node.add', ['node_type' => 'wiki_entry']),
        '#access' => $this->entities
          ->getAccessControlHandler('node')
          ->createAccess('wiki_entry', $this->account, [], TRUE),
      ],
      'categories_title' => [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'h2',
        '#value' => $this->t('Categorias da Wiki420'),
      ],
      'categories' => $this->viewBlock('wiki_categories', 'block_1'),
      'recent_title' => [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'h2',
        '#value' => $this->t('Verbetes recentes'),
      ],
      'recent' => $this->viewBlock('wiki_entries', 'block_recent'),
      'changes_title' => [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'h2',
        '#value' => $this->t('Alterações recentes da Wiki420'),
      ],
      'changes' => $this->recentChanges(),
    ];
    return $build;
  }

  /**
   * Searches only published Wiki entries by title, summary, and body.
   */
  public function search(Request $request): array {
    // InputBag::get() throws on non-scalar values; a malformed `q[]` is treated
    // as an empty term instead of failing the request.
    $raw = $request->query->all()['q'] ?? '';
    $term = trim(is_string($raw) ? $raw : '');
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'wiki-search-results'],
      '#cache' => [
        'contexts' => ['url.query_args:q', 'domain', 'user.permissions', 'user.node_grants:view'],
        'tags' => ['node_list:wiki_entry'],
      ],
      'title' => [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'h1',
        '#value' => $this->t('Buscar na Wiki420'),
      ],
      'context_tabs' => $this->contextNavigation('aculta_portal.wiki_search'),
    ];
    if ($term === '') {
      return $build;
    }
    $term = mb_substr($term, 0, SearchPageController::MAX_CHARS);
    $wikiDomain = $this->domainPurposeManager->getDomain('wiki');
    if (!$wikiDomain) {
      $build['empty'] = [
        '#type' => 'item',
        '#plain_text' => $this->t('A busca da Wiki420 está indisponível no momento.'),
      ];
      return $build;
    }
    $viewBuilder = $this->entities->getViewBuilder('node');
    $results = [];
    foreach ($this->findWikiEntries($term, $wikiDomain, 30) as $node) {
      $results[$node->id()] = $viewBuilder->view($node, 'teaser');
    }
    // pt-BR plural rules use the singular form for 0, so the count is only
    // shown when there is at least one visible result.
    if ($results) {
      $build['count'] = [
        '#type' => 'item',
        '#plain_text' => $this->formatPlural(
          count($results),
          '1 verbete encontrado.',
          '@count verbetes encontrados.',
        ),
      ];
      $build['results'] = $results;
    }
    else {
      $build['empty'] = [
        '#type' => 'component',
        '#component' => 'aculta420:empty-state',
        '#slots' => [
          'message' => [
            '#plain_text' => $this->t('Nenhum verbete publicado corresponde a essa busca.'),
          ],
        ],
      ];
    }
    return $build;
  }

  /**
   * Shared accessible route navigation for the two Wiki landing screens.
   *
   * Actual URLs and active state are owned by the Wiki provider, not the SDC.
   */
  private function contextNavigation(string $activeRoute): array {
    $links = [];
    foreach ([
      'aculta_portal.wiki_home' => $this->t('Início'),
      'aculta_portal.wiki_search' => $this->t('Busca'),
    ] as $route => $label) {
      $attributes = $route === $activeRoute ? ['aria-current' => 'page'] : [];
      $links[] = Link::fromTextAndUrl($label, Url::fromRoute($route, [], ['attributes' => $attributes]))->toRenderable();
    }
    return [
      '#type' => 'component',
      '#component' => 'aculta420:context-tabs',
      '#props' => ['label' => (string) $this->t('Navegação da Wiki420')],
      '#slots' => ['items' => ['#theme' => 'item_list', '#items' => $links]],
    ];
  }

  /**
   * Returns a no-store HTML preview using the full Wiki search query.
   */
  public function preview(Request $request): Response {
    // InputBag::get() throws on non-scalar values; a malformed `q[]` is treated
    // as an empty term instead of failing the request.
    $raw = $request->query->all()['q'] ?? '';
    $term = trim(is_string($raw) ? $raw : '');
    $term = mb_substr($term, 0, SearchPageController::MAX_CHARS);
    $build = ['#cache' => ['max-age' => 0]];
    if (mb_strlen($term) < SearchPageController::MIN_CHARS) {
      $build['hint'] = [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'p',
        '#element_attributes' => new Attribute(['data-portal-ui' => 'hint']),
        '#value' => $this->t(
          'Digite pelo menos @n caracteres para ver resultados.',
          ['@n' => SearchPageController::MIN_CHARS],
        ),
      ];
      return $this->previewResponse($build);
    }

    $wikiDomain = $this->domainPurposeManager->getDomain('wiki');
    if ($wikiDomain === NULL) {
      $build['empty'] = ['#plain_text' => $this->t('A busca da Wiki420 está indisponível no momento.')];
      return $this->previewResponse($build);
    }

    $nodes = $this->findWikiEntries($term, $wikiDomain, 8);
    if ($nodes === []) {
      $build['empty'] = ['#plain_text' => $this->t('Nenhum verbete publicado corresponde a essa busca.')];
      return $this->previewResponse($build);
    }

    $items = [];
    foreach ($nodes as $node) {
      $items[] = [
        '#type' => 'link',
        '#title' => $node->label(),
        '#url' => $node->toUrl('canonical'),
      ];
    }
    $fullSearch = Url::fromRoute('aculta_portal.wiki_search', [], ['query' => ['q' => $term]]);
    $build['list'] = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['data-portal-ui' => 'search-preview-list'],
    ];
    $build['full'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'search-preview-footer'],
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Ver todos os resultados para «@term»', ['@term' => $term]),
        '#url' => $fullSearch,
        '#attributes' => ['data-portal-ui' => 'search-preview-full'],
      ],
    ];
    return $this->previewResponse($build);
  }

  /**
   * Finds visible Wiki420 entries for both complete and interactive searches.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Published entries in the Wiki domain visible to the current account.
   */
  private function findWikiEntries(string $term, DomainInterface $wikiDomain, int $limit): array {
    $pattern = '%' . $this->database->escapeLike($term) . '%';
    $nodeStorage = $this->entities->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->accessCheck(TRUE)
      ->addMetaData('account', $this->account)
      ->condition('type', 'wiki_entry')
      ->condition('field_domain_source.target_id', $wikiDomain->id())
      ->condition('status', 1)
      ->range(0, $limit)
      ->sort('title');
    $matches = $query->orConditionGroup()
      ->condition('title', $pattern, 'LIKE')
      ->condition('field_wiki_summary.value', $pattern, 'LIKE')
      ->condition('body.value', $pattern, 'LIKE');
    $ids = $query->condition($matches)->execute();
    $nodes = [];
    foreach ($nodeStorage->loadMultiple($ids) as $node) {
      if ($node instanceof NodeInterface && $node->access('view')) {
        $nodes[] = $node;
      }
    }
    return $nodes;
  }

  /**
   * Renders a non-cacheable preview response for the current request.
   */
  private function previewResponse(array $build): Response {
    $response = new Response((string) $this->renderer->renderInIsolation($build));
    $response->headers->set('Cache-Control', 'no-store, private');
    return $response;
  }

  /**
   * Lists recent published Wiki420 entries without public authorship.
   */
  private function recentChanges(): array {
    $build = [
      '#theme' => 'item_list',
      '#items' => [],
      '#cache' => [
        'contexts' => ['domain', 'user.permissions', 'user.node_grants:view'],
        'tags' => ['node_list:wiki_entry'],
      ],
    ];
    $wikiDomain = $this->domainPurposeManager->getDomain('wiki');
    if (!$wikiDomain) {
      return $build;
    }
    // Public listing: node access applies; each row is re-checked below.
    $nodeStorage = $this->entities->getStorage('node');
    $ids = $nodeStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'wiki_entry')
      ->condition('field_domain_source.target_id', $wikiDomain->id())
      ->condition('status', 1)
      ->sort('changed', 'DESC')
      ->range(0, 5)
      ->execute();
    $nodes = $nodeStorage->loadMultiple($ids);
    $cacheability = CacheableMetadata::createFromRenderArray($build);
    foreach ($nodes as $node) {
      if (!$node->access('view')) {
        continue;
      }
      $changed = $node->getChangedTime();
      $row = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'wiki-recent-change'],
        'title' => [
          '#type' => 'link',
          '#title' => $node->label(),
          '#url' => $node->toUrl('canonical'),
        ],
        'updated' => [
          '#type' => 'html_tag',
          '#tag' => 'time',
          '#value' => $this->dateFormatter->format($changed, 'medium'),
          '#attributes' => ['datetime' => gmdate(DATE_ATOM, $changed)],
        ],
      ];
      $build['#items'][] = $row;
      $cacheability->addCacheableDependency($node);
    }
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Embeds one Wiki View display; the Views element enforces display access.
   *
   * Uses the injected executable factory instead of the static Views locator
   * and keeps buildRenderable() so '#embed', cache keys and properties match.
   */
  private function viewBlock(string $viewId, string $displayId): array {
    $config = $this->entities->getStorage('view')->load($viewId);
    $view = $config ? $this->viewExecutableFactory->get($config) : NULL;
    return $view?->buildRenderable($displayId) ?? ['#markup' => ''];
  }

}
