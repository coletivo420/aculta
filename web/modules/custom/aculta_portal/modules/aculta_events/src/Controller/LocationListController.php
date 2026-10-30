<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Catalogue\LocationCatalogue;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lista textual e paginada de locais com links canônicos e busca (LOC-05).
 */
final class LocationListController extends ControllerBase {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly LocationCatalogue $locais,
    private readonly RequestStack $pilha,
    private readonly PagerManagerInterface $pagerManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_events.locations'),
      $container->get('request_stack'),
      $container->get('pager.manager'),
    );
  }

  /**
   * Página da lista de locais.
   */
  public function page(): array {
    $request = $this->pilha->getCurrentRequest();
    $tipo = $request?->query->getInt('tipo') ?: NULL;
    $busca = trim((string) ($request?->query->get('busca') ?? ''));
    $page = $request?->query->getInt('page') ?: 0;
    $limit = 12;

    $resultado = $this->locais->buscaPaginada(
      $tipo,
      $busca !== '' ? $busca : NULL,
      $page,
      $limit,
      $this->currentUser(),
    );

    $this->pagerManager->createPager($resultado['total'], $limit);

    $itens = [];
    foreach ($resultado['items'] as $item) {
      /** @var \Drupal\aculta_events\Entity\EventLocation $local */
      $local = $item['local'];
      $url = Url::fromRoute('aculta_events.location_canonical', [
        'aculta_event_location' => $local->id(),
      ]);

      $linha = '<strong>' . Link::fromTextAndUrl((string) $local->label(), $url)->toString() . '</strong>';
      if ($item['endereco'] !== '') {
        $linha .= '<br><span class="location-address">' . Html::escape($item['endereco']) . '</span>';
      }
      if (!$local->get('field_osm_id')->isEmpty()) {
        $linha .= '<br><a href="https://www.openstreetmap.org/copyright" rel="noopener" target="_blank">'
          . Html::escape((string) $this->t('© OpenStreetMap contributors')) . '</a>';
      }
      $itens[] = ['#markup' => $linha];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-locations-list-page'],
      '#cache' => [
        'contexts' => ['url.query_args', 'user.permissions'],
        'tags' => ['aculta_event_location_list'],
        'max-age' => 900,
      ],
    ];

    // Ação de cadastrar local para usuários autorizados.
    if ($this->entityTypeManager()->getAccessControlHandler('aculta_event_location')->createAccess(NULL, $this->currentUser())) {
      $build['top_actions'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'event-location-actions-bar'],
        'add_btn' => [
          '#type' => 'link',
          '#title' => $this->t('+ Cadastrar local'),
          '#url' => Url::fromRoute('aculta_events.location_add'),
          '#attributes' => ['data-portal-ui' => 'event-location-button-primary-action'],
        ],
      ];
    }

    $build['list'] = [
      '#theme' => 'item_list',
      '#items' => $itens,
      '#empty' => $this->t('Nenhum local encontrado.'),
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

}
