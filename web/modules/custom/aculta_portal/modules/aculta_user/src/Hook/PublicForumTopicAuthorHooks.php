<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Supplies ACULTA User author cards to new public discussion topics. */
final class PublicForumTopicAuthorHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.author_card_builder')]
    private readonly AuthorCardBuilder $authorCardBuilder,
  ) {}

  /**
   * Adds the current account author card to public Forum topics.
   *
   * @param array<array-key, mixed> $build
   *   Dados recebidos.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entidade renderizada.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   Configuração de exibição aplicada.
   * @param string $view_mode
   *   Modo de visualização.
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'aculta_forum_topic'
      || !in_array($view_mode, ['full', 'teaser'], TRUE)) {
      return;
    }

    $author = $entity->getOwner();
    if (!$author->isAuthenticated()) {
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
