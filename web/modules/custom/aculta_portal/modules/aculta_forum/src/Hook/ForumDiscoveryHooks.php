<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Event\ForumSearchEvent;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\ViewExecutable;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Emite a busca executada sem termos nem identificadores de pessoa.
 *
 * O Statistics só grava quando `forum_discovery.enabled` estiver ligado; a
 * auditoria de LGPD, bots, cache e consentimento precede a ativação. Não há
 * beacon de visualização, clique ou leitura nesta fase.
 */
final class ForumDiscoveryHooks {

  /**
   * View de busca pública do Fórum.
   */
  private const SEARCH_VIEW = 'aculta_forum_search';

  public function __construct(
    private readonly EventDispatcherInterface $dispatcher,
  ) {}

  /**
   * Implements hook_views_post_execute().
   */
  #[Hook('views_post_execute')]
  public function viewsPostExecute(ViewExecutable $view): void {
    if ($view->id() !== self::SEARCH_VIEW) {
      return;
    }
    $this->dispatcher->dispatch(
      new ForumSearchEvent((int) $view->total_rows),
      ForumSearchEvent::EXECUTED,
    );
  }

}
