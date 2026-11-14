<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_cgu\Entity\Contribution;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Administrative collection for private Location creation and edits. */
final class EventLocationContributionAdminController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
    #[Autowire(service: 'date.formatter')]
    private readonly DateFormatterInterface $dateFormatterService,
  ) {}

  /**
   * Lists submitted Location proposals without copying their payload. */
  public function overview(): array {
    $storage = $this->entities->getStorage('aculta_contribution');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('source', 'events_location')
      ->condition('intent', ['location_create', 'location_update'], 'IN')
      ->condition('state', 'submitted')
      ->sort('created', 'ASC')
      ->pager(50)
      ->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $proposal) {
      if (!$proposal instanceof Contribution) {
        continue;
      }
      if (!$proposal->access('view', $this->currentUser())) {
        continue;
      }
      $target = NULL;
      $uuid = (string) $proposal->get('target_uuid')->value;
      if ($uuid !== '') {
        $matches = $this->entities->getStorage('aculta_event_location')->loadByProperties(['uuid' => $uuid]);
        $candidate = reset($matches);
        $target = $candidate instanceof EventLocation ? $candidate : NULL;
      }
      $author = $this->entities->getStorage('user')->load($proposal->getOwnerId());
      $rows[] = [
        'location' => ['data' => ['#plain_text' => $target ? (string) $target->label() : (string) $proposal->get('subject')->value]],
        'kind' => ['data' => ['#plain_text' => $proposal->get('intent')->value === 'location_create' ? $this->t('Criação') : $this->t('Alteração')]],
        'author' => ['data' => ['#plain_text' => $author ? $author->getDisplayName() : $this->t('Conta removida')]],
        'created' => ['data' => ['#plain_text' => $this->dateFormatterService->format((int) $proposal->get('created')->value, 'short')]],
        'review' => [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Revisar proposta'),
            '#url' => Url::fromRoute('aculta_events.admin_location_contribution_review', ['aculta_contribution' => $proposal->id()]),
          ],
        ],
      ];
    }
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-location-contribution-queue'],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Local'), $this->t('Tipo'), $this->t('Enviada por'), $this->t('Data'), $this->t('Ação')],
        '#rows' => $rows,
        '#empty' => $this->t('Não há propostas pendentes de Locais.'),
      ],
      'pager' => ['#type' => 'pager'],
    ];
    (new CacheableMetadata())
      ->addCacheContexts(['user.permissions'])
      ->addCacheTags(['aculta_contribution_list', 'aculta_event_location_list'])
      ->applyTo($build);
    return $build;
  }

}
