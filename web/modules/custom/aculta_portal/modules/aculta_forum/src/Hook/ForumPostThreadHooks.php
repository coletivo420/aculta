<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Service\ForumPostThreadBuilder;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/**
 * Adds revisionable posts to the canonical public topic presentation.
 */
final class ForumPostThreadHooks {

  public function __construct(
    private readonly ForumPostThreadBuilder $threadBuilder,
  ) {}

  /**
   * Renders revisionable posts before the still-active Comment field.
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'aculta_forum_topic' || $view_mode !== 'full') {
      return;
    }

    $posts = $this->threadBuilder->build($entity);
    // A discussão do tópico é o compositor de Responder do cartão de abertura; o link antigo
    // "Responder à discussão" do bloco de posts não aparece na página.
    unset($posts['actions']['create']);
    // Sem posts legados, o construtor devolve só o contêiner vazio: não há nada a exibir.
    $hasItems = array_filter(array_keys($posts), static fn($key) => $key !== 'actions' && !str_starts_with((string) $key, '#')) !== [];
    if ($hasItems) {
      $build['aculta_forum_revisionable_posts'] = $posts;
    }
  }

}
