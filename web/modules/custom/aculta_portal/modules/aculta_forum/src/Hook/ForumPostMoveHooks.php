<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Service\ForumPostMover;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds the split/move operation to eligible public Forum posts.
 */
final class ForumPostMoveHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.post_mover')]
    private readonly ForumPostMover $mover,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $account,
  ) {}

  /**
   * Adds an operation to Forum post administration listings.
   *
   * @return array<mixed>
   *   Operations added to the entity operation links.
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity, CacheableMetadata $cacheability): array {
    if (!$entity instanceof ForumPost) {
      return [];
    }
    $access = $this->mover->access($entity, $this->account);
    $cacheability->addCacheableDependency($access)->addCacheContexts(['user.permissions']);
    if (!$access->isAllowed()) {
      return [];
    }
    return [
      'move_forum_post' => [
        'title' => new TranslatableMarkup('Mover para outro tópico'),
        'weight' => 31,
        'url' => Url::fromRoute('aculta_forum.post_move', ['aculta_forum_post' => $entity->id()]),
      ],
    ];
  }

}
