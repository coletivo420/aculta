<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the central administration page for the public ACULTA Forum.
 */
final class ForumAdminOverviewController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
    #[Autowire(service: 'aculta_statistics.storage')]
    private readonly ?AggregateStorageInterface $statisticsStorage = NULL,
  ) {}

  /**
   * Renders an informative overview of the Growroom420 public Forum.
   *
   * The statistics are limited to the Forum topic bundle and its canonical
   * Domain. Other discussion hosts, including Magazine, LMS, and Wiki, are not
   * queried or included in this page.
   */
  public function overview(): array {
    $forumDomain = $this->domainPurposeManager->getDomain('forum');
    $topicCount = NULL;
    $categoryCount = NULL;

    if ($forumDomain !== NULL) {
      $topicCount = (int) $this->entities->getStorage('node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'aculta_forum_topic')
        ->condition('status', NodeInterface::PUBLISHED)
        ->condition('field_domain_source.target_id', $forumDomain->id())
        ->count()
        ->execute();
      $categoryCount = (int) $this->entities->getStorage('taxonomy_term')->getQuery()
        ->accessCheck(TRUE)
        ->condition('vid', 'aculta_forum_categories')
        ->count()
        ->execute();
    }

    $build = [
      '#type' => 'container',
      '#cache' => [
        'contexts' => ['domain', 'languages:language_interface', 'user.node_grants:view', 'user.permissions'],
        'tags' => [
          'node_list:aculta_forum_topic',
          'taxonomy_term_list:aculta_forum_categories',
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
   * Builds safe metric rows, omitting counts when the Forum domain is absent.
   */
  private function statisticsRows(?int $categoryCount, ?int $topicCount): array {
    if ($categoryCount === NULL || $topicCount === NULL) {
      return [];
    }

    return [
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
  }

}
