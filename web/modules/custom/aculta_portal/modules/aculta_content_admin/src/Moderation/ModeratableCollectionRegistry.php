<?php

declare(strict_types=1);

namespace Drupal\aculta_content_admin\Moderation;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Entity\View;
use Drupal\views\ViewsData;

/**
 * Finds editorial collections without bypassing their native route access.
 */
final class ModeratableCollectionRegistry {

  /**
   * Controller-backed collection routes not represented by Views.
   *
   * This allowlist intentionally contains collection pages only. Actions,
   * individual records, settings and operational reports are excluded.
   * Routes from optional modules are ignored while those modules are disabled.
   *
   * @var array<string, string>
   */
  private const CONTROLLER_COLLECTIONS = [
    'aculta_forum.post_moderation_queue' => 'Fórum — publicações aguardando moderação',
    'aculta_forum_lms.moderation_overview' => 'Fóruns do LMS — coleções moderáveis',
    'aculta_calendar.import_review' => 'Calendário — itens importados para revisão',
    'aculta_messenger.admin_temporary_notices' => 'Avisos — temporários',
    'aculta_messenger.admin_persistent_notices' => 'Avisos — persistentes',
    'aculta_links.dashboard' => 'ACULTA Links — micropáginas e destinos',
    'aculta_faq.admin' => 'FAQ — perguntas e blocos editoriais',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccessManagerInterface $accessManager,
    private readonly ViewsData $viewsData,
  ) {}

  /**
   * Returns only collections whose native route access is allowed.
   *
   * @return array{collections: list<array{label: string, route: string, parameters: array, access: \Drupal\Core\Access\AccessResultInterface, cache_tags: list<string>}>, cacheability: \Drupal\Core\Cache\CacheableMetadata}
   *   Accessible links and cache metadata, including denied candidates.
   */
  public function forAccount(AccountInterface $account): array {
    $cacheability = (new CacheableMetadata())->addCacheContexts(['user', 'user.permissions']);
    $collections = [];

    foreach ($this->controllerCandidates() as $route_name => $label) {
      $this->addCandidate($collections, $cacheability, $route_name, $label, [], $account);
    }

    foreach ($this->viewCandidates($cacheability) as $candidate) {
      $this->addCandidate(
        $collections,
        $cacheability,
        $candidate['route'],
        $candidate['label'],
        [],
        $account,
        $candidate['cache_tags'],
      );
    }

    usort($collections, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
    return ['collections' => $collections, 'cacheability' => $cacheability];
  }

  /**
   * Grants dashboard access only when at least one moderatable list is visible.
   */
  public function overviewAccess(RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $result = $this->forAccount($account);
    return AccessResult::allowedIf($result['collections'] !== [])
      ->addCacheableDependency($result['cacheability']);
  }

  /**
   * Adds one native route candidate to the accessible collection list.
   *
   * @param list<array{label: string, route: string, parameters: array, access: \Drupal\Core\Access\AccessResultInterface, cache_tags: list<string>}> $collections
   *   Collections visible to this account.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Metadata from every evaluated route, including denials.
   * @param string $route
   *   Route name to check.
   * @param string $label
   *   Human-readable collection label.
   * @param array $parameters
   *   Route parameters.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account to check.
   * @param list<string> $cache_tags
   *   Config entity cache tags to add for discovered Views.
   */
  private function addCandidate(array &$collections, CacheableMetadata $cacheability, string $route, string $label, array $parameters, AccountInterface $account, array $cache_tags = []): void {
    $access = $this->accessManager->checkNamedRoute($route, $parameters, $account, TRUE);

    $cacheability->addCacheableDependency($access);
    if (!$access->isAllowed()) {
      return;
    }

    $collections[] = [
      'label' => $label,
      'route' => $route,
      'parameters' => $parameters,
      'access' => $access,
      'cache_tags' => $cache_tags,
    ];
  }

  /**
   * Returns page Views whose base entity is editorial content.
   *
   * @return list<array{route: string, label: string, cache_tags: list<string>}>
   *   Candidate page routes.
   */
  private function viewCandidates(CacheableMetadata $cacheability): array {
    $candidates = [];
    foreach ($this->entityTypeManager->getStorage('view')->loadMultiple() as $view) {
      if (!$view instanceof View || !$view->status()) {
        continue;
      }
      $base_table = $view->get('base_table');
      $base = $this->viewsData->get($base_table)['table']['entity type'] ?? NULL;
      if (!is_string($base) || $base === 'user' || !$this->isContentEntityType($base)) {
        continue;
      }

      foreach ($view->get('display') as $display_id => $display) {
        if (($display['display_plugin'] ?? NULL) !== 'page') {
          continue;
        }
        $path = ltrim((string) ($display['display_options']['path'] ?? ''), '/');
        if (!str_starts_with($path, 'painel-administrativo/')) {
          continue;
        }
        $route = "view.{$view->id()}.{$display_id}";
        $display_title = trim((string) ($display['display_title'] ?? ''));
        $label = $display_title === '' ? (string) $view->label() : $view->label() . ' — ' . $display_title;
        $tags = $view->getCacheTags();
        $cacheability->addCacheableDependency($view);
        $candidates[] = ['route' => $route, 'label' => (string) $label, 'cache_tags' => $tags];
      }
    }
    return $candidates;
  }

  /**
   * Checks that the Views base table belongs to a content entity, not a user.
   */
  private function isContentEntityType(string $entity_type_id): bool {
    if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
      return FALSE;
    }
    $class = $this->entityTypeManager->getDefinition($entity_type_id)->getClass();
    return is_a($class, ContentEntityInterface::class, TRUE);
  }

  /**
   * Returns the controller route allowlist.
   *
   * @return array<string, string>
   *   Route names and editorial collection labels.
   */
  private function controllerCandidates(): array {
    return self::CONTROLLER_COLLECTIONS;
  }

}
