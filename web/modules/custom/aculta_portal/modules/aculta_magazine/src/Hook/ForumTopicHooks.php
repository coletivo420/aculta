<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Hook;

use Drupal\aculta_magazine\Forum\ForumTopicSync;
use Drupal\aculta_magazine\Revista\RevistaPublications;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Mantém o tópico do Fórum de cada publicação da Revista (criação, edição e exclusão).
 */
final class ForumTopicHooks {

  public function __construct(
    #[Autowire(service: 'aculta_magazine.forum_topic_sync')]
    private readonly ForumTopicSync $sync,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_insert() for node entities.
   */
  #[Hook('node_insert')]
  public function nodeInsert(EntityInterface $entity): void {
    if ($this->isPublication($entity)) {
      $this->sync->sync($entity);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for node entities.
   */
  #[Hook('node_update')]
  public function nodeUpdate(EntityInterface $entity): void {
    if ($this->isPublication($entity)) {
      $this->sync->sync($entity);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for node entities.
   */
  #[Hook('node_delete')]
  public function nodeDelete(EntityInterface $entity): void {
    if ($this->isPublication($entity)) {
      $this->sync->unpublish($entity);
    }
  }

  private function isPublication(EntityInterface $entity): bool {
    return $entity instanceof NodeInterface && RevistaPublications::isPublication($entity->bundle());
  }

}
