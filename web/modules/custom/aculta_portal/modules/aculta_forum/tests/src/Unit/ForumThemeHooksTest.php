<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\ForumThemeHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contrato do registro das categorias após a migração para classe de hook.
 */
#[Group('aculta_forum')]
final class ForumThemeHooksTest extends UnitTestCase {

  /**
   * A apresentação de categorias é delegada ao row plugin UI Patterns.
   */
  public function testCategoriesDoNotRegisterLegacyUnformattedTemplate(): void {
    $theme = (new ForumThemeHooks())->theme([], 'module', 'aculta_forum', '/modules/custom/aculta_forum');

    $key = 'views_view_unformatted__aculta_forum_categories__page_categories';
    $this->assertArrayNotHasKey($key, $theme);
    $this->assertArrayHasKey('discussion_reply_composer', $theme);
  }

}
