<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\PathProcessor\PurposeFrontPathProcessor;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Restores Forum browse tabs at the purpose root without linking to its internal path. */
final class PublicForumFrontNavigationHooks {

  private const TASKS_ROUTE = 'view.aculta_forum_public_topics.page_new_topics';

  public function __construct(
    #[Autowire(service: 'plugin.manager.menu.local_task')]
    private readonly LocalTaskManagerInterface $localTaskManager,
    private readonly RequestStack $requestStack,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
 * Adds the sibling browse tasks when the public Forum is served at `/`. */
  #[Hook('views_pre_render')]
  public function viewsPreRender(ViewExecutable $view): void {
    $request = $this->requestStack->getCurrentRequest();
    if ($view->id() !== 'aculta_forum_public_topics'
      || $view->current_display !== 'page_discussions'
      || $request === NULL
      || $request->getPathInfo() !== '/'
      || $request->attributes->get(PurposeFrontPathProcessor::FRONT_ATTRIBUTE) !== TRUE
      || $this->domainPurposeManager->getCurrentPurpose() !== 'forum') {
      return;
    }

    // The internal `/discussoes` path is intentionally not public. Build the
    // existing sibling tasks from a routed display, then add the public `/`
    // link as the active root item.
    $cacheability = new CacheableMetadata();
    $tasks = $this->localTaskManager->getTasksBuild(self::TASKS_ROUTE, $cacheability);
    $primary = $tasks[0] ?? [];
    foreach ($primary as &$task) {
      $task['#active'] = FALSE;
    }
    unset($task);
    $frontUrl = Url::fromUri('base:/');
    $frontUrl->setOption('attributes', ['aria-current' => 'page']);
    $primary = [
      'aculta_forum.public_front' => [
        '#theme' => 'menu_local_task',
        '#link' => [
          'title' => new TranslatableMarkup('Discussões'),
          'url' => $frontUrl,
          'localized_options' => [],
        ],
        '#active' => TRUE,
        '#weight' => -100,
      ],
    ] + $primary;

    $navigation = [
      '#theme' => 'menu_local_tasks',
      '#primary' => $primary,
      '#secondary' => $tasks[1] ?? [],
    ];
    $cacheability->addCacheContexts(['url.path', 'url.site']);
    $cacheability->applyTo($navigation);
    $cacheability->applyTo($view->element);
    $view->attachment_before['aculta_forum_front_navigation'] = $navigation;
  }

}
