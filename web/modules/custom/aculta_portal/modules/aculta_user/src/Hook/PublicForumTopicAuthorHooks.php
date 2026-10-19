<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\Entity\LegacyAuthor;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Supplies ACULTA User author cards to new public discussion topics. */
final class PublicForumTopicAuthorHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.author_card_builder')]
    private readonly AuthorCardBuilder $authorCardBuilder,
  ) {}

  /**
   * Adds either the public User card or the immutable historical attribution.
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'aculta_forum_topic'
      || !in_array($view_mode, ['full', 'teaser'], TRUE)) {
      return;
    }

    if ($entity->hasField('field_autor_legado') && !$entity->get('field_autor_legado')->isEmpty()) {
      $legacyAuthor = $entity->get('field_autor_legado')->entity;
      if ($legacyAuthor instanceof LegacyAuthor) {
        $build['author_card'] = $this->authorCardBuilder->buildLegacy($legacyAuthor) + ['#weight' => -20];
      }
      return;
    }

    $author = $entity->getOwner();
    if (!$author instanceof UserInterface) {
      return;
    }

    $card = $this->authorCardBuilder->build($author, 'author_forum');
    if ($card === NULL) {
      return;
    }
    $card['#weight'] = -20;
    $build['author_card'] = $card;
  }

}
