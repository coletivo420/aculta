<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Search;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Resultados do índice geral para a prévia (popup) e para a página de busca. O índice é do site inteiro,
 * então a consulta roda no host atual; cada resultado abre no host do próprio conteúdo.
 */
final class SearchResults {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Prévia: até $limit resultados e o total do índice para o termo (o total já vem filtrado por content_access).
   *
   * @return array{total: int, items: array<int, array{title: string, type: string, url: \Drupal\Core\Url}>}
   */
  public function search(string $term, int $limit = 8): array {
    $index = $this->entityTypeManager->getStorage('search_api_index')->load(SearchPageController::INDEX_ID);
    if ($index === NULL) {
      return ['total' => 0, 'items' => []];
    }
    $query = $index->query(['limit' => $limit, 'offset' => 0]);
    $query->addCondition('status', TRUE);
    $query->keys($term);
    $result = $query->execute();
    $total = (int) $result->getResultCount();
    return ['total' => $total, 'items' => $this->items($result->getResultItems())];
  }

  /**
   * @param array<int, \Drupal\search_api\Item\ItemInterface> $resultItems
   * @return array<int, array{title: string, type: string, url: \Drupal\Core\Url}>
   */
  private function items(array $resultItems): array {
    $results = [];
    foreach ($resultItems as $item) {
      $node = $item->getOriginalObject()?->getValue();
      // O índice não substitui o controle de acesso: checa item a item.
      if (!$node instanceof NodeInterface || !$node->access('view')) {
        continue;
      }
      $results[] = [
        'title' => (string) $node->label(),
        'type' => (string) $node->type->entity?->label(),
        'url' => $this->url($node),
      ];
    }
    return $results;
  }

  /** Link do resultado no host do conteúdo (pelo purpose do nó). */
  public function url(NodeInterface $node): Url {
    $relative = $node->toUrl();
    $domainId = $node->hasField('field_domain_source') ? $node->get('field_domain_source')->target_id : NULL;
    $purpose = is_string($domainId) ? $this->domainPurposeManager->getPurposeForDomainId($domainId) : NULL;
    $absolute = $purpose !== NULL ? $this->domainPurposeManager->canonicalPathUrl($purpose, $relative->toString()) : NULL;
    return $absolute ?? $relative;
  }

}
