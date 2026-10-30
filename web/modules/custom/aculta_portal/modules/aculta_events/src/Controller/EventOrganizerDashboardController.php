<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Access\EventPermissionPolicy;
use Drupal\aculta_events\Entity\Event;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Organizer-facing Event tools, scoped to entities the current user can edit.
 */
final class EventOrganizerDashboardController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EventPermissionPolicy $permissions,
    private readonly ?RequestStack $requestStack = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_events.permission_policy'),
      $container->get('request_stack'),
    );
  }

  /**
   * Lists editable events and exposes only owner-authorized create actions.
   *
   * @return array<string, mixed>
   *   A cacheable organizer work list.
   */
  public function page(): array {
    $account = $this->currentUser();
    $request = $this->requestStack?->getCurrentRequest();

    // Filters from query parameters.
    $busca = trim((string) ($request?->query->get('busca', '') ?? ''));
    $statusFilter = trim((string) ($request?->query->get('status', 'todos') ?? 'todos'));

    // Request cacheable AccessResult objects.
    $canCreateEvent = $this->entities->getAccessControlHandler('aculta_event')->createAccess(NULL, $account, [], TRUE);
    $canCreateLocation = $this->entities->getAccessControlHandler('aculta_event_location')->createAccess(NULL, $account, [], TRUE);

    $items = [];
    if ($canCreateEvent->isAllowed()) {
      $items[] = [
        '#type' => 'link',
        '#title' => $this->t('Criar evento'),
        '#url' => Url::fromRoute('aculta_events.organizer_event_add'),
        '#attributes' => ['data-portal-ui' => 'button-primary'],
      ];
    }
    if ($canCreateLocation->isAllowed()) {
      $items[] = [
        '#type' => 'link',
        '#title' => $this->t('Cadastrar local'),
        '#url' => Url::fromRoute('aculta_events.organizer_location_add'),
        '#attributes' => ['data-portal-ui' => 'button-secondary'],
      ];
    }
    if ($canCreateEvent->isAllowed()) {
      $items[] = [
        '#type' => 'link',
        '#title' => $this->t('Cadastrar organização'),
        '#url' => Url::fromRoute('aculta_events.organizer_organization_add'),
        '#attributes' => ['data-portal-ui' => 'button-secondary'],
      ];
    }

    $storage = $this->entities->getStorage('aculta_event');
    $query = $storage->getQuery()->accessCheck(FALSE)->sort('id', 'DESC');
    if (!$account->hasPermission('administer aculta events')) {
      $query->condition('field_organizador', (int) $account->id());
    }
    if ($busca !== '') {
      $query->condition('title', '%' . $busca . '%', 'LIKE');
    }
    if ($statusFilter !== 'todos' && in_array($statusFilter, ['publicado', 'rascunho', 'cancelado'], TRUE)) {
      $query->condition('field_status', $statusFilter);
    }

    $query->pager(20);
    $ids = $query->execute();

    $cache = (new CacheableMetadata())
      ->addCacheContexts(['user', 'user.permissions', 'user.roles', 'url.query_args'])
      ->addCacheTags([
        'aculta_event_list',
        'aculta_event_date_list',
        'aculta_event_location_list',
        'aculta_event_organization_list',
        'config:aculta_events.settings',
      ])
      ->addCacheableDependency($canCreateEvent)
      ->addCacheableDependency($canCreateLocation);

    /** @var array<int, \Drupal\aculta_events\Entity\Event> $events */
    $events = [];
    foreach ($storage->loadMultiple($ids) as $event) {
      if (!$event instanceof Event) {
        continue;
      }
      $access = $event->access('update', $account, TRUE);
      $cache->addCacheableDependency($event)->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      $events[(int) $event->id()] = $event;
    }

    // Resolve next dates and summary location for loaded events.
    $nextDates = [];
    $locationIds = [];
    if ($events !== []) {
      $dateStorage = $this->entities->getStorage('aculta_event_date');
      $dateQuery = $dateStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('field_evento', array_keys($events), 'IN')
        ->sort('field_quando.value', 'ASC');
      $dateIds = $dateQuery->execute();
      if ($dateIds !== []) {
        foreach ($dateStorage->loadMultiple($dateIds) as $date) {
          $eventId = (int) ($date->get('field_evento')->target_id ?? 0);
          if ($eventId > 0 && !isset($nextDates[$eventId])) {
            $nextDates[$eventId] = $date;
            $locId = (int) ($date->get('field_local')->target_id ?? 0);
            if ($locId > 0) {
              $locationIds[$locId] = $locId;
            }
          }
        }
      }
    }

    $locations = [];
    if ($locationIds !== []) {
      $locations = $this->entities->getStorage('aculta_event_location')->loadMultiple($locationIds);
    }

    $rows = [];
    $statusMap = [
      'publicado' => $this->t('Publicado'),
      'rascunho' => $this->t('Rascunho'),
      'cancelado' => $this->t('Cancelado'),
    ];

    foreach ($events as $eventId => $event) {
      $dateText = (string) $this->t('Sem datas agendadas');
      $locText = (string) $this->t('Não definido');

      if (isset($nextDates[$eventId])) {
        $date = $nextDates[$eventId];
        $quando = $date->get('field_quando')->first();
        if ($quando && $quando->start_date) {
          $dateText = $quando->start_date->format('d/m/Y H:i');
        }
        elseif ($quando && !empty($quando->value)) {
          $dateText = substr((string) $quando->value, 0, 16);
        }
        else {
          $dateText = (string) $date->label();
        }

        $locId = (int) ($date->get('field_local')->target_id ?? 0);
        if ($locId > 0 && isset($locations[$locId])) {
          $loc = $locations[$locId];
          $locTitle = (string) $loc->label();
          $cityUf = '';
          if ($loc->hasField('field_endereco') && !$loc->get('field_endereco')->isEmpty()) {
            $addr = $loc->get('field_endereco')->first();
            if ($addr) {
              $parts = array_filter([(string) $addr->locality, (string) $addr->administrative_area]);
              if ($parts !== []) {
                $cityUf = ' (' . implode('/', $parts) . ')';
              }
            }
          }
          $locText = $locTitle . $cityUf;
        }
        elseif (($date->get('field_modo')->value ?? '') === 'online') {
          $locText = (string) $this->t('On-line / Virtual');
        }
      }

      $rawStatus = (string) ($event->get('field_status')->value ?? '');
      $statusLabel = $statusMap[$rawStatus] ?? ($rawStatus !== '' ? ucfirst($rawStatus) : (string) $this->t('Indefinido'));

      $rows[] = [
        ['data' => $event->label()],
        ['data' => $dateText],
        ['data' => $locText],
        ['data' => $statusLabel],
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Gerenciar'),
            '#url' => Url::fromRoute('aculta_events.organizer_event_edit', ['aculta_event' => $event->id()]),
          ],
        ],
      ];
    }

    $hasFilters = $busca !== '' || ($statusFilter !== 'todos' && $statusFilter !== '');

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-organizer-dashboard'],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Crie e gerencie seus eventos, datas e locais. A edição usa as permissões e o acesso às entidades já definidos em Eventos.'),
      ],
      'guide' => [
        '#type' => 'details',
        '#title' => $this->t('Como organizar eventos no ACULTA Studio Culturas (Guia rápido)'),
        '#open' => empty($rows) && !$hasFilters,
        '#attributes' => [
          'data-portal-ui' => 'event-organizer-guide',
        ],
        'steps' => [
          '#theme' => 'item_list',
          '#items' => [
            [
              '#markup' => '<strong>' . $this->t('Passo 1: Crie o evento.') . '</strong> ' . $this->t('Informe o título, descrição, classificação indicativa e recursos de acessibilidade.'),
            ],
            [
              '#markup' => '<strong>' . $this->t('Passo 2: Adicione datas e sessões.') . '</strong> ' . $this->t('Defina horários, modalidade (presencial ou on-line) e informações de ingresso (gratuito ou link externo).'),
            ],
            [
              '#markup' => '<strong>' . $this->t('Passo 3: Selecione ou cadastre o local.') . '</strong> ' . $this->t('Busque por nome ou município, ou cadastre um local novo pelo CEP brasileiro com controle de precisão.'),
            ],
          ],
        ],
      ],
      'actions' => ['#type' => 'actions', 'items' => $items],
      'filters' => [
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => 'event-organizer-filters',
        ],
        'form' => [
          '#type' => 'html_tag',
          '#tag' => 'form',
          '#attributes' => [
            'method' => 'get',
            'action' => Url::fromRoute('aculta_events.organizer_dashboard')->toString(),
            'data-portal-ui' => 'event-organizer-filter-form',
          ],
          'busca' => [
            '#type' => 'textfield',
            '#title' => $this->t('Buscar por nome'),
            '#name' => 'busca',
            '#default_value' => $busca,
            '#attributes' => [
              'placeholder' => $this->t('Nome do evento...'),
              'size' => 30,
            ],
          ],
          'status' => [
            '#type' => 'select',
            '#title' => $this->t('Situação'),
            '#name' => 'status',
            '#options' => [
              'todos' => $this->t('Todas as situações'),
              'publicado' => $this->t('Publicado'),
              'rascunho' => $this->t('Rascunho'),
              'cancelado' => $this->t('Cancelado'),
            ],
            '#default_value' => $statusFilter,
          ],
          'submit' => [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => $this->t('Filtrar'),
            '#attributes' => [
              'type' => 'submit',
              'data-portal-ui' => 'button-secondary',
            ],
          ],
        ],
      ],
      'events' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Evento'),
          $this->t('Próxima data'),
          $this->t('Local'),
          $this->t('Situação'),
          $this->t('Ações'),
        ],
        '#rows' => $rows,
        '#empty' => $hasFilters
          ? $this->t('Nenhum evento encontrado para os filtros selecionados.')
          : $this->t('Você ainda não tem eventos para gerenciar.'),
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];

    if ($hasFilters) {
      $build['filters']['form']['reset'] = [
        '#type' => 'link',
        '#title' => $this->t('Limpar filtros'),
        '#url' => Url::fromRoute('aculta_events.organizer_dashboard'),
        '#attributes' => ['data-portal-ui' => 'button-ghost'],
      ];
    }

    $cache->applyTo($build);
    return $build;
  }

}
