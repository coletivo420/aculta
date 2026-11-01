<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Painel administrativo de reivindicações de posse e organizações (ARQ-05).
 */
final class OwnershipClaimAdminController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeMgr,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Renderiza a listagem de pedidos de reivindicação de posse.
   */
  public function overview(Request $request): array {
    $statusFilter = $request->query->get('status');
    $targetTypeFilter = $request->query->get('target_type');
    $targetIdFilter = $request->query->get('target_id');
    $validStatuses = ['pending' => 'Pendentes', 'approved' => 'Aprovadas', 'rejected' => 'Rejeitadas'];

    $storage = $this->entityTypeMgr->getStorage('aculta_ownership_claim');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->sort('created', 'DESC')
      ->pager(25);

    if ($statusFilter !== NULL && array_key_exists($statusFilter, $validStatuses)) {
      $query->condition('status', $statusFilter);
    }
    if ($targetTypeFilter !== NULL && $targetTypeFilter !== '') {
      $query->condition('target_type', (string) $targetTypeFilter);
    }
    if ($targetIdFilter !== NULL && is_numeric($targetIdFilter)) {
      $query->condition('target_id', (int) $targetIdFilter);
    }

    $claimIds = $query->execute();
    /** @var \Drupal\aculta_user\Entity\OwnershipClaim[] $claims */
    $claims = !empty($claimIds) ? $storage->loadMultiple($claimIds) : [];

    $rows = [];
    $userStorage = $this->entityTypeMgr->getStorage('user');

    foreach ($claims as $claim) {
      $targetType = (string) $claim->get('target_type')->value;
      $targetId = (int) $claim->get('target_id')->value;

      $targetText = sprintf('%s #%d', $targetType, $targetId);
      if ($this->entityTypeMgr->hasDefinition($targetType)) {
        $targetEntity = $this->entityTypeMgr->getStorage($targetType)->load($targetId);
        if ($targetEntity !== NULL) {
          if ($targetEntity->access('view', $this->currentUser())) {
            $label = $targetEntity->label() ?? $targetText;
            if ($targetEntity->hasLinkTemplate('canonical')) {
              try {
                $targetCell = Link::fromTextAndUrl((string) $label, $targetEntity->toUrl())->toRenderable();
              }
              catch (\Throwable) {
                $targetCell = ['#plain_text' => (string) $label];
              }
            }
            else {
              $targetCell = ['#plain_text' => (string) $label];
            }
          }
          else {
            $targetCell = ['#plain_text' => sprintf('[%s] (%s)', $this->t('Conteúdo restrito'), $targetText)];
          }
        }
        else {
          $targetCell = ['#plain_text' => sprintf('[%s] (%s)', $this->t('Conteúdo excluído'), $targetText)];
        }
      }
      else {
        $targetCell = ['#plain_text' => $targetText];
      }

      $claimantUid = (int) $claim->get('uid')->target_id;
      $claimant = $userStorage->load($claimantUid);
      $claimantName = $claimant !== NULL ? $claimant->getDisplayName() : sprintf('UID #%d', $claimantUid);

      $statusValue = (string) $claim->get('status')->value;
      $statusLabel = match ($statusValue) {
        'approved' => $this->t('Aprovado'),
        'rejected' => $this->t('Rejeitado'),
        default => $this->t('Pendente'),
      };

      $created = (int) $claim->get('created')->value;
      $createdFormatted = $created > 0 ? $this->dateFormatter->format($created, 'short') : '-';

      $justificativa = (string) $claim->get('justificativa')->value;
      $justificativaTruncada = Unicode::truncate($justificativa, 100, TRUE, TRUE);

      $actions = [];
      if ($statusValue === 'pending') {
        $actions[] = [
          'title' => $this->t('Avaliar / Decidir'),
          'url' => Url::fromRoute('aculta_user.ownership_claim_decide', ['aculta_ownership_claim' => $claim->id()]),
        ];
      }
      else {
        $decidedByUid = (int) $claim->get('decided_by')->value;
        $decidedBy = $decidedByUid > 0 ? $userStorage->load($decidedByUid) : NULL;
        $decidedByName = $decidedBy !== NULL ? $decidedBy->getDisplayName() : ($decidedByUid > 0 ? sprintf('UID #%d', $decidedByUid) : '-');
        $actions[] = [
          'title' => sprintf('%s (%s)', $statusLabel, $decidedByName),
          'url' => Url::fromRoute('aculta_user.ownership_claim_decide', ['aculta_ownership_claim' => $claim->id()]),
        ];
      }

      $rows[] = [
        'id' => $claim->id(),
        'target' => ['data' => $targetCell],
        'claimant' => $claimantName,
        'justificativa' => $justificativaTruncada,
        'contato' => (string) ($claim->get('contato')->value ?? '-'),
        'created' => $createdFormatted,
        'status' => $statusLabel,
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => $actions,
          ],
        ],
      ];
    }

    $filterLinks = [
      'all' => [
        'title' => $this->t('Todas'),
        'url' => Url::fromRoute('aculta_user.ownership_claims'),
        'attributes' => $statusFilter === NULL ? ['class' => ['is-active']] : [],
      ],
    ];
    foreach ($validStatuses as $st => $stLabel) {
      $filterLinks[$st] = [
        'title' => $stLabel,
        'url' => Url::fromRoute('aculta_user.ownership_claims', [], ['query' => ['status' => $st]]),
        'attributes' => $statusFilter === $st ? ['class' => ['is-active']] : [],
      ];
    }

    return [
      'filters' => [
        '#theme' => 'links',
        '#links' => $filterLinks,
        '#attributes' => ['data-portal-ui' => 'user-admin-filters'],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('ID'),
          $this->t('Entidade Reivindicada'),
          $this->t('Solicitante'),
          $this->t('Justificativa'),
          $this->t('Contato'),
          $this->t('Data do pedido'),
          $this->t('Situação'),
          $this->t('Ações'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhuma reivindicação de posse encontrada para os filtros selecionados.'),
      ],
      'pager' => [
        '#type' => 'pager',
      ],
      '#cache' => [
        'tags' => ['aculta_ownership_claim_list'],
        'contexts' => [
          'user.permissions',
          'url.query_args:status',
          'url.query_args:target_type',
          'url.query_args:target_id',
        ],
      ],
    ];
  }

}
