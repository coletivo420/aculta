<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\aculta_events\Cache\EventCacheTags;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renders the prepared card fields for the public catalogue View. */
#[ViewsField('aculta_events_catalogue_card')]
final class PublicCatalogueCard extends FieldPluginBase implements ContainerFactoryPluginInterface {

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
    $this->field_alias = $this->query->addField($this->tableAlias, 'id', 'aculta_event_id');
  }

  /**
   * {@inheritdoc} */
  public function render(ResultRow $values): MarkupInterface|string {
    $eventId = (string) ($values->aculta_event_id ?? '');
    $request = $this->requestStack->getCurrentRequest();
    $items = $request->attributes->get('_aculta_events_catalogue_cards', []);
    $item = is_array($items) ? ($items[$eventId] ?? NULL) : NULL;
    if (!is_array($item) || !isset($item['title']) || !is_string($item['title']) || trim($item['title']) === '') {
      return '';
    }

    $build = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:card-cultural',
      '#props' => [
        'title' => $item['title'],
        'url' => $item['url'] ?? NULL,
        'category' => $item['category'] ?? NULL,
        'date' => $item['date'] ?? NULL,
        'location' => $item['location'] ?? NULL,
        'summary' => $item['summary'] ?? NULL,
        'cancelled' => (bool) ($item['cancelled'] ?? FALSE),
        'cancel_label' => $item['cancel_label'] ?? 'Evento cancelado',
      ],
      '#cache' => [
        'contexts' => ['url.query_args', 'user', 'languages:language_interface'],
        'tags' => [EventCacheTags::LISTA],
        'max-age' => (int) ($request->attributes->get('_aculta_events_catalogue_max_age') ?? Cache::PERMANENT),
      ],
    ];
    return $this->getRenderer()->render($build);
  }

}
