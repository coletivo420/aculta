<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

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

  public function __construct(
    protected readonly SavedContentService $savedContent,
    protected readonly RecommendationService $recommendations,
    protected readonly AccountInterface $account,
    protected readonly RequestStack $requestStack,
    protected readonly DateFormatterInterface $dateFormatter,
    TranslationInterface $stringTranslation,
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
    if ($activeTab === 'salvos') {
      $items = $this->savedContent->getSavedItems($this->account, 'saved', 30);
      $itemsList = $this->buildCompactCards($items);
    }
    elseif ($activeTab === 'seguindo') {
      $items = $this->savedContent->getSavedItems($this->account, 'following', 30);
      $itemsList = $this->buildCompactCards($items);
    }
    else {
      $recs = $this->recommendations->getRecommendations($this->account, 15);
      $itemsList = $this->buildRecommendationCards($recs);
    }

    $tabLinks = [];
    foreach ($tabs as $key => $tabData) {
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

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['studio-saved-page'],
        'data-portal-ui' => 'studio-saved-content',
      ],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->t('Meu espaço: salvos e recomendações'),
        '#attributes' => ['class' => ['page-title']],
      ],
      'tabs' => [
        '#theme' => 'item_list',
        '#items' => $tabLinks,
        '#attributes' => ['class' => ['nav-tabs', 'studio-saved-tabs']],
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['studio-saved-list-wrapper']],
        'list' => empty($itemsList)
          ? [
            '#type' => 'container',
            '#attributes' => ['class' => ['studio-saved-empty']],
            'message' => [
              '#markup' => '<p>' . ($activeTab === 'recomendacoes'
                ? $this->t('Nenhuma recomendação disponível no momento. Explore conteúdos na comunidade para receber sugestões.')
                : $this->t('Você ainda não possui itens nesta lista. Marque tópicos ou conteúdos para encontrá-los aqui.')) . '</p>',
            ],
          ]
          : [
            '#theme' => 'item_list',
            '#items' => $itemsList,
            '#attributes' => ['class' => ['studio-card-compact-list']],
          ],
      ],
      '#cache' => [
        'contexts' => ['user', 'url.query_args:tab'],
        'tags' => array_merge(
          $this->savedContent->getCacheTags($this->account),
          ['node_list']
        ),
      ],
    ];
  }

  /**
   * Builds compact cards for saved or followed items.
   *
   * @param array<int, array<string, mixed>> $items
   *   Items from SavedContentService.
   *
   * @return array<int, array<string, mixed>>
   *   Render items.
   */
  protected function buildCompactCards(array $items): array {
    $cards = [];
    foreach ($items as $item) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $item['entity'];
      $dateStr = $this->dateFormatter->format($item['created'], 'short');

      $cards[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['studio-card-compact', 'saved-item']],
        'link' => [
          '#type' => 'link',
          '#title' => $entity->label() ?? $this->t('Sem título'),
          '#url' => $entity->toUrl(),
          '#attributes' => ['class' => ['compact-card-title']],
        ],
        'meta' => [
          '#markup' => '<span class="compact-card-meta">' . $this->t('Salvo em @date', ['@date' => $dateStr]) . '</span>',
        ],
      ];
    }
    return $cards;
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
        '#attributes' => ['class' => ['studio-card-compact', 'recommendation-item']],
        'link' => [
          '#type' => 'link',
          '#title' => $entity->label() ?? $this->t('Sem título'),
          '#url' => $entity->toUrl(),
          '#attributes' => ['class' => ['compact-card-title']],
        ],
        'reason' => [
          '#markup' => '<div class="recommendation-reason"><span class="badge-reason">' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</span></div>',
        ],
      ];
    }
    return $cards;
  }

}
