<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Plugin\views\sort;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Attribute\ViewsSort;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\Plugin\views\sort\SortPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keeps the View rows in the catalogue's next-occurrence order. */
#[ViewsSort('aculta_events_catalogue_order')]
final class PublicCatalogueOrder extends SortPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly RequestStack $requestStack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc} */
  public function query(): void {
    if (!$this->query instanceof Sql) {
      return;
    }
    $this->ensureMyTable();
    $request = $this->requestStack->getCurrentRequest();
    $items = $request?->attributes->get('_aculta_events_catalogue_cards', []);
    if (!is_array($items) || $items === []) {
      return;
    }

    $branches = [];
    foreach (array_keys($items) as $weight => $id) {
      if (ctype_digit((string) $id)) {
        $branches[] = 'WHEN ' . (int) $id . ' THEN ' . (int) $weight;
      }
    }
    if ($branches === []) {
      return;
    }

    $formula = 'CASE ' . $this->tableAlias . '.id ' . implode(' ', $branches) . ' ELSE ' . count($branches) . ' END';
    $this->query->addOrderBy(NULL, $formula, 'ASC', $this->tableAlias . '_catalogue_order');
  }

}
