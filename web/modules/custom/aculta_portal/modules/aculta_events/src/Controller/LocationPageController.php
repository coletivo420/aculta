<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_calendar\Agenda\CalendarAgenda;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\aculta_events\Calendar\EventCalendarItemProvider;
use Drupal\aculta_events\Catalogue\LocationCatalogue;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\address\AddressInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Página canônica individual de um Local com agenda de eventos (LOC-05).
 */
final class LocationPageController extends ControllerBase {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly LocationCatalogue $catalogue,
    private readonly CalendarAgenda $agenda,
    private readonly EventCalendarItemProvider $calendarProvider,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_events.locations'),
      $container->get('aculta_calendar.agenda'),
      $container->get('aculta_events.item_provider'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Título da página do local.
   */
  public function title(EventLocation $aculta_event_location): string {
    return (string) $aculta_event_location->label();
  }

  /**
   * Renderiza a página pública do local.
   */
  public function page(EventLocation $aculta_event_location): array {
    $viewAccess = $aculta_event_location->access('view', $this->currentUser(), TRUE);
    if (!$viewAccess->isAllowed()) {
      throw new NotFoundHttpException();
    }
    $cacheability = CacheableMetadata::createFromObject($aculta_event_location)
      ->merge(CacheableMetadata::createFromObject($viewAccess));

    $endereco = $this->catalogue->enderecoPublico($aculta_event_location);
    $tipoLabel = '';
    if (!$aculta_event_location->get('field_tipo')->isEmpty()) {
      $term = $aculta_event_location->get('field_tipo')->entity;
      if ($term !== NULL) {
        $tipoLabel = (string) $term->label();
      }
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-location-page'],
      '#cache' => [
        'tags' => $cacheability->getCacheTags(),
        'contexts' => $cacheability->getCacheContexts(),
        'max-age' => $cacheability->getCacheMaxAge(),
      ],
    ];

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-location-header'],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => Html::escape((string) $aculta_event_location->label()),
      ],
    ];

    if ($tipoLabel !== '') {
      $build['header']['type'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => Html::escape($tipoLabel),
        '#attributes' => ['data-portal-ui' => 'event-location-type-badge'],
      ];
    }

    if ($aculta_event_location->access('update', $this->currentUser())) {
      $build['header']['actions'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'event-location-actions'],
        'edit_link' => [
          '#type' => 'aculta_portal_button',
          '#props' => [
            'text' => (string) $this->t('Editar este local'),
            'url' => Url::fromRoute('aculta_events.location_edit', [
              'aculta_event_location' => $aculta_event_location->id(),
            ])->toString(),
            'variant' => 'secondary',
            'size' => 'small',
          ],
        ],
      ];
    }

    $details = [];
    if ($endereco !== '') {
      $details['endereco'] = [
        '#type' => 'item',
        '#title' => $this->t('Endereço'),
        '#markup' => '<p class="location-address">' . Html::escape($endereco) . '</p>',
      ];
    }

    $horario = (string) ($aculta_event_location->get('field_horario')->value ?? '');
    if ($horario !== '') {
      $details['horario'] = [
        '#type' => 'item',
        '#title' => $this->t('Horário de funcionamento'),
        '#markup' => '<p class="location-hours">' . nl2br(Html::escape($horario)) . '</p>',
      ];
    }

    $descricao = (string) ($aculta_event_location->get('field_descricao')->value ?? '');
    if ($descricao !== '') {
      $details['descricao'] = [
        '#type' => 'item',
        '#title' => $this->t('Sobre o local'),
        '#markup' => '<p class="location-description">' . nl2br(Html::escape($descricao)) . '</p>',
      ];
    }

    $telefone = (string) ($aculta_event_location->get('field_telefone')->value ?? '');
    if ($telefone !== '') {
      $details['telefone'] = [
        '#type' => 'item',
        '#title' => $this->t('Telefone'),
        '#markup' => '<p class="location-phone">' . Html::escape($telefone) . '</p>',
      ];
    }

    $site = self::safeSiteUrl((string) ($aculta_event_location->get('field_site')->value ?? ''));
    if ($site !== NULL) {
      $details['site'] = [
        '#type' => 'item',
        '#title' => $this->t('Site oficial'),
        '#markup' => '<p class="location-site"><a href="' . Html::escape($site) . '" target="_blank" rel="noopener noreferrer">' . Html::escape($site) . '</a></p>',
      ];
    }

    $build['details'] = $details;

    // Ações de gerenciamento se o usuário tiver permissão de edição.
    if ($aculta_event_location->access('update', $this->currentUser())) {
      $build['actions'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'event-location-admin-actions'],
        'edit' => [
          '#type' => 'aculta_portal_button',
          '#props' => [
            'text' => (string) $this->t('Editar local'),
            'url' => Url::fromRoute('aculta_events.location_edit', ['aculta_event_location' => $aculta_event_location->id()])->toString(),
            'variant' => 'primary',
          ],
        ],
      ];
    }

    // Agenda de eventos neste local (LOC-05).
    $agenda = $this->buildAgenda($aculta_event_location);
    if (!empty($agenda)) {
      $build['agenda'] = $agenda;
    }

    // Dados estruturados schema.org (Place).
    $placeLd = [
      '@context' => 'https://schema.org',
      '@type' => 'Place',
      'name' => (string) $aculta_event_location->label(),
      'url' => Url::fromRoute('aculta_events.location_canonical', [
        'aculta_event_location' => $aculta_event_location->id(),
      ], ['absolute' => TRUE])->toString(),
    ];

    if ($descricao !== '') {
      $placeLd['description'] = mb_substr(trim(strip_tags($descricao)), 0, 500);
    }

    if ($telefone !== '') {
      $placeLd['telephone'] = $telefone;
    }

    if ($site !== '') {
      $placeLd['sameAs'] = $site;
    }

    if ($horario !== '') {
      $placeLd['openingHours'] = $horario;
    }

    $addressItem = $aculta_event_location->get('field_endereco')->first();
    if ($addressItem instanceof AddressInterface) {
      $locality = (string) $addressItem->getLocality();
      $adminArea = (string) $addressItem->getAdministrativeArea();
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
        $precisao = (string) ($aculta_event_location->get('field_precisao')->value ?? 'exato');
        if ($precisao !== 'aproximado') {
          $street = (string) $addressItem->getAddressLine1();
          $postalCode = (string) $addressItem->getPostalCode();
          if ($street !== '') {
            $postalAddress['streetAddress'] = $street;
          }
          if ($postalCode !== '') {
            $postalAddress['postalCode'] = $postalCode;
          }
        }
        $placeLd['address'] = $postalAddress;
      }
    }

    $precisao = (string) ($aculta_event_location->get('field_precisao')->value ?? 'exato');
    if ($precisao !== 'aproximado' && !$aculta_event_location->get('field_coordenadas')->isEmpty()) {
      $lat = $aculta_event_location->get('field_coordenadas')->lat;
      $lon = $aculta_event_location->get('field_coordenadas')->lon;
      if ($lat !== NULL && $lon !== NULL) {
        $placeLd['geo'] = [
          '@type' => 'GeoCoordinates',
          'latitude' => (float) $lat,
          'longitude' => (float) $lon,
        ];
      }
    }

    $jsonLd = json_encode($placeLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $json = str_replace('</', '<\\/', (string) $jsonLd);
    $build['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#attributes' => ['type' => 'application/ld+json'],
        '#value' => $json,
      ],
      'aculta_events_location_jsonld',
    ];

    return $build;
  }

  /**
   * Returns a site URL safe for public HTML and structured data.
   */
  private static function safeSiteUrl(string $value): ?string {
    $value = trim($value);
    $scheme = parse_url($value, PHP_URL_SCHEME);
    if ($value === '' || !is_string($scheme)
      || !in_array(strtolower($scheme), ['http', 'https'], TRUE)
      || preg_match('/[\x00-\x20<\x22\x3e\x7f]/', $value) === 1
      || filter_var($value, FILTER_VALIDATE_URL) === FALSE) {
      return NULL;
    }
    return $value;
  }

  /**
   * Constrói a agenda de datas e eventos associados a este local.
   */
  private function buildAgenda(EventLocation $location): array {
    $timezone = new \DateTimeZone('America/Sao_Paulo');
    $now = (new \DateTimeImmutable('@' . $this->time->getCurrentTime()))->setTimezone($timezone);
    $from = $now->setTime(0, 0);
    $to = $from->modify('+30 days')->setTime(23, 59, 59);
    $query = new CalendarQuery(
      $from,
      $to,
      $now,
      ['local' => (int) $location->id()],
      ['aculta_events'],
    );
    $days = $this->agenda->dias($query);
    $agenda = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-location-agenda'],
      '#cache' => [
        'tags' => $this->calendarProvider->cacheTags(),
        'contexts' => $this->calendarProvider->cacheContexts(),
        'max-age' => $this->calendarProvider->cacheMaxAge(),
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => (string) $this->t('Agenda neste local'),
      ],
      'ics' => [
        '#type' => 'link',
        '#title' => $this->t('Baixar agenda deste local (ICS)'),
        '#url' => Url::fromRoute('aculta_events.location_ics', [
          'aculta_event_location' => $location->id(),
        ]),
      ],
    ];
    if ($days === []) {
      $agenda['empty'] = [
        '#markup' => '<p class="location-agenda-empty">' . Html::escape((string) $this->t('Nenhum evento agendado neste local nos próximos 30 dias.')) . '</p>',
      ];
      return $agenda;
    }

    foreach ($days as $day => $occurrences) {
      $dayItems = [];
      foreach ($occurrences as $occurrence) {
        $time = $occurrence['diaInteiro']
          ? (string) $this->t('Dia inteiro')
          : $occurrence['inicio']->format('H:i') . ' às ' . $occurrence['fim']->format('H:i');
        $title = $occurrence['link'] !== NULL
          ? Link::fromTextAndUrl($occurrence['titulo'], Url::fromUserInput($occurrence['link']))->toString()
          : Html::escape($occurrence['titulo']);
        $line = '<time datetime="' . Html::escape($occurrence['inicio']->format(DATE_ATOM)) . '">'
          . Html::escape($time) . '</time> — <strong>' . $title . '</strong>';
        if ($occurrence['local'] !== '') {
          $line .= ' · ' . Html::escape($occurrence['local']);
        }
        if ($occurrence['cancelada']) {
          $line .= ' <span class="event-cancelled">(' . Html::escape((string) $this->t('cancelado')) . ')</span>';
        }
        $dayItems[] = ['#markup' => $line];
      }
      $dayDate = new \DateTimeImmutable($day, $timezone);
      $agenda['days'][$day] = [
        '#type' => 'container',
        '#attributes' => ['data-event-date' => $day],
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $dayDate->format('d/m/Y'),
        ],
        'items' => [
          '#theme' => 'item_list',
          '#items' => $dayItems,
        ],
      ];
    }
    return $agenda;
  }

}
