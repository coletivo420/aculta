<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Catalogue\LocationCatalogue;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
    EntityTypeManagerInterface $entityTypeManager,
    private readonly LocationCatalogue $catalogue,
    private readonly DateFormatterInterface $dateFormatter,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_events.locations'),
      $container->get('date.formatter'),
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
    if (!$aculta_event_location->access('view', $this->currentUser())) {
      throw new NotFoundHttpException();
    }

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
      '#attributes' => ['class' => ['aculta-location-page']],
      '#cache' => [
        'tags' => $aculta_event_location->getCacheTags(),
        'contexts' => ['user.permissions'],
      ],
    ];

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['aculta-location-header']],
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
        '#attributes' => ['class' => ['badge', 'badge-location-type']],
      ];
    }

    if ($aculta_event_location->access('update', $this->currentUser())) {
      $build['header']['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['location-actions', 'mt-2', 'mb-3']],
        'edit_link' => [
          '#type' => 'link',
          '#title' => $this->t('Editar este local'),
          '#url' => Url::fromRoute('aculta_events.location_edit', [
            'aculta_event_location' => $aculta_event_location->id(),
          ]),
          '#attributes' => ['class' => ['button', 'button--secondary', 'button--small']],
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

    $site = (string) ($aculta_event_location->get('field_site')->value ?? '');
    if ($site !== '') {
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
        '#attributes' => ['class' => ['location-admin-actions']],
        'edit' => [
          '#type' => 'link',
          '#title' => $this->t('Editar local'),
          '#url' => Url::fromRoute('aculta_events.location_edit', ['aculta_event_location' => $aculta_event_location->id()]),
          '#attributes' => ['class' => ['button', 'button--action']],
        ],
      ];
    }

    // Agenda de eventos neste local (LOC-05).
    $agenda = $this->buildAgenda($aculta_event_location);
    if (!empty($agenda)) {
      $build['agenda'] = $agenda;
    }

    return $build;
  }

  /**
   * Constrói a agenda de datas e eventos associados a este local.
   */
  private function buildAgenda(EventLocation $location): array {
    $dateStorage = $this->entityTypeManager->getStorage('aculta_event_date');
    $dateIds = $dateStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_local', (int) $location->id())
      ->sort('field_quando.value', 'ASC')
      ->execute();

    if (empty($dateIds)) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['location-agenda']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => (string) $this->t('Agenda neste local'),
        ],
        'empty' => [
          '#markup' => '<p class="location-agenda-empty">' . $this->t('Nenhum evento agendado neste local no momento.') . '</p>',
        ],
      ];
    }

    $dates = $dateStorage->loadMultiple($dateIds);
    $items = [];
    $eventStorage = $this->entityTypeManager->getStorage('aculta_event');

    foreach ($dates as $date) {
      $eventId = (int) ($date->get('field_evento')->target_id ?? 0);
      if ($eventId <= 0) {
        continue;
      }
      /** @var \Drupal\aculta_events\Entity\Event|null $event */
      $event = $eventStorage->load($eventId);
      if (!$event instanceof Event || !$event->access('view', $this->currentUser())) {
        continue;
      }

      $quando = $date->get('field_quando')->first();
      $startFormatted = '';
      if ($quando !== NULL && !empty($quando->value)) {
        $timestamp = strtotime((string) $quando->value);
        if ($timestamp !== FALSE) {
          $startFormatted = $this->dateFormatter->format($timestamp, 'medium');
        }
      }

      $slug = (string) ($event->get('field_slug')->value ?? '');
      $eventUrl = $slug !== ''
        ? Url::fromRoute('aculta_events.page', ['slug' => $slug])
        : Url::fromRoute('aculta_events.catalogue');

      $line = '<strong>' . Link::fromTextAndUrl((string) $event->label(), $eventUrl)->toString() . '</strong>';
      $dateTitle = (string) $date->label();
      if ($dateTitle !== '' && $dateTitle !== (string) $event->label()) {
        $line .= ' — ' . Html::escape($dateTitle);
      }
      if ($startFormatted !== '') {
        $line .= '<br><span class="event-date-time">' . Html::escape($startFormatted) . '</span>';
      }

      $isFree = (bool) ($date->get('field_gratuito')->value ?? FALSE);
      if ($isFree) {
        $line .= ' <span class="badge badge-free">' . Html::escape((string) $this->t('Gratuito')) . '</span>';
      }

      $items[] = ['#markup' => $line];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['location-agenda']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => (string) $this->t('Agenda neste local'),
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#empty' => $this->t('Nenhum evento com acesso público disponível.'),
      ],
    ];
  }

}
