<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\aculta_portal\Saved\SavedContentFilter;
use Drupal\aculta_portal\Saved\SavedContentQueryInterface;
use Drupal\aculta_portal\Saved\SavedContentQueryResult;
use Drupal\aculta_studio\Recommendation\RecommendationService;
use Drupal\aculta_studio\Saved\SavedContentService;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for personal saved items, followed content, and recommendations.
 */
class SavedContentController {

  use StringTranslationTrait;

  /**
   * Constructs a SavedContentController instance.
   */
  public function __construct(
    protected readonly SavedContentService $savedContent,
    protected readonly RecommendationService $recommendations,
    protected readonly AccountInterface $account,
    protected readonly RequestStack $requestStack,
    protected readonly DateFormatterInterface $dateFormatter,
    TranslationInterface $stringTranslation,
    protected readonly ?SavedContentQueryInterface $savedContentQuery = NULL,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Renders the personal saved and recommended content page.
   *
   * @return array<string, mixed>
   *   Render array with strict user cacheability.
   */
  public function page(): array {
    if (!$this->account->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }

    $request = $this->requestStack->getCurrentRequest();
    $activeTab = $request?->query->get('tab', 'salvos') ?? 'salvos';
    if (!in_array($activeTab, ['salvos', 'seguindo', 'recomendacoes'], TRUE)) {
      $activeTab = 'salvos';
    }

    $page = max(0, (int) ($request?->query->get('page', 0) ?? 0));
    $bundle = $request?->query->get('bundle');
    $purpose = $request?->query->get('purpose');
    $limit = 15;
    $offset = $page * $limit;

    $counts = $this->savedContent->getSavedCounts($this->account);

    // Build navigation tabs.
    $tabs = [
      'salvos' => [
        'title' => $this->t('Salvos (@count)', ['@count' => $counts['saved']]),
        'url' => Url::fromRoute('aculta_studio.salvos', [], ['query' => ['tab' => 'salvos']]),
        'active' => $activeTab === 'salvos',
      ],
      'seguindo' => [
        'title' => $this->t('Seguindo (@count)', ['@count' => $counts['following']]),
        'url' => Url::fromRoute('aculta_studio.salvos', [], ['query' => ['tab' => 'seguindo']]),
        'active' => $activeTab === 'seguindo',
      ],
      'recomendacoes' => [
        'title' => $this->t('Recomendações'),
        'url' => Url::fromRoute('aculta_studio.salvos', [], ['query' => ['tab' => 'recomendacoes']]),
        'active' => $activeTab === 'recomendacoes',
      ],
    ];

    $itemsList = [];
    $pagination = [];
    $cacheContexts = [
      'user',
      'url.query_args:tab',
      'url.query_args:page',
      'url.query_args:bundle',
      'url.query_args:purpose',
    ];
    $cacheTags = $this->savedContent->getCacheTags($this->account);
    $cacheMaxAge = Cache::PERMANENT;

    if ($activeTab === 'recomendacoes') {
      $recs = $this->recommendations->getRecommendations($this->account, 15);
      $itemsList = $this->buildRecommendationCards($recs);
      $cacheTags = array_merge($cacheTags, ['node_list']);
    }
    else {
      $category = $activeTab === 'salvos'
        ? SavedContentQueryInterface::CATEGORY_SAVED
        : SavedContentQueryInterface::CATEGORY_FOLLOWING;

      $filter = new SavedContentFilter(
        account: $this->account,
        category: $category,
        bundle: $bundle ? (string) $bundle : NULL,
        purpose: $purpose ? (string) $purpose : NULL,
        limit: $limit,
        offset: $offset,
      );

      $queryService = $this->savedContentQuery ?? $this->savedContent->getQueryService();
      $queryResult = $queryService->query($filter);

      $itemsList = $this->buildCardsFromQueryResult($queryResult);
      $pagination = $this->buildPagination($queryResult, $page, $limit, $activeTab, $bundle, $purpose);

      $cacheTags = array_unique(array_merge($cacheTags, $queryResult->getCacheTags()));
      $cacheContexts = array_unique(array_merge($cacheContexts, $queryResult->getCacheContexts()));
      $cacheMaxAge = $queryResult->getCacheMaxAge();
    }

    $tabLinks = [];
    foreach ($tabs as $tabData) {
      $tabLinks[] = [
        '#type' => 'link',
        '#title' => $tabData['title'],
        '#url' => $tabData['url'],
        '#attributes' => [
          'class' => $tabData['active'] ? ['is-active', 'active-tab'] : [],
          'aria-current' => $tabData['active'] ? 'page' : 'false',
        ],
      ];
    }

    $emptyMessage = match ($activeTab) {
      'recomendacoes' => $this->t('Nenhuma recomendação disponível no momento. Explore conteúdos na comunidade para receber sugestões.'),
      'seguindo' => $this->t('Você ainda não está seguindo nenhum tópico ou publicação.'),
      default => $this->t('Você ainda não possui itens nesta lista. Marque tópicos ou conteúdos para encontrá-los aqui.'),
    };

    return [
      '#type' => 'container',
      '#attributes' => [
        'data-portal-ui' => 'studio-saved-content',
      ],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('Meu espaço: salvos e recomendações'),
        '#attributes' => ['data-portal-ui' => 'studio-page-title'],
      ],
      'tabs' => [
        '#theme' => 'item_list',
        '#items' => $tabLinks,
        '#attributes' => ['data-portal-ui' => 'studio-saved-tabs'],
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'studio-saved-list-wrapper'],
        'list' => empty($itemsList)
          ? [
            '#type' => 'container',
            '#attributes' => ['data-portal-ui' => 'studio-saved-empty'],
            'message' => [
              '#markup' => '<p>' . $emptyMessage . '</p>',
            ],
          ]
          : [
            '#theme' => 'item_list',
            '#items' => $itemsList,
            '#attributes' => ['data-portal-ui' => 'studio-card-compact-list'],
          ],
        'pager' => $pagination,
      ],
      '#cache' => [
        'contexts' => $cacheContexts,
        'tags' => $cacheTags,
        'max-age' => $cacheMaxAge,
      ],
    ];
  }

  /**
   * Builds compact cards from SavedContentQueryResult.
   *
   * @param \Drupal\aculta_portal\Saved\SavedContentQueryResult $queryResult
   *   The query result.
   *
   * @return array<int, array<string, mixed>>
   *   Render array items.
   */
  protected function buildCardsFromQueryResult(SavedContentQueryResult $queryResult): array {
    $cards = [];
    foreach ($queryResult->getItems() as $item) {
      $dateStr = $this->dateFormatter->format($item->getCreatedTime(), 'short');
      $url = $item->getUrl() ?? Url::fromRoute('<front>');

      $metaHtml = '<span data-portal-ui="studio-saved-meta">' . $this->t('Salvo em @date', ['@date' => $dateStr]) . '</span>';
      if ($item->getPurpose() !== NULL) {
        $metaHtml .= ' <span data-portal-ui="studio-saved-purpose">' . htmlspecialchars(strtoupper($item->getPurpose()), ENT_QUOTES, 'UTF-8') . '</span>';
      }

      $cards[] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'studio-card-compact saved-item'],
        'link' => [
          '#type' => 'link',
          '#title' => $item->getTitle() ?: $this->t('Sem título'),
          '#url' => $url,
          '#attributes' => ['data-portal-ui' => 'studio-saved-card-title'],
        ],
        'meta' => [
          '#markup' => $metaHtml,
        ],
      ];
    }

    return $cards;
  }

  /**
   * Builds pagination render array.
   *
   * @param \Drupal\aculta_portal\Saved\SavedContentQueryResult $queryResult
   *   The query result.
   * @param int $page
   *   Current 0-indexed page number.
   * @param int $limit
   *   Items per page.
   * @param string $activeTab
   *   Current active tab.
   * @param mixed $bundle
   *   Optional bundle filter.
   * @param mixed $purpose
   *   Optional purpose filter.
   *
   * @return array<string, mixed>
   *   Pagination render array.
   */
  protected function buildPagination(
    SavedContentQueryResult $queryResult,
    int $page,
    int $limit,
    string $activeTab,
    mixed $bundle,
    mixed $purpose,
  ): array {
    $totalCount = $queryResult->getTotalCount();
    $totalPages = (int) ceil($totalCount / $limit);

    if ($totalPages <= 1) {
      return [];
    }

    $links = [];
    if ($page > 0) {
      $prevQuery = array_filter([
        'tab' => $activeTab,
        'page' => $page > 1 ? $page - 1 : NULL,
        'bundle' => $bundle,
        'purpose' => $purpose,
      ]);
      $links[] = [
        '#type' => 'link',
        '#title' => $this->t('« Anterior'),
        '#url' => Url::fromRoute('aculta_studio.salvos', [], ['query' => $prevQuery]),
        '#attributes' => ['data-portal-ui' => 'studio-saved-pager-prev'],
      ];
    }

    $links[] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t('Página @current de @total (@count itens)', [
        '@current' => $page + 1,
        '@total' => $totalPages,
        '@count' => $totalCount,
      ]),
      '#attributes' => ['data-portal-ui' => 'studio-saved-pager-info'],
    ];

    if ($queryResult->hasMore()) {
      $nextQuery = array_filter([
        'tab' => $activeTab,
        'page' => $page + 1,
        'bundle' => $bundle,
        'purpose' => $purpose,
      ]);
      $links[] = [
        '#type' => 'link',
        '#title' => $this->t('Próxima »'),
        '#url' => Url::fromRoute('aculta_studio.salvos', [], ['query' => $nextQuery]),
        '#attributes' => ['data-portal-ui' => 'studio-saved-pager-next'],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-saved-pagination'],
      'links' => $links,
    ];
  }

  /**
   * Builds recommendation cards with transparent explanations.
   *
   * @param array<int, array<string, mixed>> $recommendations
   *   Items from RecommendationService.
   *
   * @return array<int, array<string, mixed>>
   *   Render items.
   */
  protected function buildRecommendationCards(array $recommendations): array {
    $cards = [];
    foreach ($recommendations as $rec) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $rec['entity'];
      $reason = $rec['reason'];

      $cards[] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'studio-card-compact recommendation-item'],
        'link' => [
          '#type' => 'link',
          '#title' => $entity->label() ?? $this->t('Sem título'),
          '#url' => $entity->toUrl(),
          '#attributes' => ['data-portal-ui' => 'studio-saved-card-title'],
        ],
        'reason' => [
          '#markup' => '<div data-portal-ui="studio-recommendation-reason"><span data-portal-ui="studio-recommendation-badge">' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</span></div>',
        ],
      ];
    }

    return $cards;
  }

}
