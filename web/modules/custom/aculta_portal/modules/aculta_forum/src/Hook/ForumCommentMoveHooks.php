<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Service\ForumCommentMover;
use Drupal\comment\CommentInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds the move operation to eligible public Forum comments. */
final class ForumCommentMoveHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.comment_mover')]
    private readonly ForumCommentMover $mover,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $account,
  ) {}

  /**
 * Adds an operation to Core comment administration listings. */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity, CacheableMetadata $cacheability): array {
    if (!$entity instanceof CommentInterface) {
      return [];
    }
    $access = $this->mover->access($entity, $this->account);
    $cacheability->addCacheableDependency($access)->addCacheContexts(['user.permissions']);
    if (!$access->isAllowed()) {
      return [];
    }
    return [
      'move_forum_comment' => [
        'title' => new TranslatableMarkup('Mover para outro tópico'),
        'weight' => 30,
        'url' => Url::fromRoute('aculta_forum.comment_move', ['comment' => $entity->id()]),
      ],
    ];
  }

}
