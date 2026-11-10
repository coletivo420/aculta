<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Cache\EventCacheTags;
use Drupal\aculta_events\Catalogue\LocationCatalogue;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_events\Occurrence\UpcomingDates;
use Drupal\aculta_maps\MapRenderer;
use Drupal\aculta_maps\RouteLinkBuilder;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Página pública de um evento com descrição, datas, locais e organizador.
 *
 * Rascunho e slug inexistente respondem 404, sem confirmar que o evento existe.
 * Cancelado aparece com aviso (MODELO-DE-DADOS.md).
 */
final class EventPageController extends ControllerBase {

  /**
   * Horizonte de datas listadas na página: um ano.
   */
  private const HORIZONTE_DIAS = 365;

  /**
   * Validade do cache quando o evento não tem data futura: 15 minutos.
   */
  private const VALIDADE_PADRAO = 900;

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly UpcomingDates $proximas,
    private readonly TimeInterface $tempo,
    private readonly ?DomainPurposeManager $purposes = NULL,
    private readonly ?AuthorIdentityResolver $identities = NULL,
    private readonly ?MapRenderer $mapRenderer = NULL,
    private readonly ?RouteLinkBuilder $routeLinks = NULL,
    private readonly ?LocationCatalogue $locationCatalogue = NULL,
    private readonly ?RendererInterface $renderer = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_events.upcoming'),
      $container->get('datetime.time'),
      $container->has('aculta_portal.domain_purpose') ? $container->get('aculta_portal.domain_purpose') : NULL,
      $container->has('aculta_user.author_identity_resolver') ? $container->get('aculta_user.author_identity_resolver') : NULL,
      $container->has('aculta_maps.renderer') ? $container->get('aculta_maps.renderer') : NULL,
      $container->has('aculta_maps.route_link_builder') ? $container->get('aculta_maps.route_link_builder') : NULL,
      $container->has('aculta_events.locations') ? $container->get('aculta_events.locations') : NULL,
      $container->has('renderer') ? $container->get('renderer') : NULL,
    );
  }

  /**
   * Página do evento.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function page(string $slug): array {
    $evento = $this->eventoPorSlug($slug);

    $agora = (new \DateTimeImmutable('@' . $this->tempo->getCurrentTime()))
      ->setTimezone(new \DateTimeZone(UpcomingDates::FUSO));
    $ocorrencias = $this->proximas
      ->doEvento($evento, $agora, self::HORIZONTE_DIAS);

    $linhas = [];
    $podeEditarRecorrencia = $this->currentUser()->hasPermission('administer aculta events')
      && ($this->purposes === NULL || $this->purposes->getCurrentPurpose() === 'main');
    // A tag de lista cobre data criada, editada ou excluída. Access results
    // below also contribute their contexts, including owner-only locations.
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($evento)
      ->addCacheTags([EventCacheTags::LISTA])
      ->addCacheContexts(['user.permissions']);
    $localViewAccess = [];
    foreach ($ocorrencias as $ocorrencia) {
      $local = $ocorrencia['data']->get('field_local')->entity;
      $texto = Html::escape($ocorrencia['inicio']->format('d/m/Y H:i') . ' às ' . $ocorrencia['fim']->format('H:i'));
      if ($local instanceof EventLocation) {
        $access = $local->access('view', $this->currentUser(), TRUE);
        $cacheability->addCacheableDependency($local)->addCacheableDependency($access);
        $localViewAccess[(string) $local->id()] = $access;
        if ($access->isAllowed()) {
          $localUrl = Url::fromRoute('aculta_events.location_canonical', [
            'aculta_event_location' => $local->id(),
          ])->toString();
          $texto .= ' — <a href="' . Html::escape($localUrl) . '">' . Html::escape((string) $local->label()) . '</a>';
          if (!$local->get('field_osm_id')->isEmpty()) {
            $texto .= ' — <a href="https://www.openstreetmap.org/copyright" rel="noopener">'
              . Html::escape((string) $this->t('© OpenStreetMap contributors')) . '</a>';
          }
        }
      }
      if ($ocorrencia['cancelada'] || $evento->get('field_status')->value === 'cancelado') {
        $texto .= ' <strong>(cancelado)</strong>';
      }
      $data = $ocorrencia['data'];
      if ($podeEditarRecorrencia && !$data->get('field_regra')->isEmpty()) {
        $id = (string) $data->id();
        $urlRegra = Url::fromRoute('aculta_events.date_rule', ['aculta_event_date' => $id])->toString();
        $urlOcorrencia = Url::fromRoute('aculta_events.occurrence', [
          'aculta_event_date' => $id,
          'inicio' => (string) $ocorrencia['original']->getTimestamp(),
        ])->toString();
        $texto .= ' — <a href="' . Html::escape($urlRegra) . '">'
          . Html::escape((string) $this->t('Editar recorrência')) . '</a>';
        $texto .= ' · <a href="' . Html::escape($urlOcorrencia) . '">'
          . Html::escape((string) $this->t('Editar esta ocorrência')) . '</a>';
      }
      $linha = ['#markup' => $texto];
      $linhas[] = $linha;
    }

    // O conjunto de datas muda quando a próxima termina, não a cada requisição.
    $validade = $this->proximas->validade($ocorrencias, $this->tempo->getCurrentTime()) ?? self::VALIDADE_PADRAO;

    // Dados schema.org (Event), uma entrada por ocorrência listada (até 20).
    $eventosLd = [];
    $urlEvento = Url::fromRoute('aculta_events.page', ['slug' => (string) $evento->get('field_slug')->value], ['absolute' => TRUE])->toString();
    $descricao = mb_substr(trim(strip_tags((string) $evento->get('body')->value)), 0, 500);
    $organizador = $evento->get('field_organizador')->entity;
    $organizadorIdentidade = NULL;
    if ($organizador !== NULL) {
      $cacheability->addCacheableDependency($organizador);
      if ($this->identities !== NULL) {
        $organizadorIdentidade = $this->identities->resolvePublic($organizador);
        $cacheability->addCacheableDependency($organizadorIdentidade);
      }
    }
    $org = $evento->getOrganization();
    $orgAccess = $org?->access('view', $this->currentUser(), TRUE);
    if ($org !== NULL && $orgAccess !== NULL) {
      $cacheability->addCacheableDependency($org)->addCacheableDependency($orgAccess);
    }

    foreach (array_slice($ocorrencias, 0, 20) as $ocorrencia) {
      $local = $ocorrencia['data']->get('field_local')->entity;
      $modo = (string) ($ocorrencia['data']->get('field_modo')->value ?? 'presencial');

      $entrada = [
        '@type' => 'Event',
        'name' => (string) $evento->label(),
        'url' => $urlEvento,
        'startDate' => $ocorrencia['inicio']->format('c'),
        'endDate' => $ocorrencia['fim']->format('c'),
        'eventStatus' => ($ocorrencia['cancelada'] || $evento->get('field_status')->value === 'cancelado')
          ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
      ];
      if ($descricao !== '') {
        $entrada['description'] = $descricao;
      }

      if ($modo === 'online') {
        $entrada['eventAttendanceMode'] = 'https://schema.org/OnlineEventAttendanceMode';
      }
      elseif ($modo === 'hibrido') {
        $entrada['eventAttendanceMode'] = 'https://schema.org/MixedEventAttendanceMode';
      }
      else {
        $entrada['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
      }

      // Link virtual para modalidades online ou híbrida.
      if (in_array($modo, ['online', 'hibrido'], TRUE) && !$ocorrencia['data']->get('field_link_virtual')->isEmpty()) {
        $virtualUrl = (string) $ocorrencia['data']->get('field_link_virtual')->uri;
        if ($virtualUrl !== '') {
          $entrada['location'] = [
            '@type' => 'VirtualLocation',
            'url' => $virtualUrl,
          ];
        }
      }

      // Local físico.
      if ($local instanceof EventLocation) {
        $localAccess = $localViewAccess[(string) $local->id()]
          ?? $local->access('view', $this->currentUser(), TRUE);
        $cacheability->addCacheableDependency($local)->addCacheableDependency($localAccess);
        if ($localAccess->isAllowed()) {
          $placeData = [
            '@type' => 'Place',
            'name' => (string) $local->label(),
          ];
          $placeData['url'] = Url::fromRoute('aculta_events.location_canonical', [
            'aculta_event_location' => $local->id(),
          ], ['absolute' => TRUE])->toString();

          $addressItem = $local->get('field_endereco')->first();
          if ($addressItem !== NULL) {
            $locality = (string) $addressItem->locality;
            $adminArea = (string) $addressItem->administrative_area;
            if ($locality !== '' || $adminArea !== '') {
              $postalAddress = [
                '@type' => 'PostalAddress',
                'addressCountry' => 'BR',
              ];
              if ($locality !== '') {
                $postalAddress['addressLocality'] = $locality;
              }
              if ($adminArea !== '') {
                $postalAddress['addressRegion'] = $adminArea;
              }
              $precisao = (string) ($local->get('field_precisao')->value ?? 'exato');
              if ($precisao !== 'aproximado') {
                $street = (string) $addressItem->address_line1;
                $postalCode = (string) $addressItem->postal_code;
                if ($street !== '') {
                  $postalAddress['streetAddress'] = $street;
                }
                if ($postalCode !== '') {
                  $postalAddress['postalCode'] = $postalCode;
                }
              }
              $placeData['address'] = $postalAddress;
            }
          }

          if (isset($entrada['location']) && is_array($entrada['location']) && ($entrada['location']['@type'] ?? '') === 'VirtualLocation') {
            $entrada['location'] = [$entrada['location'], $placeData];
          }
          else {
            $entrada['location'] = $placeData;
          }
        }
      }

      // Organizador: prioriza Organização pública; fallback para usuário.
      if ($org !== NULL && $orgAccess?->isAllowed()) {
        $orgSlug = (string) $org->get('field_slug')->value;
        $orgLd = [
          '@type' => 'Organization',
          'name' => (string) $org->label(),
        ];
        if ($orgSlug !== '') {
          $orgLd['url'] = Url::fromRoute('aculta_events.organization_page', ['slug' => $orgSlug], ['absolute' => TRUE])->toString();
        }
        $entrada['organizer'] = $orgLd;
      }
      elseif ($organizadorIdentidade !== NULL) {
        $entrada['organizer'] = [
          '@type' => 'Person',
          'name' => $organizadorIdentidade->toArray()['display_name'],
        ];
      }

      // Ingressos e ofertas.
      if (!$ocorrencia['data']->get('field_gratuito')->isEmpty() && !empty($ocorrencia['data']->get('field_gratuito')->value)) {
        $entrada['offers'] = [
          '@type' => 'Offer',
          'price' => '0',
          'priceCurrency' => 'BRL',
          'availability' => 'https://schema.org/InStock',
        ];
      }
      elseif (!$ocorrencia['data']->get('field_ticketing')->isEmpty()) {
        $ticketingUrl = (string) $ocorrencia['data']->get('field_ticketing')->uri;
        if ($ticketingUrl !== '') {
          $entrada['offers'] = [
            '@type' => 'Offer',
            'url' => $ticketingUrl,
            'availability' => 'https://schema.org/InStock',
          ];
        }
      }

      $eventosLd[] = $entrada;
    }
    $anexos = [];
    if ($eventosLd !== []) {
      // Escapa "</" para que o JSON não feche a tag de script por acidente.
      $jsonLd = json_encode(
        ['@context' => 'https://schema.org', '@graph' => $eventosLd],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
      );
      $json = str_replace('</', '<\\/', (string) $jsonLd);
      $anexos['html_head'][] = [
        [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#attributes' => ['type' => 'application/ld+json'],
          '#value' => $json,
        ],
        'aculta_events_event_jsonld',
      ];
    }

    $prefix = '';
    if ($this->purposes?->getCurrentPurpose() === 'main' && $evento->access('update')) {
      // A administração vive no MAIN; a página pública no purpose eventos.
      $adminPath = '/painel-administrativo/eventos/' . $evento->id() . '/editar';
      $adminUrl = $this->purposes?->environmentPathUrl('main', $adminPath);
      $editUrl = $adminUrl?->toString()
        ?? Url::fromRoute('aculta_events.event_edit', ['aculta_event' => $evento->id()])->toString();
      $adminButton = [
        '#type' => 'aculta_portal_button',
        '#props' => [
          'text' => (string) $this->t('Editar evento e datas'),
          'url' => $editUrl,
          'variant' => 'primary',
        ],
      ];
      $buttonHtml = $this->renderer !== NULL ? (string) $this->renderer->renderInIsolation($adminButton) : '';
      $prefix = '<div class="event-admin-actions mb-4">' . $buttonHtml . '</div>';
    }

    // Organização verificada e coorganizadores (ARQ-05 / OC-C).
    $orgData = NULL;
    if ($org !== NULL && $orgAccess?->isAllowed()) {
      $orgSlug = (string) $org->get('field_slug')->value;
      $orgUrl = $orgSlug !== ''
        ? Url::fromRoute('aculta_events.organization_page', ['slug' => $orgSlug])
        : NULL;
      $orgData = [
        'label' => (string) $org->label(),
        'url' => $orgUrl?->toString(),
      ];
    }

    $coOrgs = [];
    foreach ($evento->get('field_coorganizadores')->referencedEntities() as $coOrg) {
      if (!$coOrg instanceof EventOrganization) {
        continue;
      }
      $coOrgAccess = $coOrg->access('view', $this->currentUser(), TRUE);
      $cacheability->addCacheableDependency($coOrg)->addCacheableDependency($coOrgAccess);
      if ($coOrgAccess->isAllowed()) {
        $coOrgSlug = (string) $coOrg->get('field_slug')->value;
        $coUrl = $coOrgSlug !== ''
          ? Url::fromRoute('aculta_events.organization_page', ['slug' => $coOrgSlug])
          : NULL;
        $coOrgs[] = [
          'label' => (string) $coOrg->label(),
          'url' => $coUrl?->toString(),
        ];
      }
    }

    // Campos estruturados de acessibilidade, segurança e classificação
    // (ARQ-04).
    $acessibilidadeTexto = (string) $evento->get('field_acessibilidade')->value;
    $selosAcessibilidade = [];
    if ($acessibilidadeTexto !== '') {
      $linhasAcess = preg_split('/[\r\n;]+/', $acessibilidadeTexto);
      if ($linhasAcess !== FALSE) {
        foreach ($linhasAcess as $item) {
          $trimmed = trim($item);
          if ($trimmed !== '') {
            $selosAcessibilidade[] = $trimmed;
          }
        }
      }
    }

    $classificacao = (string) $evento->get('field_classificacao_etaria')->value;
    $seguranca = (string) $evento->get('field_seguranca')->value;
    $cacheability->setCacheMaxAge($validade);

    // Mapa contextual: se houver locais físicos autorizados com coordenadas,
    // renderiza o mapa com marcadores dos locais deste evento.
    $mapaRender = NULL;
    if ($this->mapRenderer !== NULL) {
      $marcadores = [];
      $locaisProcessados = [];
      foreach ($ocorrencias as $ocorrencia) {
        $loc = $ocorrencia['data']->get('field_local')->entity;
        if (!$loc instanceof EventLocation) {
          continue;
        }
        $locId = (string) $loc->id();
        if (isset($locaisProcessados[$locId])) {
          continue;
        }
        $locaisProcessados[$locId] = TRUE;
        $locAccess = $localViewAccess[$locId] ?? $loc->access('view', $this->currentUser(), TRUE);
        if (!$locAccess->isAllowed()) {
          continue;
        }
        $coords = $loc->get('field_coordenadas')->first()?->getValue();
        if (!is_numeric($coords['lat'] ?? NULL) || !is_numeric($coords['lon'] ?? NULL)) {
          continue;
        }
        $casas = (string) $loc->get('field_precisao')->value === 'aproximado' ? 2 : 6;
        $enderecoLoc = $this->locationCatalogue !== NULL ? $this->locationCatalogue->enderecoPublico($loc) : '';
        $locUrl = Url::fromRoute('aculta_events.location_canonical', [
          'aculta_event_location' => $loc->id(),
        ])->toString();
        $routeUrl = ($enderecoLoc !== '' && $this->routeLinks !== NULL)
          ? $this->routeLinks->build($enderecoLoc)->toString()
          : '';
        $categoriaLoc = '';
        $tipoLoc = $loc->get('field_tipo')->entity;
        if ($tipoLoc !== NULL && $tipoLoc->access('view')) {
          $categoriaLoc = (string) $tipoLoc->label();
        }
        $marcadores[] = [
          'label' => (string) $loc->label(),
          'lat' => round((float) $coords['lat'], $casas),
          'lng' => round((float) $coords['lon'], $casas),
          'description' => $enderecoLoc,
          'url' => $locUrl,
          'category' => $categoriaLoc,
          'precision' => $casas === 2 ? 'approximate' : 'exact',
          'routeUrl' => $routeUrl,
        ];
      }
      if ($marcadores !== []) {
        $mapaRender = $this->mapRenderer->build([
          'id' => 'event-page-map',
          'label' => (string) $this->t('Mapa do local do evento'),
          'markers' => $marcadores,
        ]);
      }
    }

    return [
      '#theme' => 'aculta_event_page',
      '#prefix' => $prefix !== '' ? $prefix : NULL,
      '#titulo' => (string) $evento->label(),
      '#subtitulo' => (string) $evento->get('field_subtitulo')->value,
      '#descricao' => (string) $evento->get('body')->value,
      '#tipo' => $evento->get('field_tipo')->entity?->label(),
      '#organizador' => $organizadorIdentidade?->toArray()['display_name'] ?? NULL,
      '#organizacao' => $orgData,
      '#coorganizadores' => $coOrgs,
      '#classificacao_etaria' => $classificacao !== '' ? $classificacao : NULL,
      '#selos_acessibilidade' => $selosAcessibilidade,
      '#seguranca' => $seguranca !== '' ? $seguranca : NULL,
      '#url' => (string) $evento->get('field_url')->value,
      '#datas' => $linhas,
      '#mapa' => $mapaRender,
      '#cancelado' => $evento->get('field_status')->value === 'cancelado',
      '#cache' => [
        'contexts' => $cacheability->getCacheContexts(),
        'tags' => $cacheability->getCacheTags(),
        'max-age' => $cacheability->getCacheMaxAge(),
      ],
      '#attached' => $anexos,
    ];
  }

  /**
   * Título da página, com o nome do evento.
   */
  public function titulo(string $slug): string {
    return (string) $this->eventoPorSlug($slug)->label();
  }

  /**
   * Evento publicado ou cancelado pelo slug; rascunho e inexistente são 404.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  private function eventoPorSlug(string $slug): Event {
    $eventos = $this->entities->getStorage('aculta_event')->loadByProperties(['field_slug' => $slug]);
    $evento = $eventos ? reset($eventos) : NULL;
    if (!$evento instanceof Event || !$evento->access('view')) {
      throw new NotFoundHttpException();
    }
    return $evento;
  }

}
