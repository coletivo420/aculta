<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Catalogue\LocationCatalogue;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\aculta_maps\MapRenderer;
use Drupal\aculta_maps\RouteLinkBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mapa de locais (/mapa).
 *
 * A lista textual funciona sem JavaScript e sem chave. O mapa usa
 * OpenStreetMap/Leaflet por padrão, sem chave; Google só com credencial.
 *
 * Privacidade: local "aproximado" aparece com coordenadas arredondadas (cerca
 * de 1 km), e nunca como texto de coordenada.
 */
final class MapController extends ControllerBase {

  /**
   * Casas decimais das coordenadas de local aproximado (~1 km).
   */
  private const CASAS_APROXIMADO = 2;

  /**
   * Construtor.
   */
  public function __construct(
    private readonly LocationCatalogue $locais,
    private readonly MapRenderer $mapRenderer,
    private readonly RouteLinkBuilder $routeLinks,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_events.locations'), $container->get('aculta_maps.renderer'), $container->get('aculta_maps.route_link_builder'));
  }

  /**
   * Página do mapa com a lista de apoio.
   */
  public function page(): array {
    $marcadores = [];
    $itens = [];
    $resultado = $this->locais->buscaPaginada(NULL, NULL, 0, 0);
    foreach ($resultado['items'] as $item) {
      $local = $item['local'];
      $urlGerada = $local->toUrl()->toString(TRUE);
      $resultado['cache_metadata']->addCacheableDependency($urlGerada);
      $urlLocal = $urlGerada->getGeneratedUrl();
      $categoria = '';
      $tipo = $local->get('field_tipo')->entity;
      if ($tipo !== NULL) {
        $acessoTipo = $tipo->access('view', NULL, TRUE);
        $resultado['cache_metadata']->addCacheableDependency($acessoTipo);
        if ($acessoTipo->isAllowed()) {
          $resultado['cache_metadata']->addCacheableDependency($tipo);
          $categoria = (string) $tipo->label();
        }
      }
      $itemRender = [
        '#type' => 'container',
        'location' => [
          '#markup' => '<a href="' . Html::escape($urlLocal) . '"><strong>' . Html::escape((string) $local->label()) . '</strong></a>',
        ],
      ];
      if ($categoria !== '') {
        $itemRender['category'] = ['#markup' => '<br>' . Html::escape($categoria)];
      }
      if ($item['endereco'] !== '') {
        $itemRender['address'] = ['#markup' => '<br>' . Html::escape($item['endereco']) . ' '];
      }
      if ($item['endereco'] !== '') {
        $itemRender['route'] = [
          '#type' => 'link',
          '#title' => $this->t('Ver rota'),
          '#url' => $this->routeLinks->build($item['endereco']),
          '#options' => ['attributes' => ['data-portal-ui' => 'map-location-route']],
        ];
      }
      $itens[] = $itemRender;

      $coords = $local->get('field_coordenadas')->first()?->getValue();
      // Zero is a valid coordinate (Equator / Greenwich); empty() would drop
      // valid locations at either axis. MapRenderer applies numeric range
      // validation before exposing marker data to the browser.
      if (is_numeric($coords['lat'] ?? NULL) && is_numeric($coords['lon'] ?? NULL)) {
        $casas = (string) $local->get('field_precisao')->value === 'aproximado' ? self::CASAS_APROXIMADO : 6;
        $marcadores[] = [
          'label' => (string) $local->label(),
          'lat' => round((float) $coords['lat'], $casas),
          'lng' => round((float) $coords['lon'], $casas),
          'description' => $item['endereco'],
          'url' => $urlLocal,
          'category' => $categoria,
          'precision' => $casas === self::CASAS_APROXIMADO ? 'approximate' : 'exact',
          'routeUrl' => $item['endereco'] !== '' ? $this->routeLinks->build($item['endereco'])->toString() : '',
        ];
      }
    }

    $mapa = $this->mapRenderer->build([
      'label' => (string) $this->t('Mapa de locais'),
      'markers' => $marcadores,
    ]);
    $render = [
      'lista' => ['#theme' => 'item_list', '#items' => $itens, '#empty' => $this->t('Nenhum local cadastrado.')],
      // Decisão editorial de 2026-10-29: sem o texto inline de envio de IP;
      // ficam a atribuição do provedor (Leaflet/OSM) e o link da política.
      'aviso' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'map-privacy-notice'],
        'politica' => [
          '#type' => 'link',
          '#title' => $this->t('Política de privacidade'),
          '#url' => Url::fromUri('internal:/sobre/politica-de-privacidade'),
        ],
      ],
      '#cache' => ['tags' => ['aculta_event_location_list'], 'max-age' => 900],
    ];

    // Ordem mobile-first: mapa, aviso (política) e lista textual equivalente.
    $render['mapa'] = $mapa + ['#weight' => -10];
    $render['aviso']['#weight'] = -5;

    CacheableMetadata::createFromRenderArray($render)
      ->addCacheableDependency($resultado['cache_metadata'])
      ->applyTo($render);

    return $render;
  }

}
