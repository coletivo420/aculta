<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\ForumThemeHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contrato do registro do agrupamento de categorias do fórum, após a migração para classe de hook.
 */
#[Group('aculta_forum')]
final class ForumThemeHooksTest extends UnitTestCase {

  /**
   * O caminho do template é derivado do caminho do módulo recebido pelo hook.
   */
  public function testCategoriesTemplateUsesModulePath(): void {
    $theme = (new ForumThemeHooks())->theme([], 'module', 'aculta_forum', '/modules/custom/aculta_forum');

    $key = 'views_view_unformatted__aculta_forum_categories__page_categories';
    $this->assertArrayHasKey($key, $theme);
    $this->assertSame('/modules/custom/aculta_forum/templates', $theme[$key]['path']);
    $this->assertSame('views-view-unformatted--aculta-forum-categories--page-categories', $theme[$key]['template']);
    $this->assertSame('views_view_unformatted', $theme[$key]['base hook']);
  }

}
