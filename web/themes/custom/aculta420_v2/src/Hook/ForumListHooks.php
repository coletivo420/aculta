<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;

/**
 * Dados de apresentação da lista pública de tópicos do fórum.
 *
 * Só lê contagens e rótulos de categoria. Nada de regra de acesso ou de
 * domínio: a lista continua sendo a View do módulo aculta_forum.
 */
final class ForumListHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Prepara categorias e totais para o template da lista de tópicos.
   *
   * @param array<string, mixed> $variables
   *   The views_view variables.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(array &$variables): void {
    $view = $variables['view'] ?? NULL;
    if (!is_object($view) || $view->id() !== 'aculta_forum_public_topics') {
      return;
    }

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $categories = [];
    foreach ($this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'aculta_forum_categories']) as $term) {
      $count = (int) $nodeStorage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'aculta_forum_topic')
        ->condition('status', 1)
        ->condition('field_aculta_categories', $term->id())
        ->count()
        ->execute();
      $categories[] = [
        'label' => $term->label(),
        'url' => Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $term->id()])->toString(),
        'count' => $count,
      ];
    }

    $total = (int) $nodeStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'aculta_forum_topic')
      ->condition('status', 1)
      ->count()
      ->execute();

    $variables['forum_categories'] = $categories;
    $variables['forum_total_topics'] = $total;
  }

}
