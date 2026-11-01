<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_ugc\ModerationScope;
use Drupal\aculta_ugc\Service\UgcReportQueue;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Painel administrativo unificado de denúncias e moderação de UGC (ADM1).
 */
final class UgcModerationDashboardController extends ControllerBase {

  /**
   * Construtor do controller.
   */
  public function __construct(
    private readonly UgcReportQueue $ugcQueue,
    private readonly EntityTypeManagerInterface $entityTypeMgr,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('aculta_ugc.report_queue'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Checagem de acesso à fila de denúncias.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden()->cachePerUser();
    }
    if (ModerationScope::isGlobal($account) || !empty(ModerationScope::purposesFor($account))) {
      return AccessResult::allowed()->cachePerUser();
    }
    return AccessResult::forbidden('Sem permissão para moderar denúncias de conteúdo.')->cachePerUser();
  }

  /**
   * Renderiza a listagem unificada com filtros por purpose e status.
   */
  public function overview(Request $request): array {
    $account = $this->currentUser();
    $allowedPurposes = ModerationScope::purposesFor($account);

    $requestedPurpose = $request->query->get('purpose');
    $purpose = ($requestedPurpose !== NULL && in_array($requestedPurpose, $allowedPurposes, TRUE))
      ? $requestedPurpose
      : NULL;

    $validStatuses = [
      'pendente' => $this->t('Pendentes'),
      'em_analise' => $this->t('Em análise'),
      'procedente' => $this->t('Procedentes'),
      'improcedente' => $this->t('Improcedentes'),
    ];

    $requestedStatus = (string) $request->query->get('status', 'pendente');
    $status = array_key_exists($requestedStatus, $validStatuses) ? $requestedStatus : 'pendente';

    $page = max(0, $request->query->getInt('page', 0));
    $limit = 25;

    $queueData = $this->ugcQueue->getQueueItems($account, $purpose, $status, $page, $limit);

    $rows = [];
    $storage = $this->entityTypeMgr;
    $reportStorage = $storage->getStorage('aculta_ugc_report');

    foreach ($queueData['items'] as $item) {
      $targetType = $item['target_type'];
      $targetId = $item['target_id'];
      $itemPurpose = $item['purpose'];

      if (!$storage->hasDefinition($targetType)) {
        $targetCell = ['#plain_text' => sprintf('[%s #%d]', $targetType, $targetId)];
      }
      else {
        $entity = $storage->getStorage($targetType)->load($targetId);
        if ($entity === NULL) {
          $targetCell = ['#plain_text' => sprintf('[%s] (%s #%d)', $this->t('Conteúdo excluído'), $targetType, $targetId)];
        }
        elseif (!$entity->access('view', $account)) {
          $targetCell = ['#plain_text' => sprintf('[%s] (%s #%d)', $this->t('Conteúdo restrito'), $targetType, $targetId)];
        }
        else {
          $label = $entity->label() ?? sprintf('%s #%d', $targetType, $targetId);
          $isUnpublished = method_exists($entity, 'isPublished') && !$entity->isPublished();
          $displayText = $isUnpublished ? sprintf('%s (%s)', $label, $this->t('despublicado')) : (string) $label;

          if ($entity->hasLinkTemplate('canonical')) {
            try {
              $targetCell = Link::fromTextAndUrl($displayText, $entity->toUrl())->toRenderable();
            }
            catch (\Throwable) {
              $targetCell = ['#plain_text' => $displayText];
            }
          }
          else {
            $targetCell = ['#plain_text' => $displayText];
          }
        }
      }

      $purposeLabels = [
        'forum' => $this->t('Fórum'),
        'revista' => $this->t('Revista'),
        'wiki' => $this->t('Wiki'),
        'eventos' => $this->t('Eventos'),
        'portal' => $this->t('Portal'),
      ];
      $purposeLabel = $purposeLabels[$itemPurpose] ?? ucfirst($itemPurpose);

      $motivosStr = !empty($item['motivos']) ? implode(', ', $item['motivos']) : '-';
      $ultimaFormatted = $item['ultima'] > 0 ? $this->dateFormatter->format($item['ultima'], 'short') : '-';

      // Localiza a denúncia correspondente para ação de julgamento.
      $reportIdQuery = $reportStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('target_type', $targetType)
        ->condition('target_id', $targetId)
        ->condition('purpose', $itemPurpose)
        ->condition('status', $status)
        ->sort('id', 'DESC')
        ->range(0, 1)
        ->execute();

      $actions = [];
      if (!empty($reportIdQuery)) {
        $reportId = (int) reset($reportIdQuery);
        $actions[] = [
          'title' => ($status === 'pendente' || $status === 'em_analise') ? $this->t('Julgar / Parecer') : $this->t('Ver detalhes'),
          'url' => Url::fromRoute('aculta_ugc.report_decide', ['aculta_ugc_report' => $reportId]),
        ];
      }

      $rows[] = [
        'target' => ['data' => $targetCell],
        'purpose' => $purposeLabel,
        'total' => (int) $item['total'],
        'motivos' => $motivosStr,
        'ultima' => $ultimaFormatted,
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => $actions,
          ],
        ],
      ];
    }

    // Filtros por status.
    $statusFilterLinks = [];
    foreach ($validStatuses as $st => $stLabel) {
      $queryParams = ['status' => $st];
      if ($purpose !== NULL) {
        $queryParams['purpose'] = $purpose;
      }
      $statusFilterLinks[$st] = [
        'title' => $stLabel,
        'url' => Url::fromRoute('aculta_ugc.admin_reports', [], ['query' => $queryParams]),
        'attributes' => $status === $st ? ['class' => ['is-active']] : [],
      ];
    }

    // Filtros por purpose (apenas os autorizados para este moderador).
    $purposeFilterLinks = [
      'all' => [
        'title' => $this->t('Todos os setores autorizados'),
        'url' => Url::fromRoute('aculta_ugc.admin_reports', [], ['query' => ['status' => $status]]),
        'attributes' => $purpose === NULL ? ['class' => ['is-active']] : [],
      ],
    ];
    foreach ($allowedPurposes as $p) {
      $pLabel = $purposeLabels[$p] ?? ucfirst($p);
      $purposeFilterLinks[$p] = [
        'title' => $pLabel,
        'url' => Url::fromRoute('aculta_ugc.admin_reports', [], ['query' => ['status' => $status, 'purpose' => $p]]),
        'attributes' => $purpose === $p ? ['class' => ['is-active']] : [],
      ];
    }

    return [
      'status_filters' => [
        '#theme' => 'links',
        '#links' => $statusFilterLinks,
        '#attributes' => ['data-portal-ui' => 'ugc-admin-status-filters'],
      ],
      'purpose_filters' => [
        '#theme' => 'links',
        '#links' => $purposeFilterLinks,
        '#attributes' => ['data-portal-ui' => 'ugc-admin-purpose-filters'],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Conteúdo denunciado'),
          $this->t('Área / Purpose'),
          $this->t('Total de denúncias'),
          $this->t('Motivos apontados'),
          $this->t('Última ocorrência'),
          $this->t('Ações'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhuma denúncia encontrada para os filtros selecionados.'),
      ],
      'pager' => [
        '#type' => 'pager',
      ],
      '#cache' => [
        'tags' => ['aculta_ugc_report_list'],
        'contexts' => ['user.permissions', 'url.query_args:status', 'url.query_args:purpose'],
      ],
    ];
  }

}
