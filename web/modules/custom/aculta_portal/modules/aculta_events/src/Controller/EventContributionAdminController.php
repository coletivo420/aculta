<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lists submitted Event contributions for native Events review. */
final class EventContributionAdminController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
    #[Autowire(service: 'date.formatter')]
    private readonly DateFormatterInterface $dateFormatterService,
  ) {}

  /**
   * Lists pending Event proposals without exposing their private payload. */
  public function overview(): array {
    $storage = $this->entities->getStorage('aculta_contribution');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('source', 'events_event')
      ->condition('intent', ['event_create', 'event_update'], 'IN')
      ->condition('state', 'submitted')
      ->sort('created', 'ASC')
      ->pager(50)
      ->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $proposal) {
      if (!$proposal instanceof ContentEntityInterface || !$proposal instanceof EntityOwnerInterface) {
        continue;
      }
      if (!$proposal->access('view', $this->currentUser())) {
        continue;
      }
      $author = $this->entities->getStorage('user')->load($proposal->getOwnerId());
      $rows[] = [
        'event' => ['data' => ['#plain_text' => (string) $proposal->get('subject')->value]],
        'kind' => ['data' => ['#plain_text' => $proposal->get('intent')->value === 'event_create' ? $this->t('Criação') : $this->t('Alteração')]],
        'author' => ['data' => ['#plain_text' => $author ? $author->getDisplayName() : $this->t('Conta removida')]],
        'created' => ['data' => ['#plain_text' => $this->dateFormatterService->format((int) $proposal->get('created')->value, 'short')]],
        'review' => [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Revisar proposta'),
            '#url' => Url::fromRoute('aculta_events.admin_event_contribution_review', ['aculta_contribution' => $proposal->id()]),
          ],
        ],
      ];
    }
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-contribution-queue'],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Evento'), $this->t('Tipo'), $this->t('Enviada por'), $this->t('Data'), $this->t('Ação')],
        '#rows' => $rows,
        '#empty' => $this->t('Não há propostas pendentes de Eventos.'),
      ],
      'pager' => ['#type' => 'pager'],
    ];
    (new CacheableMetadata())
      ->addCacheContexts(['user.permissions'])
      ->addCacheTags(['aculta_contribution_list', 'aculta_event_list'])
      ->applyTo($build);
    return $build;
  }

}
