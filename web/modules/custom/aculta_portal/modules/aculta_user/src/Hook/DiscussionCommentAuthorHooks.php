<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\aculta_user\Identity\AuthorCardVariantResolver;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\user\UserInterface;

/**
 * Adds the shared ACULTA User identity card to discussion comments.
 */
final class DiscussionCommentAuthorHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.author_card_builder')]
    private readonly AuthorCardBuilder $authorCardBuilder,
    #[Autowire(service: 'aculta_user.author_card_variant_resolver')]
    private readonly AuthorCardVariantResolver $variantResolver,
  ) {}

  /**
   * Adds the appropriate author card to a rendered discussion entity.
   *
   * @param array<array-key, mixed> $build
   *   Render array to which the author card can be added.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being rendered.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The active entity display.
   * @param string $view_mode
   *   The active view mode.
   */
  #[Hook('entity_view')]
  public function entityView(
    array &$build,
    EntityInterface $entity,
    EntityViewDisplayInterface $display,
    string $view_mode,
  ): void {
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    if ($entity->getEntityTypeId() === 'aculta_forum_post') {
      $author = $entity instanceof EntityOwnerInterface ? $entity->getOwner() : NULL;
      if ($author instanceof UserInterface) {
        // Sem apelido, usa-se o cartão de fallback obrigatório.
        $card = $this->authorCardBuilder->build($author, 'author_forum')
          ?? $this->authorCardBuilder->buildFallback($author);
        $build['author_card'] = $card + ['#weight' => -20];
      }
      return;
    }

    if (!$entity instanceof CommentInterface ||
      $entity->bundle() !== 'aculta_discussion_post' ||
      $entity->getFieldName() !== 'field_aculta_discussion') {
      return;
    }

    $host = $entity->getCommentedEntity();
    $variant = $host === NULL ? NULL : $this->variantResolver->forHost($host);
    $archivedForumHost = $variant === 'author_forum'
      && $host->hasField('field_autor_legado')
      && !$host->get('field_autor_legado')->isEmpty();

    if ($archivedForumHost
      && $entity->hasField('field_autor_legado')
      && !$entity->get('field_autor_legado')->isEmpty()) {
      $legacyAuthor = $entity->get('field_autor_legado')->entity;
      if ($legacyAuthor instanceof ContentEntityInterface && $legacyAuthor->getEntityTypeId() === 'aculta_forum_legacy_user') {
        $build['author_card'] = $this->authorCardBuilder->buildLegacyForum($legacyAuthor)
          + ['#weight' => -20];
      }
      return;
    }

    if ($variant === NULL) {
      return;
    }

    $author = $entity->getOwner();
    $card = $this->authorCardBuilder->build($author, $variant) ?? $this->authorCardBuilder->buildFallback($author);
    $card['#weight'] = -20;
    $build['author_card'] = $card;
  }

}
