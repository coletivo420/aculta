<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Template\Attribute;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/** Public landing page and bounded title/summary/body search for the Wiki. */
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
  ) {}

  /** Builds the Wiki home from the editorial Views. */
  public function home(): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'wiki-home'],
      '#cache' => [
        'contexts' => ['domain', 'user.permissions'],
        'tags' => ['node_list:wiki_entry', 'taxonomy_term_list'],
      ],
      'intro' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'wiki-intro'],
        'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h1', '#value' => $this->t('Wiki420')],
        'text' => ['#plain_text' => $this->t('Wiki420 é uma wiki antiproibicionista, participativa e baseada em fontes. Aqui reunimos conhecimento sobre maconha, direitos, cultura, ciência, saúde, história, redução de danos e luta pela legalização.')],
      ],
      'search' => [
        '#type' => 'form',
        '#method' => 'get',
        '#action' => Url::fromRoute('aculta_portal.wiki_search')->toString(),
        '#attributes' => ['role' => 'search', 'data-portal-ui' => 'wiki-search'],
        'q' => [
          '#type' => 'textfield',
          '#name' => 'q',
          '#title' => $this->t('Buscar verbetes'),
          '#size' => 40,
          '#maxlength' => 100,
          '#required' => TRUE,
          '#attributes' => ['autocomplete' => 'off'],
        ],
        'submit' => ['#type' => 'submit', '#value' => $this->t('Buscar')],
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
        '#access' => $this->account->hasPermission('create wiki_entry content'),
      ],
      'categories_title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h2', '#value' => $this->t('Categorias da Wiki420')],
      'categories' => $this->viewBlock('wiki_categories', 'block_1'),
      'recent_title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h2', '#value' => $this->t('Verbetes recentes')],
      'recent' => $this->viewBlock('wiki_entries', 'block_recent'),
      'changes_title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h2', '#value' => $this->t('Alterações recentes da Wiki420')],
      'changes' => $this->recentChanges(),
    ];
    return $build;
  }

  /** Searches only published Wiki entries by title, summary, and body. */
  public function search(Request $request): array {
    $term = trim((string) $request->query->get('q', ''));
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'wiki-search-results'],
      '#cache' => ['contexts' => ['url.query_args:q', 'domain', 'user.permissions'], 'tags' => ['node_list:wiki_entry']],
      'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h1', '#value' => $this->t('Buscar na Wiki420')],
      'form' => [
        '#type' => 'form',
        '#method' => 'get',
        '#action' => Url::fromRoute('aculta_portal.wiki_search')->toString(),
        '#attributes' => ['role' => 'search'],
        'q' => ['#type' => 'textfield', '#name' => 'q', '#title' => $this->t('Termo de busca'), '#default_value' => $term, '#maxlength' => 100, '#required' => TRUE],
        'submit' => ['#type' => 'submit', '#value' => $this->t('Buscar')],
      ],
    ];
    if ($term === '') {
      return $build;
    }
    $term = mb_substr($term, 0, 100);
    $wikiDomain = $this->domainPurposeManager->getDomain('wiki');
    if (!$wikiDomain) {
      $build['empty'] = ['#type' => 'item', '#plain_text' => $this->t('A busca da Wiki420 está indisponível no momento.')];
      return $build;
    }
    $pattern = '%' . $this->database->escapeLike($term) . '%';
    // Public search: node access applies to the listing, and each result is
    // still checked individually before rendering.
    $nodeStorage = $this->entities->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'wiki_entry')
      ->condition('field_domain_source.target_id', $wikiDomain->id())
      ->condition('status', 1)
      ->range(0, 30)
      ->sort('title');
    $matches = $query->orConditionGroup()
      ->condition('title', $pattern, 'LIKE')
      ->condition('field_wiki_summary.value', $pattern, 'LIKE')
      ->condition('body.value', $pattern, 'LIKE');
    $ids = $query->condition($matches)->execute();
    $viewBuilder = $this->entities->getViewBuilder('node');
    $results = [];
    foreach ($nodeStorage->loadMultiple($ids) as $node) {
      if ($node->access('view')) {
        $results[$node->id()] = $viewBuilder->view($node, 'teaser');
      }
    }
    // pt-BR plural rules use the singular form for 0, so the count is only
    // shown when there is at least one visible result.
    if ($results) {
      $build['count'] = ['#type' => 'item', '#plain_text' => $this->formatPlural(count($results), '1 verbete encontrado.', '@count verbetes encontrados.')];
      $build['results'] = $results;
    }
    else {
      $build['empty'] = ['#type' => 'item', '#plain_text' => $this->t('Nenhum verbete publicado corresponde a essa busca.')];
    }
    return $build;
  }

  /** Lists published Wiki420 entries by latest update, without public authorship. */
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
