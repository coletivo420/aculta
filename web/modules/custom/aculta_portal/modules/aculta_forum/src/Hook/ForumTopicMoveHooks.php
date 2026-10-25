<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Service\ForumTopicMover;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds the category move action to eligible public Forum topics.
 */
final class ForumTopicMoveHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.topic_mover')]
    private readonly ForumTopicMover $mover,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $account,
  ) {}

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity, CacheableMetadata $cacheability): array {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'aculta_forum_topic') {
      return [];
    }
    $access = $this->mover->access($entity, $this->account);
    $cacheability->addCacheableDependency($access)->addCacheContexts(['user.permissions']);
    if (!$access->isAllowed()) {
      return [];
    }
    return [
      'move_category' => [
        'title' => new TranslatableMarkup('Mover de categoria'),
        'weight' => 30,
        'url' => Url::fromRoute('aculta_forum.topic_move', ['node' => $entity->id()]),
      ],
    ];
  }

}
