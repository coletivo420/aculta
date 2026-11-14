<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\ForumThemeHooks;
use Drupal\aculta_user\Avatar\AvatarResolver;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
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
    $hooks = new ForumThemeHooks(
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(AvatarResolver::class),
      // Classe final: o teste só usa theme(), então a instância sem construtor basta.
      (new \ReflectionClass(AuthorCardBuilder::class))->newInstanceWithoutConstructor(),
    );
    $theme = $hooks->theme([], 'module', 'aculta_forum', '/modules/custom/aculta_forum');

    $key = 'views_view_unformatted__aculta_forum_categories__page_categories';
    $this->assertArrayNotHasKey($key, $theme);
    $this->assertArrayHasKey('discussion_reply_composer', $theme);
    $this->assertArrayNotHasKey('discussion_reaction_picker', $theme);
  }

}
