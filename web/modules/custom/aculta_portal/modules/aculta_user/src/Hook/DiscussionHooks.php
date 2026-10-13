<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;

/**
 * Discussão vinculada: trecho dos últimos comentários do tópico de Fórum e link para a discussão completa.
 *
 * Plano aculta_user, seção E3: os comentários moram só no tópico. O conteúdo mostra um trecho e um link para o
 * purpose FORUM, obtido por DomainPurposeManager (nunca por hostname). Sem tópico vinculado, não há bloco.
 * Só comentários publicados aparecem.
 */
final class DiscussionHooks {

  /** Campo de referência do conteúdo para o tópico de Fórum. */
  private const FIELD = 'field_discussao';

  /** Quantidade de comentários no trecho. */
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
    if (!$entity instanceof NodeInterface || $view_mode !== 'full' || !$entity->hasField(self::FIELD) || $entity->get(self::FIELD)->isEmpty()) {
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
      '#attributes' => ['data-aculta-user' => 'discussao'],
      'titulo' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => (string) t('Discussão')],
      'comentarios' => $items ?: ['#markup' => (string) t('Ainda não há comentários publicados.')],
      'link' => [
        '#type' => 'link',
        '#title' => t('Ver discussão completa (@n comentários)', ['@n' => $total]),
        '#url' => $url,
      ],
      '#cache' => ['tags' => $cache->getCacheTags(), 'contexts' => $cache->getCacheContexts()],
    ];
  }

  /** Número de comentários publicados do tópico. */
  private function publishedCount(int $topicId): int {
    return (int) $this->commentQuery($topicId)->count()->execute();
  }

  /** @return \Drupal\comment\CommentInterface[] */
  private function excerpt(int $topicId): array {
    $ids = $this->commentQuery($topicId)->sort('created', 'DESC')->range(0, self::EXCERPT_LIMIT)->execute();
    return array_values($this->entityTypeManager->getStorage('comment')->loadMultiple($ids));
  }

  private function commentQuery(int $topicId) {
    return $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', 'comment_forum')
      ->condition('entity_type', 'node')
      ->condition('entity_id', $topicId)
      ->condition('status', 1);
  }

  /** Trecho em texto puro, de até 200 caracteres. */
  private function summary(CommentInterface $comment): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $comment->get('comment_body')->value)) ?? '');
    return mb_strlen($text) > 200 ? mb_substr($text, 0, 197) . '...' : $text;
  }

}
