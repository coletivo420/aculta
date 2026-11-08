<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_contributions\Entity\Contribution;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shows an author's private Events-domain proposals and editorial outcomes. */
final class EventContributionWorkspaceController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
    #[Autowire(service: 'date.formatter')]
    private readonly DateFormatterInterface $dateFormatterService,
  ) {}

  /**
   * Lists only proposals owned by the current account. */
  public function overview(): array {
    $account = $this->currentUser();
    $storage = $this->entities->getStorage('aculta_contribution');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('source', ['events_organization', 'events_location', 'events_event'], 'IN')
      ->condition('uid', (int) $account->id())
      ->sort('created', 'DESC')
      ->pager(30)
      ->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $proposal) {
      if (!$proposal instanceof Contribution || !$proposal->access('view', $account)) {
        continue;
      }
      $rows[] = [
        'subject' => [
          'data' => [
            '#type' => 'link',
            '#title' => (string) $proposal->get('subject')->value,
            '#url' => Url::fromRoute('aculta_events.organizer_contribution_status', [
              'proposal_uuid' => $proposal->uuid(),
            ]),
          ],
        ],
        'intent' => ['data' => ['#plain_text' => $this->intentLabel((string) $proposal->get('intent')->value)]],
        'state' => ['data' => ['#plain_text' => $this->stateLabel((string) $proposal->get('state')->value)]],
        'updated' => ['data' => ['#plain_text' => $this->dateFormatterService->format((int) $proposal->getRevisionCreationTime(), 'short')]],
      ];
    }
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-contribution-workspace'],
      'intro' => ['#plain_text' => $this->t('Acompanhe as propostas de Evento que você enviou e os retornos da revisão.')],
      'table' => [
        '#prefix' => '<div class="table-responsive">',
        '#suffix' => '</div>',
        '#type' => 'table',
        '#header' => [$this->t('Proposta'), $this->t('Tipo'), $this->t('Situação'), $this->t('Atualizada')],
        '#rows' => $rows,
        '#empty' => $this->t('Você ainda não enviou propostas de Evento.'),
      ],
      'pager' => ['#type' => 'pager'],
    ];
    (new CacheableMetadata())
      ->addCacheContexts(['user', 'user.permissions', 'languages:language_interface'])
      ->addCacheTags(['aculta_contribution_list'])
      ->applyTo($build);
    return $build;
  }

  /**
   * Displays a proposal only to its active author. */
  public function view(string $proposal_uuid): array {
    $account = $this->currentUser();
    $storage = $this->entities->getStorage('aculta_contribution');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('uuid', $proposal_uuid)
      ->condition('source', ['events_organization', 'events_location', 'events_event'], 'IN')
      ->condition('uid', (int) $account->id())
      ->range(0, 1)
      ->execute();
    $proposal = $ids !== [] ? $storage->load(reset($ids)) : NULL;
    $access = $proposal instanceof Contribution ? $proposal->access('view', $account, TRUE) : NULL;
    if (!$proposal instanceof Contribution || !$access?->isAllowed()) {
      throw new NotFoundHttpException();
    }
    $cacheability = CacheableMetadata::createFromObject($proposal)
      ->addCacheableDependency($access)
      ->addCacheContexts(['user', 'user.permissions', 'languages:language_interface']);
    $payloadRows = [];
    foreach ($proposal->getPayload() as $field => $value) {
      $payloadRows[] = [
        'field' => ['data' => ['#plain_text' => (string) $field]],
        'value' => ['data' => ['#plain_text' => $this->displayValue($value)]],
      ];
    }
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-contribution-status'],
      'back' => [
        '#type' => 'link',
        '#title' => $this->t('Voltar às minhas propostas'),
        '#url' => Url::fromRoute('aculta_events.organizer_contributions'),
      ],
      'subject' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => $this->plainText((string) $proposal->get('subject')->value),
      ],
      'state' => [
        '#type' => 'item',
        '#title' => $this->t('Situação'),
        '#markup' => $this->plainText($this->stateLabel((string) $proposal->get('state')->value)),
      ],
      'rationale' => [
        '#type' => 'item',
        '#title' => $this->t('Justificativa enviada'),
        '#markup' => $this->plainText((string) $proposal->get('rationale')->value),
      ],
      'payload' => [
        '#prefix' => '<div class="table-responsive">',
        '#suffix' => '</div>',
        '#type' => 'table',
        '#caption' => $this->t('Conteúdo enviado'),
        '#header' => [$this->t('Campo'), $this->t('Valor')],
        '#rows' => $payloadRows,
      ],
    ];
    if ($proposal->get('state')->value === 'needs_changes') {
      $build['revise'] = [
        '#type' => 'component',
        '#component' => 'aculta420_v2:button',
        '#props' => [
          'text' => (string) $this->t('Revisar e reenviar proposta'),
          'url' => Url::fromRoute('aculta_events.organizer_contribution_revise', [
            'proposal_uuid' => $proposal->uuid(),
          ])->toString(),
          'variant' => 'primary',
        ],
      ];
    }
    if (in_array($proposal->get('state')->value, ['needs_changes', 'declined', 'conflict'], TRUE)) {
      $build['decision_note'] = [
        '#type' => 'item',
        '#title' => $this->t('Retorno da revisão'),
        '#markup' => $this->plainText((string) $proposal->get('decision_note')->value),
      ];
    }
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Formats an allowlisted payload value for safe display. */
  private function displayValue(mixed $value): string {
    if (is_array($value)) {
      return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
    if (is_bool($value)) {
      return $value ? (string) $this->t('Sim') : (string) $this->t('Não');
    }
    return is_scalar($value) ? (string) $value : '';
  }

  /**
   * Escapes text values rendered through #markup. */
  private function plainText(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  /**
   * Returns the display label for a supported contribution intent. */
  private function intentLabel(string $intent): string {
    return match ($intent) {
      'organization_create' => (string) $this->t('Criação de Organização'),
      'organization_update' => (string) $this->t('Atualização de Organização'),
      'location_create' => (string) $this->t('Criação de Local'),
      'location_update' => (string) $this->t('Atualização de Local'),
      'event_create' => (string) $this->t('Criação de Evento'),
      'event_update' => (string) $this->t('Atualização de Evento'),
      default => (string) $this->t('Proposta'),
    };
  }

  /**
   * Returns a translated label for a contribution state. */
  private function stateLabel(string $state): string {
    return match ($state) {
      'submitted' => (string) $this->t('Em análise'),
      'needs_changes' => (string) $this->t('Precisa de ajustes'),
      'accepted' => (string) $this->t('Aceita'),
      'declined' => (string) $this->t('Não aceita'),
      'withdrawn' => (string) $this->t('Retirada'),
      'conflict' => (string) $this->t('Precisa de nova análise'),
      default => (string) $this->t('Situação desconhecida'),
    };
  }

}
