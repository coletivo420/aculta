<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;

/**
 * Provides the legacy Magazine excerpt and link while the old forum is active.
 *
 * The shared Comment formatter replaces this block when the new field exists.
 */
final class DiscussionHooks {

  use StringTranslationTrait;

  /**
   * Legacy publication reference to a Drupal Forum topic.
   *
   * @var string
   */
  private const FIELD = 'field_discussao';

  /**
   * Maximum number of legacy comments in the excerpt.
   *
   * @var int
   */
  private const EXCERPT_LIMIT = 5;

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $purposeManager,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_view() for node entities.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, EntityInterface $entity, mixed $display, string $view_mode): void {
    if (!$entity instanceof NodeInterface || $view_mode !== 'full' ||
      $entity->hasField('field_aculta_discussion') ||
      !$entity->hasField(self::FIELD) || $entity->get(self::FIELD)->isEmpty()) {
      return;
    }
    $topic = $entity->get(self::FIELD)->entity;
    if (!$topic instanceof NodeInterface || $topic->bundle() !== 'forum' || !$topic->isPublished()) {
      return;
    }

    $cache = CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($topic)
      ->addCacheTags(['comment_list']);

    $total = $this->publishedCount((int) $topic->id());
    $comments = $this->excerpt((int) $topic->id());
    $url = $this->purposeManager->routeUrl('forum', 'entity.node.canonical', ['node' => $topic->id()]);
    if ($url === NULL) {
      return;
    }

    $items = [];
    foreach ($comments as $comment) {
      $items[] = [
        '#type' => 'container',
        'autor' => ['#type' => 'html_tag', '#tag' => 'strong', '#value' => $comment->getAuthorName()],
        'texto' => ['#type' => 'html_tag', '#tag' => 'p', '#value' => $this->summary($comment)],
      ];
    }

    $build['discussao'] = [
      '#type' => 'container',
      // Depois da caixa de autoria (peso 100 em ArticleAuthorsHooks).
      '#weight' => 110,
      '#attributes' => ['data-aculta-user' => 'discussao', 'data-portal-ui' => 'discussion'],
      'titulo' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => (string) t('Discussão'),
        '#attributes' => ['data-portal-ui' => 'discussion-title'],
      ],
      'comentarios' => $items
        ? [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'discussion-list'],
          'itens' => array_map(
            static fn(array $item): array => $item + [
              '#attributes' => ['data-portal-ui' => 'discussion-item'],
            ],
            $items,
          ),
        ]
        : ['#markup' => (string) t('Ainda não há comentários publicados.')],
      'link' => [
        '#type' => 'link',
        // Forma escolhida pelo número, sem formatPlural: em pt-br a regra plural trata 0 como singular.
        '#title' => $total === 1
          ? t('Ver discussão completa (1 comentário)')
          : t('Ver discussão completa (@count comentários)', ['@count' => $total]),
        '#url' => $url,
        '#attributes' => ['data-portal-ui' => 'discussion-link'],
      ],
      '#cache' => ['tags' => $cache->getCacheTags(), 'contexts' => $cache->getCacheContexts()],
    ];
  }

  /**
   * Returns the number of published legacy topic comments.
   */
  private function publishedCount(int $topicId): int {
    return (int) $this->commentQuery($topicId)->count()->execute();
  }

  /**
   * Returns the newest published comments for the legacy excerpt.
   *
   * @return \Drupal\comment\CommentInterface[]
   *   Loaded comments, newest first.
   */
  private function excerpt(int $topicId): array {
    $ids = $this->commentQuery($topicId)->sort('created', 'DESC')->range(0, self::EXCERPT_LIMIT)->execute();
    return array_values($this->entityTypeManager->getStorage('comment')->loadMultiple($ids));
  }

  /**
   * Builds an access-independent query restricted to public legacy comments.
   */
  private function commentQuery(int $topicId) {
    return $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', 'comment_forum')
      ->condition('entity_type', 'node')
      ->condition('entity_id', $topicId)
      ->condition('status', 1);
  }

  /**
   * Returns a plain text comment excerpt with a 200-character limit.
   */
  private function summary(CommentInterface $comment): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $comment->get('comment_body')->value)) ?? '');
    return mb_strlen($text) > 200 ? mb_substr($text, 0, 197) . '...' : $text;
  }

}
