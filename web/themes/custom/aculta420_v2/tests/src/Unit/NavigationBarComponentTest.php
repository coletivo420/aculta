<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Core\Template\Attribute;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

/**
 * Checks the navigation-bar component's empty-menu contract.
 */
#[CoversNothing]
#[Group('aculta420_v2')]
final class NavigationBarComponentTest extends UnitTestCase {

  /**
   * An absent shared menu does not emit an empty panel or toggle.
   */
  public function testEmptyMenuOmitsPanelAndToggle(): void {
    $componentDirectory = dirname(__DIR__, 3) . '/components/navigation-bar';
    $twig = new Environment(new FilesystemLoader($componentDirectory));
    $twig->addFilter(new TwigFilter('t', static fn (string $value): string => $value));

    $html = $twig->render('navigation-bar.twig', [
      'attributes' => new Attribute(),
      'brand' => '<a href="/">Conta</a>',
      'menu_items' => [],
    ]);

    self::assertStringNotContainsString('data-aculta-navigation-toggle', $html);
    self::assertStringNotContainsString('data-aculta-navigation-panel', $html);
  }

}
