<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Forum;

use Drupal\aculta_magazine\Revista\RevistaPublications;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Read-only lookup for historical Magazine-to-Forum references.
 *
 * New publication discussions use Comment directly on their host entity. This
 * service deliberately has no API that creates or mutates legacy topics.
 */
final class LegacyPublicationTopicLookup {

  /**
   * Publicações que guardam o tópico (mesmos tipos da Revista).
   */
  private const TOPIC_FIELD = 'field_discussao';

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Tópico vinculado à publicação, ou NULL.
   */
  public function topicFor(NodeInterface $post): ?NodeInterface {
    if (!$post->hasField(self::TOPIC_FIELD)
      || $post->get(self::TOPIC_FIELD)->isEmpty()) {
      return NULL;
    }
    $topic = $post->get(self::TOPIC_FIELD)->entity;
    return $topic instanceof NodeInterface
      && $topic->bundle() === 'forum'
      ? $topic
      : NULL;
  }

  /**
   * Publicação de abertura do tópico, ou NULL se não estiver publicada.
   */
  public function openingPostFor(NodeInterface $topic): ?NodeInterface {
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', RevistaPublications::BUNDLES, 'IN')
      ->condition(self::TOPIC_FIELD . '.target_id', $topic->id())
      ->condition('status', 1)
      ->sort('nid')
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    $post = $this->entityTypeManager->getStorage('node')->load(reset($ids));
    return $post instanceof NodeInterface ? $post : NULL;
  }

}
