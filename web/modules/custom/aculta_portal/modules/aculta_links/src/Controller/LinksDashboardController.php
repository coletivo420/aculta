<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link as CoreLink;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Builds the owner-scoped administration dashboard for pages and links.
 */
final class LinksDashboardController {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
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
        '#type' => 'container',
        'add_page' => [
          '#type' => 'link',
          '#title' => new TranslatableMarkup('Criar micropágina'),
          '#url' => Url::fromRoute('entity.aculta_link_page.add_form'),
        ],
        'add_link' => [
          '#type' => 'link',
          '#title' => new TranslatableMarkup('Criar destino reutilizável'),
          '#url' => Url::fromRoute('entity.aculta_link.add_form'),
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
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->sort('changed', 'DESC')
      ->pager(50, $pager_element);
    if (!$admin) {
      $query->condition($owner_key, $this->currentUser->id());
    }
    $entities = $storage->loadMultiple($query->execute());
    $rows = [];
    $cache_tags = array_merge(
      $this->entityTypeManager->getDefinition($entity_type_id)->getListCacheTags(),
      [$list_cache_tag],
    );
    foreach ($entities as $entity) {
      $access = $entity->access('update', $this->currentUser, TRUE);
      $cache_tags = array_merge($cache_tags, $entity->getCacheTags(), $access->getCacheTags());
      if (!$access->isAllowed()) {
        continue;
      }
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
      $rows[] = [
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
    }

    return [
      '#type' => 'details',
      '#title' => match ($heading) {
        'Micropáginas' => new TranslatableMarkup('Micropáginas'),
        default => new TranslatableMarkup('Destinos reutilizáveis'),
      },
      '#open' => TRUE,
      'table' => [
        '#type' => 'table',
        '#header' => [
          new TranslatableMarkup('Título'),
          new TranslatableMarkup('Estado'),
          new TranslatableMarkup('Operações'),
        ],
        '#rows' => $rows,
        '#empty' => new TranslatableMarkup('Nenhum item cadastrado.'),
        '#cache' => [
          'contexts' => ['user', 'user.permissions', 'url.query_args:page'],
          'tags' => array_values(array_unique($cache_tags)),
        ],
      ],
      'pager' => [
        '#type' => 'pager',
        '#element' => $pager_element,
      ],
    ];
  }

}
