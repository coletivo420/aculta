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
 * Administrative queue for proposed organization profile changes.
 */
final class EventOrganizationSuggestionAdminController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
    #[Autowire(service: 'date.formatter')]
    private readonly DateFormatterInterface $dateFormatterService,
  ) {}

  /**
   * Lists pending organization suggestions for Events administrators.
   */
  public function overview(): array {
    $storage = $this->entities->getStorage('aculta_event_org_suggestion');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('field_status', 'pending')
      ->sort('created', 'ASC')
      ->pager(50)
      ->execute();

    $rows = [];
    $contributionStorage = $this->entities->getStorage('aculta_contribution');
    $contributionIds = $contributionStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('source', 'events_organization')
      ->condition('intent', ['organization_update', 'organization_create'], 'IN')
      ->condition('state', 'submitted')
      ->sort('created', 'ASC')
      ->range(0, 50)
      ->execute();
    foreach ($contributionStorage->loadMultiple($contributionIds) as $proposal) {
      if (!$proposal instanceof ContentEntityInterface || !$proposal instanceof EntityOwnerInterface) {
        continue;
      }
      $organization = NULL;
      $targetUuid = (string) $proposal->get('target_uuid')->value;
      if ($targetUuid !== '') {
        $matches = $this->entities->getStorage('aculta_event_organization')->loadByProperties(['uuid' => $targetUuid]);
        $organization = reset($matches);
      }
      $author = $this->entities->getStorage('user')->load($proposal->getOwnerId());
      $rows[] = [
        'organization' => ['data' => ['#plain_text' => $organization ? (string) $organization->label() : (string) $proposal->get('subject')->value]],
        'author' => ['data' => ['#plain_text' => $author ? $author->getDisplayName() : (string) $this->t('Conta removida')]],
        'created' => ['data' => ['#plain_text' => $this->dateFormatterService->format((int) $proposal->get('created')->value, 'short')]],
        'review' => [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Revisar proposta'),
            '#url' => Url::fromRoute('aculta_events.admin_organization_contribution_review', ['aculta_contribution' => $proposal->id()]),
          ],
        ],
      ];
    }

    foreach ($storage->loadMultiple($ids) as $suggestion) {
      if (!$suggestion instanceof ContentEntityInterface) {
        continue;
      }
      $organization = $this->entities
        ->getStorage('aculta_event_organization')
        ->load($suggestion->get('field_organization')->target_id);
      $author = $this->entities
        ->getStorage('user')
        ->load($suggestion->get('field_submitted_by')->target_id);

      $rows[] = [
        'organization' => [
          'data' => ['#plain_text' => $organization ? (string) $organization->label() : (string) $this->t('Organização removida')],
        ],
        'author' => [
          'data' => ['#plain_text' => $author ? $author->getDisplayName() : (string) $this->t('Conta removida')],
        ],
        'created' => [
          'data' => ['#plain_text' => $this->dateFormatterService->format((int) $suggestion->get('created')->value, 'short')],
        ],
        'review' => [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Revisar proposta'),
            '#url' => Url::fromRoute('aculta_events.admin_organization_suggestion_review', [
              'aculta_event_org_suggestion' => $suggestion->id(),
            ]),
          ],
        ],
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-organization-suggestion-queue'],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Organização'), $this->t('Enviada por'), $this->t('Data'), $this->t('Ação')],
        '#rows' => $rows,
        '#empty' => $this->t('Não há propostas pendentes de organizações.'),
      ],
      'pager' => ['#type' => 'pager'],
    ];

    (new CacheableMetadata())
      ->addCacheContexts(['user.permissions'])
      ->addCacheTags(['aculta_event_org_suggestion_list', 'aculta_event_organization_list', 'aculta_contribution_list'])
      ->applyTo($build);

    return $build;
  }

}
