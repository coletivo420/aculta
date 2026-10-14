<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\Hook;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Listagem pública de álbuns: só aparece no purpose magazine (decisão D13).
 *
 * A consulta da view de álbuns recebe uma condição que nunca é verdadeira fora da Revista. Assim o título
 * de um álbum não vaza para outro host, nem pelo HTML da listagem nem pelo cache.
 */
final class GalleryListingHooks {

  /** Id da view de álbuns (config/sync/views.view.aculta_albuns.yml). */
  private const VIEW_ID = 'aculta_albuns';

  /** Purpose onde álbuns são publicados. */
  private const PURPOSE = 'magazine';

  public function __construct(
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $purposeManager,
  ) {}

  /**
   * Implements hook_views_query_alter().
   */
  #[Hook('views_query_alter')]
  public function viewsQueryAlter(ViewExecutable $view, QueryPluginBase $query): void {
    if ($view->id() !== self::VIEW_ID || $this->purposeManager->getCurrentPurpose() === self::PURPOSE) {
      return;
    }
    if ($query instanceof Sql) {
      $query->addWhereExpression(0, '1 = 0');
    }
  }

}
