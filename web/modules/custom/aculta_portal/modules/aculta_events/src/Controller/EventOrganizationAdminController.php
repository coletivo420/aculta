<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Gestão de Organizações Culturais no domínio principal (ARQ-05).
 */
final class EventOrganizationAdminController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /**
   * Visão geral de organizações no painel administrativo.
   */
  public function overview(Request $request): array {
    $storage = $this->entities->getStorage('aculta_event_organization');

    $filterNome = trim((string) $request->query->get('nome', ''));
    $filterReivindicada = trim((string) $request->query->get('reivindicada', ''));
    $filterRevisao = trim((string) $request->query->get('revisao', ''));
    $filterDenuncias = trim((string) $request->query->get('com_denuncias', ''));

    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->sort('created', 'DESC')
      ->pager(20);

    if ($filterNome !== '') {
      $query->condition('title', $filterNome, 'CONTAINS');
    }

    if ($filterReivindicada === 'reivindicada') {
      $query->condition('reivindicada', 1);
    }
    elseif ($filterReivindicada === 'pendente') {
      $query->condition('reivindicacao_status', 'pendente');
    }
    elseif ($filterReivindicada === 'nao_reivindicada') {
      $query->condition('reivindicada', 0);
    }

    if ($filterRevisao === 'em_revisao') {
      $query->condition('reivindicacao_status', 'em_revisao');
    }
    elseif ($filterRevisao === 'confirmada') {
      $query->condition('reivindicacao_status', 'confirmada');
    }

    if ($filterDenuncias === '1' && $this->entities->hasDefinition('aculta_ugc_report')) {
      $reportQuery = $this->entities->getStorage('aculta_ugc_report')->getQuery()
        ->accessCheck(FALSE)
        ->condition('target_type', 'aculta_event_organization')
        ->condition('status', ['pending', 'assigned'], 'IN');
      $reportedIds = $reportQuery->execute();
      if (!empty($reportedIds)) {
        $reports = $this->entities->getStorage('aculta_ugc_report')->loadMultiple($reportedIds);
        $targetOrgIds = [];
        foreach ($reports as $rep) {
          $targetOrgIds[] = (int) $rep->get('target_id')->value;
        }
        $targetOrgIds = array_unique(array_filter($targetOrgIds));
        if (!empty($targetOrgIds)) {
          $query->condition('id', $targetOrgIds, 'IN');
        }
        else {
          $query->condition('id', 0);
        }
      }
      else {
        $query->condition('id', 0);
      }
    }

    $ids = $query->execute();

    $rows = [];
    if (!empty($ids)) {
      /** @var \Drupal\aculta_events\Entity\EventOrganization[] $orgs */
      $orgs = $storage->loadMultiple($ids);
      foreach ($orgs as $org) {
        $titularUid = $org->getOwnerId();
        $titularName = $this->t('Nenhum (não reivindicada)');
        if ($titularUid > 0) {
          /** @var \Drupal\user\UserInterface|null $user */
          $user = $this->entities->getStorage('user')->load($titularUid);
          $titularName = $user ? $user->getDisplayName() : ('UID ' . $titularUid);
        }

        $localidadeStr = (string) $this->t('Não informada');
        if ($org->shouldHideLocation()) {
          $localidadeStr = (string) $this->t('Ocultada da exibição pública');
        }
        elseif (!empty($org->get('localidade')->value) || !empty($org->get('uf')->value)) {
          $parts = array_filter([(string) $org->get('localidade')->value, (string) $org->get('uf')->value]);
          $localidadeStr = implode(' - ', $parts);
        }

        // Estado administrativo e avisos de moderação.
        $statusItems = [];
        if ($org->isClaimed()) {
          $statusItems[] = [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => (string) $this->t('Reivindicada (@status)', ['@status' => $org->getClaimStatus()]),
            '#attributes' => ['data-portal-ui' => 'event-organization-status-success'],
          ];
        }
        else {
          if ($org->getClaimStatus() === 'pendente') {
            $statusItems[] = [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#value' => (string) $this->t('Reivindicação pendente'),
              '#attributes' => ['data-portal-ui' => 'event-organization-status-warning'],
            ];
          }
          else {
            $statusItems[] = [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#value' => (string) $this->t('Não reivindicada'),
              '#attributes' => ['data-portal-ui' => 'event-organization-status-warning'],
            ];
          }
        }

        if ($org->getClaimStatus() === 'em_revisao') {
          $statusItems[] = [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => (string) $this->t('Acessos suspensos pendentes de revisão'),
            '#attributes' => ['data-portal-ui' => 'event-organization-status-info'],
          ];
        }

        // Checar denúncias abertas.
        if ($this->entities->hasDefinition('aculta_ugc_report')) {
          $ugcCount = (int) $this->entities->getStorage('aculta_ugc_report')->getQuery()
            ->accessCheck(FALSE)
            ->condition('target_type', 'aculta_event_organization')
            ->condition('target_id', (int) $org->id())
            ->condition('status', ['pending', 'assigned'], 'IN')
            ->count()
            ->execute();
          if ($ugcCount > 0) {
            $statusItems[] = [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#value' => (string) $this->t('@count denúncia(s) pendente(s)', ['@count' => $ugcCount]),
              '#attributes' => ['data-portal-ui' => 'event-organization-status-danger'],
            ];
          }
        }

        $operations = [
          'edit' => [
            'title' => $this->t('Editar'),
            'url' => Url::fromRoute('aculta_events.admin_organization_edit', ['aculta_event_organization' => $org->id()]),
          ],
          'members' => [
            'title' => $this->t('Membros e acessos'),
            'url' => Url::fromRoute('aculta_events.admin_organization_members', ['aculta_event_organization' => $org->id()]),
          ],
          'recovery' => [
            'title' => $this->t('Recuperar titularidade'),
            'url' => Url::fromRoute('aculta_events.admin_organization_recovery', ['aculta_event_organization' => $org->id()]),
          ],
          'public_view' => [
            'title' => $this->t('Ver perfil público'),
            'url' => $org->toUrl('canonical'),
            'attributes' => ['target' => '_blank', 'rel' => 'noopener'],
          ],
        ];

        // Link para fila de reivindicações se aplicável.
        if ($this->entities->hasDefinition('aculta_ownership_claim')) {
          $claimCount = (int) $this->entities->getStorage('aculta_ownership_claim')->getQuery()
            ->accessCheck(FALSE)
            ->condition('target_type', 'aculta_event_organization')
            ->condition('target_id', (int) $org->id())
            ->count()
            ->execute();
          if ($claimCount > 0) {
            $operations['claims'] = [
              'title' => $this->t('Reivindicações (@count)', ['@count' => $claimCount]),
              'url' => Url::fromRoute('aculta_user.ownership_claims', [], [
                'query' => [
                  'target_type' => 'aculta_event_organization',
                  'target_id' => $org->id(),
                ],
              ]),
            ];
          }
        }

        // Link para fila de denúncias se houver denúncias.
        if (isset($ugcCount) && $ugcCount > 0) {
          $operations['reports'] = [
            'title' => $this->t('Ver denúncias'),
            'url' => Url::fromRoute('aculta_ugc.moderation_dashboard', [], [
              'query' => ['target_type' => 'aculta_event_organization'],
            ]),
          ];
        }

        $rows[] = [
          'title' => [
            'data' => [
              '#markup' => '<strong>' . htmlspecialchars((string) $org->label(), ENT_QUOTES, 'UTF-8') . '</strong><br><small class="text-muted">Slug: ' . htmlspecialchars($org->getSlug(), ENT_QUOTES, 'UTF-8') . '</small>',
            ],
          ],
          'titular' => $titularName,
          'localidade' => $localidadeStr,
          'status' => ['data' => $statusItems],
          'actions' => [
            'data' => [
              '#type' => 'operations',
              '#links' => $operations,
            ],
          ],
        ];
      }
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'events-organization-admin-overview'],
      'header_actions' => [
        '#type' => 'actions',
        'add' => [
          '#type' => 'link',
          '#title' => $this->t('+ Cadastrar organização'),
          '#url' => Url::fromRoute('aculta_events.admin_organization_add'),
          '#attributes' => ['data-portal-ui' => 'event-organization-add-action'],
        ],
      ],
      'filters' => [
        '#type' => 'form',
        '#method' => 'get',
        '#attributes' => ['data-portal-ui' => 'admin-org-filters'],
        'wrapper' => [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'event-organization-admin-filter-fields'],
          'nome' => [
            '#type' => 'textfield',
            '#name' => 'nome',
            '#title' => $this->t('Nome'),
            '#default_value' => $filterNome,
            '#size' => 25,
          ],
          'reivindicada' => [
            '#type' => 'select',
            '#name' => 'reivindicada',
            '#title' => $this->t('Reivindicação'),
            '#options' => [
              '' => $this->t('- Todos -'),
              'reivindicada' => $this->t('Reivindicadas'),
              'pendente' => $this->t('Com reivindicação pendente'),
              'nao_reivindicada' => $this->t('Não reivindicadas'),
            ],
            '#default_value' => $filterReivindicada,
          ],
          'revisao' => [
            '#type' => 'select',
            '#name' => 'revisao',
            '#title' => $this->t('Acessos'),
            '#options' => [
              '' => $this->t('- Todos -'),
              'em_revisao' => $this->t('Acessos em revisão'),
              'confirmada' => $this->t('Confirmada'),
            ],
            '#default_value' => $filterRevisao,
          ],
          'com_denuncias' => [
            '#type' => 'select',
            '#name' => 'com_denuncias',
            '#title' => $this->t('Denúncias'),
            '#options' => [
              '' => $this->t('- Todas -'),
              '1' => $this->t('Com denúncias pendentes'),
            ],
            '#default_value' => $filterDenuncias,
          ],
          'actions' => [
            '#type' => 'actions',
            'submit' => [
              '#type' => 'submit',
              '#value' => $this->t('Filtrar'),
              '#attributes' => ['data-portal-ui' => 'button'],
            ],
            'reset' => [
              '#type' => 'link',
              '#title' => $this->t('Limpar'),
              '#url' => Url::fromRoute('aculta_events.admin_organizations'),
              '#attributes' => ['data-portal-ui' => 'button'],
            ],
          ],
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Organização'),
          $this->t('Titular'),
          $this->t('Localidade'),
          $this->t('Situação e moderação'),
          $this->t('Ações'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhuma organização cultural encontrada com os filtros informados.'),
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];

    $cacheMetadata = new CacheableMetadata();
    $cacheMetadata->addCacheContexts(['user.permissions', 'url.query_args']);
    $cacheMetadata->addCacheTags([
      'aculta_event_organization_list',
      'aculta_org_member_list',
      'aculta_ownership_claim_list',
      'aculta_ugc_report_list',
    ]);
    $cacheMetadata->applyTo($build);

    return $build;
  }

}
