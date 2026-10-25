<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Hook;

use Drupal\aculta_statistics\Controller\WikiReadController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Anexa o beacon de leitura à visualização completa de verbete publicado.
 *
 * O contador é gravado pelo servidor a cada requisição do beacon, e a página
 * em cache continua enviando o beacon. Por isso o anexo fica na renderização do
 * nó, e não em um cache de contagem.
 */
final class WikiReadHooks {

  /**
   * Anexa o beacon de leitura a verbetes publicados na visualização completa.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== WikiReadController::BUNDLE || $view_mode !== 'full' || !$entity->isPublished()) {
      return;
    }

    $build['#attached']['library'][] = 'aculta_statistics/wiki-read';
    $build['#attached']['drupalSettings']['acultaStatistics']['wikiRead'] = [
      'url' => Url::fromRoute('aculta_statistics.wiki_read', ['node' => $entity->id()], ['absolute' => FALSE])->toString(),
    ];
  }

}
