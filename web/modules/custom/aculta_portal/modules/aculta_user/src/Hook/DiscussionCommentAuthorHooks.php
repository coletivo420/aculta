<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\Entity\LegacyAuthor;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\aculta_user\Identity\AuthorCardVariantResolver;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
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
   * Implements hook_entity_view() for shared discussion comments.
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($entity->getEntityTypeId() === 'aculta_forum_post') {
      if ($entity->hasField('legacy_author') && !$entity->get('legacy_author')->isEmpty()) {
        $legacyAuthor = $entity->get('legacy_author')->entity;
        if ($legacyAuthor instanceof LegacyAuthor) {
          $build['author_card'] = $this->authorCardBuilder->buildLegacy($legacyAuthor) + ['#weight' => -20];
        }
        return;
      }

      $author = $entity instanceof EntityOwnerInterface ? $entity->getOwner() : NULL;
      if ($author instanceof UserInterface && ($card = $this->authorCardBuilder->build($author, 'author_forum')) !== NULL) {
        $build['author_card'] = $card + ['#weight' => -20];
      }
      return;
    }

    if (!$entity instanceof CommentInterface ||
      $entity->bundle() !== 'aculta_discussion_post' ||
      $entity->getFieldName() !== 'field_aculta_discussion') {
      return;
    }

    if ($entity->hasField('field_autor_legado') && !$entity->get('field_autor_legado')->isEmpty()) {
      $legacyAuthor = $entity->get('field_autor_legado')->entity;
      if ($legacyAuthor instanceof LegacyAuthor) {
        $build['author_card'] = $this->authorCardBuilder->buildLegacy($legacyAuthor) + ['#weight' => -20];
      }
      return;
    }

    $host = $entity->getCommentedEntity();
    if ($host === NULL || ($variant = $this->variantResolver->forHost($host)) === NULL) {
      return;
    }

    $author = $entity->getOwner();
    if (!$author instanceof UserInterface) {
      return;
    }

    $card = $this->authorCardBuilder->build($author, $variant);
    if ($card === NULL) {
      return;
    }
    $card['#weight'] = -20;
    $build['author_card'] = $card;
  }

}
