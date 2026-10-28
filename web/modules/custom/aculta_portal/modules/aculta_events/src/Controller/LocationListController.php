<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Catalogue\LocationCatalogue;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lista textual de locais com mapa progressivo e sem JavaScript (F4).
 */
final class LocationListController extends ControllerBase {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly LocationCatalogue $locais,
    private readonly RequestStack $pilha,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_events.locations'), $container->get('request_stack'));
  }

  /**
   * Página da lista de locais.
   */
  public function page(): array {
    $tipo = $this->pilha->getCurrentRequest()->query->getInt('tipo') ?: NULL;
    $itens = [];
    foreach ($this->locais->busca($tipo) as $item) {
      $linha = '<strong>' . Html::escape((string) $item['local']->label()) . '</strong>';
      if ($item['endereco'] !== '') {
        $linha .= '<br>' . Html::escape($item['endereco']);
      }
      if (!$item['local']->get('field_osm_id')->isEmpty()) {
        $linha .= '<br><a href="https://www.openstreetmap.org/copyright" rel="noopener">'
          . Html::escape((string) $this->t('© OpenStreetMap contributors')) . '</a>';
      }
      $itens[] = ['#markup' => $linha];
    }
    return [
      '#theme' => 'item_list',
      '#items' => $itens,
      '#empty' => $this->t('Nenhum local encontrado.'),
      '#cache' => ['contexts' => ['url.query_args'], 'tags' => ['aculta_event_location_list'], 'max-age' => 900],
    ];
  }

}
