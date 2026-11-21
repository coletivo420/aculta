<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_legacy\Hook;

use Drupal\aculta_forum_legacy\Author\LegacyForumUserCardBuilder;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Renders legacy authors only in their Growroom topic and comment context.
 */
final class LegacyForumUserAuthorHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum_legacy.author_card_builder')]
    private readonly LegacyForumUserCardBuilder $cardBuilder,
  ) {}

  /**
   * Adds the legacy author card to a historical Forum topic.
   *
   * @param array<array-key, mixed> $build
   *   Render array being assembled.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Rendered entity.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   Active display configuration.
   * @param string $view_mode
   *   Active view mode.
   */
  #[Hook('entity_view')]
  public function entityView(
    array &$build,
    EntityInterface $entity,
    EntityViewDisplayInterface $display,
    string $view_mode,
  ): void {
    if ($entity instanceof NodeInterface && $entity->bundle() === 'aculta_forum_topic'
      && in_array($view_mode, ['full', 'teaser'], TRUE)
      && $entity->hasField('field_autor_legado')
      && !$entity->get('field_autor_legado')->isEmpty()) {
      $author = $entity->get('field_autor_legado')->entity;
      if ($author instanceof ContentEntityInterface
        && $author->getEntityTypeId() === 'aculta_forum_legacy_user') {
        $build['author_card'] = $this->cardBuilder->buildForum($author) + ['#weight' => -20];
      }
    }

    if (!$entity instanceof CommentInterface || $entity->bundle() !== 'aculta_discussion_post'
      || $entity->getFieldName() !== 'field_aculta_discussion') {
      return;
    }

    $host = $entity->getCommentedEntity();
    if (!$host instanceof NodeInterface || $host->bundle() !== 'aculta_forum_topic'
      || !$host->hasField('field_autor_legado') || $host->get('field_autor_legado')->isEmpty()
      || !$entity->hasField('field_autor_legado') || $entity->get('field_autor_legado')->isEmpty()) {
      return;
    }

    $author = $entity->get('field_autor_legado')->entity;
    if ($author instanceof ContentEntityInterface
      && $author->getEntityTypeId() === 'aculta_forum_legacy_user') {
      $build['author_card'] = $this->cardBuilder->buildForum($author) + ['#weight' => -20];
    }
  }

}
