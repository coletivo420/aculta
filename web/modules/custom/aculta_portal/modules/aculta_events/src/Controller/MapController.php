<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Catalogue\LocationCatalogue;
use Drupal\aculta_events\Geocoding\GeocodingService;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mapa de locais (/mapa). Progressivo: a lista textual (/locais) funciona sem JavaScript
 * e sem chave; o mapa só carrega quando a chave do Maps está provisionada.
 *
 * Privacidade: local "aproximado" aparece no mapa com coordenadas arredondadas (cerca de 1 km),
 * e nunca como texto de coordenada.
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
    private readonly GeocodingService $geocodificacao,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_events.locations'), $container->get('aculta_events.geocoding'));
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
          'nome' => (string) $local->label(),
          'lat' => round((float) $coords['lat'], $casas),
          'lng' => round((float) $coords['lon'], $casas),
          'endereco' => $item['endereco'],
        ];
      }
    }

    $chave = $this->geocodificacao->chave();
    $render = [
      'lista' => ['#theme' => 'item_list', '#items' => $itens, '#empty' => $this->t('Nenhum local cadastrado.')],
      'aviso' => [
        '#markup' => '<p class="aculta-map__aviso">' . $this->t('Ao carregar o mapa, seu navegador envia seu endereço IP e as informações de navegação ao Google. A política de privacidade do site detalha esse uso.') . '</p>',
      ],
      '#cache' => ['tags' => ['aculta_event_location_list'], 'max-age' => 900],
    ];

    if ($chave !== NULL) {
      $render['mapa'] = [
        '#markup' => '<div id="aculta-map" class="aculta-map" role="region" aria-label="' . Html::escape((string) $this->t('Mapa de locais')) . '"></div>',
      ];
      $render['#attached']['library'][] = 'aculta_events/mapa';
      $render['#attached']['drupalSettings']['acultaEvents'] = [
        'mapsKey' => $chave,
        'marcadores' => $marcadores,
      ];
    }
    else {
      $render['sem_mapa'] = ['#markup' => '<p>' . $this->t('O mapa não está disponível neste ambiente. A lista acima tem os mesmos locais.') . '</p>'];
    }

    return $render;
  }

}
