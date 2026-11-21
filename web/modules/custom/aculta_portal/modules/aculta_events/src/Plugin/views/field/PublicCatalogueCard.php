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
 * Renders the prepared card fields for the public catalogue View.
 */
#[ViewsField('aculta_events_catalogue_card')]
final class PublicCatalogueCard extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs this service or plugin.
   *
   * @param array<mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param RequestStack $requestStack
   *   Request stack service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected readonly RequestStack $requestStack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param ContainerInterface $container
   *   Dependency injection container.
   * @param array<mixed> $configuration
   *   Plugin configuration.
   * @param mixed $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    if (!is_string($plugin_id)) {
      throw new \InvalidArgumentException('Plugin ID must be a string.');
    }
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
    $rawEventId = $values->aculta_event_id ?? NULL;
    if (!is_string($rawEventId) && !is_int($rawEventId)) {
      return '';
    }
    $eventId = (string) $rawEventId;
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return '';
    }
    $items = $request->attributes->get('_aculta_events_catalogue_cards', []);
    $item = is_array($items) ? ($items[$eventId] ?? NULL) : NULL;
    if (!is_array($item) || !isset($item['title']) || !is_string($item['title']) || trim($item['title']) === '') {
      return '';
    }

    $build = [
      '#theme' => 'portal_component',
      '#name' => 'card-cultural',
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
        'max-age' => $this->maxAge($request->attributes->get('_aculta_events_catalogue_max_age')),
      ],
    ];
    return $this->getRenderer()->render($build);
  }

  /**
   * Provides the maxAge operation.
   *
   * @param mixed $value
   *   Value used for $value.
   */
  private function maxAge(mixed $value): int {
    return is_int($value) ? $value : Cache::PERMANENT;
  }

}
