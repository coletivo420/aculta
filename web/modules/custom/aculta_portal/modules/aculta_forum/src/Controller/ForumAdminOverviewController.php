<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_forum\Analytics\ForumAnalyticsScopeResolver;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the central administration page for the public ACULTA Forum.
 */
final class ForumAdminOverviewController extends ControllerBase implements ContainerInjectionInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly ForumAnalyticsScopeResolver $scopeResolver,
    private readonly ?AggregateStorageInterface $statisticsStorage = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('aculta_forum.analytics_scope_resolver'),
      $container->has('aculta_statistics.storage') ? $container->get('aculta_statistics.storage') : NULL,
    );
  }

  /**
   * Renders an informative overview of the Growroom420 public Forum.
   *
   * The counts are public-scope only: a topic restricted by a Group, directly
   * or through its category, never enters the public indicator even when the
   * account viewing this page is an administrator. Other discussion hosts,
   * including Magazine, LMS, and Wiki, are not queried.
   */
  public function overview(): array {
    $forumDomain = $this->domainPurposeManager->getDomain('forum');
    $topicCount = NULL;
    $categoryCount = NULL;

    if ($forumDomain !== NULL) {
      $restrictedTopics = $this->scopeResolver->restrictedTopicIds();
      if ($restrictedTopics !== NULL) {
        $topicQuery = $this->entities->getStorage('node')->getQuery()
          ->accessCheck(FALSE)
          ->condition('type', 'aculta_forum_topic')
          ->condition('status', NodeInterface::PUBLISHED)
          ->condition('field_domain_source.target_id', $forumDomain->id());
        if ($restrictedTopics !== []) {
          $topicQuery->condition('nid', $restrictedTopics, 'NOT IN');
        }
        $topicCount = (int) $topicQuery->count()->execute();
      }

      $restrictedCategories = $this->scopeResolver->restrictedCategoryIds();
      if ($restrictedCategories !== NULL) {
        $categoryQuery = $this->entities->getStorage('taxonomy_term')->getQuery()
          ->accessCheck(FALSE)
          ->condition('vid', 'aculta_forum_categories');
        if ($restrictedCategories !== []) {
          $categoryQuery->condition('tid', $restrictedCategories, 'NOT IN');
        }
        $categoryCount = (int) $categoryQuery->count()->execute();
      }
    }

    $build = [
      '#type' => 'container',
      '#cache' => [
        'contexts' => ['domain', 'languages:language_interface', 'user.permissions'],
        'tags' => [
          'node_list:aculta_forum_topic',
          'taxonomy_term_list:aculta_forum_categories',
          'group_relationship_list',
          'config:aculta_forum.settings',
        ],
        'max-age' => 60,
      ],
      'introduction' => [
        '#plain_text' => $this->t('Administre e acompanhe o Growroom420, o espaço de discussões públicas independentes da ACULTA.'),
      ],
      'statistics' => [
        '#type' => 'table',
        '#caption' => $this->t('Resumo do fórum público'),
        '#header' => [$this->t('Indicador'), $this->t('Quantidade')],
        '#rows' => $this->statisticsRows($categoryCount, $topicCount),
        '#empty' => $this->t('Os dados do Growroom420 estão temporariamente indisponíveis.'),
      ],
      'activity' => $forumDomain !== NULL
        ? $this->activitySeries()
        : ['#plain_text' => $this->t('Os dados do Growroom420 estão temporariamente indisponíveis.')],
      'public_links' => [
        '#theme' => 'item_list',
        '#title' => $this->t('Acessar o fórum público'),
        '#items' => [],
      ],
    ];

    if ($forumDomain !== NULL) {
      $build['public_links']['#items'][] = [
        '#type' => 'link',
        '#title' => $this->t('Abrir Growroom420'),
        '#url' => $this->domainPurposeManager->pathUrl('forum', '/'),
      ];
      $build['public_links']['#items'][] = [
        '#type' => 'link',
        '#title' => $this->t('Ver categorias públicas'),
        '#url' => $this->domainPurposeManager->pathUrl('forum', '/categorias'),
      ];
      $account = $this->currentUser();
      if ($account->hasPermission('administer aculta_statistics')) {
        $build['public_links']['#items'][] = [
          '#type' => 'link',
          '#title' => $this->t('Relatório analítico do Fórum'),
          '#url' => Url::fromRoute('aculta_statistics.area', ['area' => 'forum']),
        ];
      }
      if ($account->hasPermission('moderar denuncias do forum')
        || $account->hasPermission('moderar denuncias ugc')
        || $account->hasPermission('moderar denuncias ugc: forum')) {
        $build['public_links']['#items'][] = [
          '#type' => 'link',
          '#title' => $this->t('Fila de denúncias do Fórum'),
          '#url' => Url::fromRoute('aculta_user.denuncias_forum'),
        ];
      }
      CacheableMetadata::createFromRenderArray($build)
        ->addCacheableDependency($forumDomain)
        ->applyTo($build);
    }

    return $build;
  }

  /**
   * Builds the seven most recent daily activity totals from Statistics.
   */
  private function activitySeries(): array {
    if ($this->statisticsStorage === NULL) {
      return [
        '#type' => 'container',
        'message' => [
          '#plain_text' => $this->t('A série temporal está indisponível neste ambiente.'),
        ],
      ];
    }

    $metrics = [
      'forum.topics.created' => $this->t('Tópicos criados'),
      'forum.comments.created' => $this->t('Comments legados criados'),
      'forum.posts.created' => $this->t('Posts revisionáveis criados'),
    ];
    $today = $this->statisticsStorage->currentDay();
    $rows = [];
    for ($day = $today - 6; $day <= $today; $day++) {
      foreach ($metrics as $metric => $label) {
        $rows[] = [
          gmdate('Y-m-d', $day * 86400),
          $label,
          $this->statisticsStorage->total($metric, $day, $day),
        ];
      }
    }

    return [
      '#type' => 'table',
      '#caption' => $this->t('Atividade do Fórum nos últimos sete dias (UTC)'),
      '#header' => [$this->t('Dia'), $this->t('Atividade'), $this->t('Quantidade')],
      '#rows' => $rows,
      '#empty' => $this->t('Ainda não há atividade agregada neste período.'),
    ];
  }

  /**
   * Builds safe metric rows, omitting counts when they cannot be proven.
   */
  private function statisticsRows(?int $categoryCount, ?int $topicCount): array {
    if ($categoryCount === NULL || $topicCount === NULL) {
      return [];
    }

    $rows = [
      [
        'data' => [
          $this->t('Categorias públicas'),
          ['data' => ['#plain_text' => (string) $categoryCount]],
        ],
      ],
      [
        'data' => [
          $this->t('Tópicos públicos publicados'),
          ['data' => ['#plain_text' => (string) $topicCount]],
        ],
      ],
    ];

    // Retratos do Statistics, quando a coleta já gravou o dia. São estoque
    // atual, não soma histórica.
    if ($this->statisticsStorage !== NULL) {
      $today = $this->statisticsStorage->currentDay();
      $snapshots = [
        'forum.replies.published_current' => $this->t('Respostas públicas publicadas'),
        'forum.topics.unanswered' => $this->t('Tópicos sem resposta'),
        'forum.reactions.active' => $this->t('Reações vigentes'),
      ];
      foreach ($snapshots as $metric => $label) {
        if ($this->statisticsStorage->hasDay($metric, $today)) {
          $rows[] = [
            'data' => [
              $label,
              ['data' => ['#plain_text' => (string) $this->statisticsStorage->total($metric, $today, $today)]],
            ],
          ];
        }
      }
    }

    return $rows;
  }

}
