<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link as CoreLink;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_links\Service\LinkAnalytics;

/**
 * Builds the owner-scoped administration dashboard for pages and links.
 */
final class LinksDashboardController {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly DomainPurposeManager $domainPurpose,
    private readonly LinkAnalytics $analytics,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Renders page and reusable-link listings after rechecking entity access.
   *
   * @return array
   *   Cacheable render array.
   */
  public function overview(): array {
    $account = $this->currentUser;
    $admin = $account->hasPermission('administer aculta links');
    $build = [
      'actions' => [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => [
          CoreLink::createFromRoute(
            new TranslatableMarkup('Criar micropágina'),
            'entity.aculta_link_page.add_form',
          )->toRenderable(),
          CoreLink::createFromRoute(
            new TranslatableMarkup('Criar destino reutilizável'),
            'entity.aculta_link.add_form',
          )->toRenderable(),
          CoreLink::createFromRoute(
            new TranslatableMarkup('Adicionar item a uma micropágina'),
            'entity.aculta_link_item.add_form',
          )->toRenderable(),
        ],
      ],
      'pages' => $this->buildTable(
        'aculta_link_page',
        'aculta_link_page_list',
        'Micropáginas',
        $admin,
        'uid',
        0,
      ),
      'links' => $this->buildTable(
        'aculta_link',
        'aculta_link_list',
        'Destinos reutilizáveis',
        $admin,
        'uid',
        1,
      ),
      'items' => $this->buildTable(
        'aculta_link_item',
        'aculta_link_item_list',
        'Itens de micropágina',
        $admin,
        'uid',
        2,
      ),
      '#cache' => [
        'contexts' => ['user', 'user.permissions'],
      ],
    ];
    return $build;
  }

  /**
   * Builds an access-filtered administrative table.
   *
   * @param string $entity_type_id
   *   Entity type ID.
   * @param string $list_cache_tag
   *   Entity list cache tag.
   * @param string $heading
   *   Heading presented above the table.
   * @param bool $admin
   *   Whether the current account has global management permission.
   * @param string $owner_key
   *   Entity owner field.
   * @param int $pager_element
   *   Pager element ID.
   *
   * @return array
   *   Cacheable table render array.
   */
  private function buildTable(string $entity_type_id, string $list_cache_tag, string $heading, bool $admin, string $owner_key, int $pager_element): array {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $filter_key = match ($entity_type_id) {
      'aculta_link_page' => 'paginas_estado',
      'aculta_link' => 'destinos_estado',
      default => 'itens_estado',
    };
    $filter = $this->requestStack->getCurrentRequest()?->query->get($filter_key);
    $filter = in_array($filter, ['publicados', 'rascunhos'], TRUE) ? $filter : 'todos';
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->sort('changed', 'DESC')
      ->pager(50, $pager_element);
    if ($filter !== 'todos') {
      $query->condition('status', $filter === 'publicados');
    }
    if (!$admin) {
      $query->condition($owner_key, $this->currentUser->id());
    }
    $entities = $storage->loadMultiple($query->execute());
    $links_domain = in_array($entity_type_id, ['aculta_link', 'aculta_link_page'], TRUE)
      ? $this->domainPurpose->getDomain('links')
      : NULL;
    $cache_tags = array_merge(
      $this->entityTypeManager->getDefinition($entity_type_id)->getListCacheTags(),
      [$list_cache_tag],
      $entity_type_id === 'aculta_link' ? ['aculta_links_analytics'] : [],
    );
    if ($links_domain !== NULL) {
      $cache_tags = array_merge($cache_tags, $links_domain->getCacheTags());
    }
    $authorized_entities = [];
    foreach ($entities as $entity) {
      $access = $entity->access('update', $this->currentUser, TRUE);
      $cache_tags = array_merge($cache_tags, $entity->getCacheTags(), $access->getCacheTags());
      if ($access->isAllowed()) {
        $authorized_entities[] = $entity;
      }
    }
    $click_counts = $entity_type_id === 'aculta_link'
      ? $this->analytics->clickCounts(array_map(static fn ($link): int => (int) $link->id(), $authorized_entities))
      : [];
    $rows = [];
    foreach ($authorized_entities as $entity) {
      $edit_route = 'entity.' . $entity_type_id . '.edit_form';
      $delete_route = 'entity.' . $entity_type_id . '.delete_form';
      $route_parameter = $entity_type_id;
      $route_arguments = [$route_parameter => $entity->id()];
      $operations = [
        'edit' => [
          'title' => new TranslatableMarkup('Editar'),
          'url' => Url::fromRoute($edit_route, $route_arguments),
        ],
        'delete' => [
          'title' => new TranslatableMarkup('Excluir'),
          'url' => Url::fromRoute($delete_route, $route_arguments),
        ],
        'revisions' => [
          'title' => new TranslatableMarkup('Revisões'),
          'url' => Url::fromRoute('entity.' . $entity_type_id . '.version_history', $route_arguments),
        ],
      ];
      if ($entity_type_id === 'aculta_link_page') {
        $operations['items'] = [
          'title' => new TranslatableMarkup('Editar itens'),
          'url' => Url::fromRoute('aculta_links.page_editor', ['aculta_link_page' => $entity->id()]),
        ];
        $operations['add_item'] = [
          'title' => new TranslatableMarkup('Adicionar botão'),
          'url' => Url::fromRoute('aculta_links.page_item_add', ['aculta_link_page' => $entity->id()]),
        ];
        $slug = (string) $entity->get('slug')->value;
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) === 1) {
          $page_path = $entity->isPublished()
            ? '/pagina/' . $slug
            : '/pagina/' . $slug . '/previa';
          $page_url = $this->domainPurpose->pathUrl('links', $page_path);
          if ($page_url !== NULL) {
            $operations['public_page'] = [
              'title' => $entity->isPublished()
                ? new TranslatableMarkup('Abrir página pública')
                : new TranslatableMarkup('Pré-visualizar'),
              'url' => $page_url,
            ];
          }
          if ($entity->isPublished()) {
            $qr_url = $this->domainPurpose->pathUrl('links', '/pagina/' . $slug . '/codigo-qr.svg');
            if ($qr_url !== NULL) {
              $operations['qr_code'] = [
                'title' => new TranslatableMarkup('Baixar código QR'),
                'url' => $qr_url,
              ];
            }
          }
        }
      }
      elseif ($entity_type_id === 'aculta_link' && $entity->isPublished()) {
        $short_code = (string) $entity->get('short_code')->value;
        if (preg_match('/^[0-9a-z]{12}$/D', $short_code) === 1) {
          $short_url = $this->domainPurpose->routeUrl(
            'links',
            'aculta_links.short_redirect',
            ['codigo' => $short_code],
          );
          if ($short_url !== NULL) {
            $operations['short_url'] = [
              'title' => new TranslatableMarkup('Abrir link curto'),
              'url' => $short_url,
            ];
          }
          $qr_url = $this->domainPurpose->routeUrl(
            'links',
            'aculta_links.short_qr_code',
            ['codigo' => $short_code],
          );
          if ($qr_url !== NULL) {
            $operations['short_qr_code'] = [
              'title' => new TranslatableMarkup('Baixar QR Code do link curto'),
              'url' => $qr_url,
            ];
          }
        }
      }
      $row = [
        'title' => CoreLink::fromTextAndUrl($entity->label(), Url::fromRoute($edit_route, $route_arguments)),
        'status' => $entity->isPublished()
          ? new TranslatableMarkup('Publicado')
          : new TranslatableMarkup('Rascunho'),
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => $operations,
          ],
        ],
      ];
      if ($entity_type_id === 'aculta_link') {
        $row = [
          'title' => $row['title'],
          'status' => $row['status'],
          'clicks' => (int) ($click_counts[(int) $entity->id()] ?? 0),
          'operations' => $row['operations'],
        ];
      }
      $rows[] = $row;
    }

    $header = [
      new TranslatableMarkup('Título'),
      [
        'data' => new TranslatableMarkup('Estado'),
        'class' => [\RESPONSIVE_PRIORITY_MEDIUM],
      ],
    ];
    if ($entity_type_id === 'aculta_link') {
      $header[] = [
        'data' => new TranslatableMarkup('Cliques'),
        'class' => [\RESPONSIVE_PRIORITY_LOW],
      ];
    }
    $header[] = new TranslatableMarkup('Operações');

    return [
      '#type' => 'details',
      '#title' => match ($heading) {
        'Micropáginas' => new TranslatableMarkup('Micropáginas'),
        'Destinos reutilizáveis' => new TranslatableMarkup('Destinos reutilizáveis'),
        default => new TranslatableMarkup('Itens de micropágina'),
      },
      '#open' => TRUE,
      'filters' => [
        '#theme' => 'links',
        '#links' => $this->buildStatusFilterLinks($filter_key, $filter),
        '#attributes' => ['class' => ['aculta-links-status-filters']],
        '#cache' => [
          'contexts' => ['url.query_args:' . $filter_key],
        ],
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => new TranslatableMarkup('Nenhum item cadastrado.'),
        '#cache' => [
          'contexts' => [
            'user',
            'user.permissions',
            'url.query_args:page',
            'url.query_args:' . $filter_key,
            ...(in_array($entity_type_id, ['aculta_link', 'aculta_link_page'], TRUE)
              ? ['url.site', 'languages:language_interface']
              : []),
          ],
          'tags' => array_values(array_unique($cache_tags)),
        ],
      ],
      'pager' => [
        '#type' => 'pager',
        '#element' => $pager_element,
      ],
    ];
  }

  /**
   * Builds links for the publication status filter of a dashboard list.
   *
   * @param string $filter_key
   *   Query parameter dedicated to this entity list.
   * @param string $active_filter
   *   Current normalized filter.
   *
   * @return array
   *   Render links keyed by status value.
   */
  private function buildStatusFilterLinks(string $filter_key, string $active_filter): array {
    $request = $this->requestStack->getCurrentRequest();
    $query = $request?->query->all() ?? [];
    // Reset pager offsets when a filter changes but retain filters for the
    // other two lists. Core pager links preserve these query arguments.
    unset($query['page']);
    $labels = [
      'todos' => new TranslatableMarkup('Todos'),
      'publicados' => new TranslatableMarkup('Publicados'),
      'rascunhos' => new TranslatableMarkup('Rascunhos'),
    ];
    $links = [];
    foreach ($labels as $value => $label) {
      $parameters = $query;
      if ($value === 'todos') {
        unset($parameters[$filter_key]);
      }
      else {
        $parameters[$filter_key] = $value;
      }
      $links[$value] = [
        'title' => $label,
        'url' => Url::fromRoute('aculta_links.dashboard', [], ['query' => $parameters]),
        'attributes' => [
          'aria-current' => $active_filter === $value ? 'page' : NULL,
        ],
      ];
    }
    return $links;
  }

}
