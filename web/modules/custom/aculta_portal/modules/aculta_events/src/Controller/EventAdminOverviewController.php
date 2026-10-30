<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Constrói a página de visão geral administrativa de Eventos (ADM-EVT).
 */
final class EventAdminOverviewController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /**
   * Renderiza a visão geral de eventos, datas e locais de eventos.
   */
  public function overview(): array {
    $eventCount = 0;
    $dateCount = 0;
    $locationCount = 0;

    if ($this->entities->hasDefinition('aculta_event')) {
      $eventCount = (int) $this->entities->getStorage('aculta_event')->getQuery()
        ->accessCheck(TRUE)
        ->count()
        ->execute();
    }

    if ($this->entities->hasDefinition('aculta_event_date')) {
      $dateCount = (int) $this->entities->getStorage('aculta_event_date')->getQuery()
        ->accessCheck(TRUE)
        ->count()
        ->execute();
    }

    if ($this->entities->hasDefinition('aculta_event_location')) {
      $locationCount = (int) $this->entities->getStorage('aculta_event_location')->getQuery()
        ->accessCheck(TRUE)
        ->count()
        ->execute();
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'events-admin-overview'],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => (string) $this->t('Gestão centralizada de eventos culturais, sessões/datas e locais do ACULTA.'),
      ],
      'metrics' => [
        '#type' => 'table',
        '#caption' => $this->t('Resumo do acervo de eventos'),
        '#header' => [$this->t('Item'), $this->t('Quantidade')],
        '#rows' => [
          [$this->t('Eventos estruturados'), (string) $eventCount],
          [$this->t('Datas e sessões agendadas'), (string) $dateCount],
          [$this->t('Locais de eventos'), (string) $locationCount],
        ],
      ],
      'quick_actions' => [
        '#theme' => 'item_list',
        '#title' => $this->t('Ações e seções de gestão'),
        '#items' => [
          [
            '#type' => 'link',
            '#title' => $this->t('Cadastrar novo evento'),
            '#url' => Url::fromRoute('aculta_events.event_add'),
          ],
          [
            '#type' => 'link',
            '#title' => $this->t('Adicionar data para evento existente'),
            '#url' => Url::fromRoute('aculta_events.date_add'),
          ],
          [
            '#type' => 'link',
            '#title' => $this->t('Cadastrar novo local'),
            '#url' => Url::fromRoute('aculta_events.location_add'),
          ],
          [
            '#type' => 'link',
            '#title' => $this->t('Gerenciar locais de eventos'),
            '#url' => Url::fromRoute('aculta_events.locations'),
          ],
          [
            '#type' => 'link',
            '#title' => $this->t('Exportar eventos (CSV)'),
            '#url' => Url::fromRoute('aculta_events.admin_export'),
          ],
          [
            '#type' => 'link',
            '#title' => $this->t('Catálogo público de eventos'),
            '#url' => Url::fromRoute('aculta_events.catalogue'),
          ],
          [
            '#type' => 'link',
            '#title' => $this->t('Mapa de locais'),
            '#url' => Url::fromRoute('aculta_events.map'),
          ],
        ],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => [
          'aculta_event_list',
          'aculta_event_date_list',
          'aculta_event_location_list',
        ],
      ],
    ];

    CacheableMetadata::createFromRenderArray($build)->applyTo($build);

    return $build;
  }

}
