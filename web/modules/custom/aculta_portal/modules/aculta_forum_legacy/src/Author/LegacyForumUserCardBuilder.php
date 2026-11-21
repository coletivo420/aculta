<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_legacy\Author;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Builds a historical author card without creating or exposing a User account.
 */
final class LegacyForumUserCardBuilder {

  /**
   * Builds the historical card used inline in forum topics and comments.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $author
   *   Historical author entity.
   *
   * @return array<array-key, mixed>
   *   Cacheable author card render array.
   */
  public function buildForum(ContentEntityInterface $author): array {
    if ($author->getEntityTypeId() !== 'aculta_forum_legacy_user') {
      throw new \InvalidArgumentException('The entity is not a forum legacy user.');
    }

    $build = [
      '#theme' => 'aculta_user_author_card',
      '#variant' => 'author_growroom_historico',
      '#display_name' => LegacyForumUserDisplayName::forForum((string) $author->label()),
      '#profile_url' => NULL,
      '#avatar' => NULL,
      '#has_avatar' => FALSE,
      '#member_since' => NULL,
      '#bio' => NULL,
      '#social_links' => [],
      '#designation' => NULL,
      '#actions' => [],
      '#reactions_received' => 0,
      '#reactions_given' => 0,
      '#source_label' => 'Acervo histórico Growroom',
    ];
    CacheableMetadata::createFromObject($author)->applyTo($build);
    return $build;
  }

}
