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

/**
 * Organizer-facing Event tools, scoped to entities the current user can edit.
 */
final class EventOrganizerDashboardController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EventPermissionPolicy $permissions,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_events.permission_policy'),
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
    // Request the cacheable AccessResult object in the fourth parameter; the
    // default return value is a boolean and cannot contribute cache metadata.
    $canCreate = $this->entities->getAccessControlHandler('aculta_event')->createAccess(NULL, $account, [], TRUE);
    $items = [];
    if ($canCreate->isAllowed()) {
      $items[] = [
        '#type' => 'link',
        '#title' => $this->t('Criar evento'),
        '#url' => Url::fromRoute('aculta_events.organizer_event_add'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    }

    $storage = $this->entities->getStorage('aculta_event');
    $query = $storage->getQuery()->accessCheck(FALSE)->sort('id', 'DESC')->range(0, 250);
    if (!$account->hasPermission('administer aculta events')) {
      $query->condition('field_organizador', (int) $account->id());
    }
    $ids = $query->execute();
    $rows = [];
    $cache = (new CacheableMetadata())
      ->addCacheContexts(['user', 'user.permissions', 'user.roles'])
      ->addCacheTags(['aculta_event_list', 'config:aculta_events.settings'])
      ->addCacheableDependency($canCreate);
    foreach ($storage->loadMultiple($ids) as $event) {
      if (!$event instanceof Event) {
        continue;
      }
      $access = $event->access('update', $account, TRUE);
      $cache->addCacheableDependency($event)->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      $rows[] = [
        ['data' => $event->label()],
        ['data' => (string) ($event->get('field_status')->value ?? '')],
        ['data' => [
          '#type' => 'link',
          '#title' => $this->t('Gerenciar'),
          '#url' => Url::fromRoute('aculta_events.organizer_event_edit', ['aculta_event' => $event->id()]),
        ]],
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-organizer-dashboard'],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Crie e gerencie seus eventos, datas e locais. A edição usa as permissões e o acesso às entidades já definidos em Eventos.'),
      ],
      'actions' => ['#type' => 'actions', 'items' => $items],
      'events' => [
        '#type' => 'table',
        '#header' => [$this->t('Evento'), $this->t('Situação'), $this->t('Ações')],
        '#rows' => $rows,
        '#empty' => $this->t('Você ainda não tem eventos para gerenciar.'),
      ],
    ];
    $cache->applyTo($build);
    return $build;
  }

}
