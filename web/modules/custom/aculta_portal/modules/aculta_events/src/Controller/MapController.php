<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Catalogue\LocationCatalogue;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\aculta_maps\MapRenderer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mapa de locais (/mapa).
 *
 * A lista textual funciona sem JavaScript e sem chave. O mapa só carrega
 * quando há credencial de provedor.
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
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_events.locations'), $container->get('aculta_maps.renderer'));
  }

  /**
   * Página do mapa com a lista de apoio.
   */
  public function page(): array {
    $marcadores = [];
    $itens = [];
    foreach ($this->locais->busca(NULL) as $item) {
      $local = $item['local'];
      $linha = '<strong>' . Html::escape((string) $local->label()) . '</strong>';
      if ($item['endereco'] !== '') {
        $linha .= '<br>' . Html::escape($item['endereco']);
      }
      $itens[] = ['#markup' => $linha];

      $coords = $local->get('field_coordenadas')->first()?->getValue();
      if (!empty($coords['lat']) && !empty($coords['lon'])) {
        $casas = (string) $local->get('field_precisao')->value === 'aproximado' ? self::CASAS_APROXIMADO : 6;
        $marcadores[] = [
          'label' => (string) $local->label(),
          'lat' => round((float) $coords['lat'], $casas),
          'lng' => round((float) $coords['lon'], $casas),
          'description' => $item['endereco'],
          'precision' => $casas === self::CASAS_APROXIMADO ? 'approximate' : 'exact',
        ];
      }
    }

    $mapa = $this->mapRenderer->build([
      'label' => (string) $this->t('Mapa de locais'),
      'markers' => $marcadores,
    ]);
    $render = [
      'lista' => ['#theme' => 'item_list', '#items' => $itens, '#empty' => $this->t('Nenhum local cadastrado.')],
      'aviso' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-map__aviso']],
        'texto' => [
          '#plain_text' => $this->t('Ao carregar o mapa, seu navegador envia seu endereço IP ao OpenStreetMap para solicitar os mapas. Consulte a política de privacidade para saber como funciona esse serviço.'),
        ],
        'politica' => [
          '#type' => 'link',
          '#title' => $this->t('Política de privacidade'),
          '#url' => Url::fromUri('internal:/sobre/politica-de-privacidade'),
        ],
      ],
      '#cache' => ['tags' => ['aculta_event_location_list'], 'max-age' => 900],
    ];

    $render['mapa'] = $mapa;

    return $render;
  }

}
