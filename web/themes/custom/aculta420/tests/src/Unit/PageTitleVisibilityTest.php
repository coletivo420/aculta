<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Markup;
use Twig\TwigFilter;

/**
 * Tests purpose-specific visibility of the shared Drupal page-title block.
 */
#[Group('aculta420')]
final class PageTitleVisibilityTest extends UnitTestCase {

  /**
   * Provides purpose homes and whether their title block should be rendered.
   *
   * @return iterable<string, array{string, bool}>
   *   Purpose and expected page-title visibility.
   */
  public static function homePurposeProvider(): iterable {
    yield 'main home keeps its content-owned heading' => ['main', FALSE];
    yield 'account home keeps its content-owned heading' => ['account', FALSE];
    yield 'support home displays the Drupal page title' => ['support', TRUE];
    yield 'magazine home keeps its content-owned heading' => ['magazine', FALSE];
    yield 'wiki home keeps its content-owned heading' => ['wiki', FALSE];
    yield 'shop home keeps its content-owned heading' => ['shop', FALSE];
    yield 'courses home displays the Drupal page title' => ['courses', TRUE];
    yield 'forum home keeps its content-owned heading' => ['forum', FALSE];
    yield 'links home keeps its content-owned heading' => ['links', FALSE];
    yield 'events home keeps its content-owned heading' => ['eventos', FALSE];
  }

  /**
   * Checks that only the two public homes without their own H1 show page title.
   */
  #[DataProvider('homePurposeProvider')]
  public function testHomePageTitleVisibility(string $purpose, bool $expected): void {
    $output = $this->renderPage($purpose, TRUE);

    self::assertSame($expected, str_contains($output, '<h1>Rota title</h1>'));
  }

  /**
   * Non-home routes continue to display the Drupal page title in every purpose.
   */
  public function testNonHomeRouteKeepsPageTitle(): void {
    foreach (['main', 'support', 'courses'] as $purpose) {
      self::assertStringContainsString('<h1>Rota title</h1>', $this->renderPage($purpose, FALSE));
    }
  }

  /**
   * Renders the actual page template with a minimal Drupal-like context.
   */
  private function renderPage(string $purpose, bool $isHome): string {
    $themeRoot = dirname(__DIR__, 3);
    $template = file_get_contents($themeRoot . '/templates/page.html.twig');
    self::assertIsString($template);

    $twig = new Environment(new ArrayLoader([
      'page.html.twig' => $template,
      'aculta420:consent-banner' => '',
    ]));
    $twig->addFilter(new TwigFilter('t', static fn (string $value): string => $value));

    $empty = new Markup('', 'UTF-8');
    return $twig->render('page.html.twig', [
      'domain_presentation' => [
        'identity' => ['purpose' => $purpose, 'title' => strtoupper($purpose), 'home_url' => '/'],
        'regions' => [],
        'layout' => [
          'institution_bar_account' => FALSE,
          'show_utility_bar' => FALSE,
          'links_page' => FALSE,
          'show_site_header' => TRUE,
        ],
      ],
      'page' => [
        'header' => $empty,
        'content' => $empty,
        'footer' => $empty,
        'primary_menu' => $empty,
        'sidebar' => $empty,
      ],
      'aculta_page_title' => new Markup('<h1>Rota title</h1>', 'UTF-8'),
      'institutional_home' => $isHome,
      'aculta_show_page_title_on_front' => FALSE,
      'aculta_show_site_header' => TRUE,
      'aculta_links_page' => FALSE,
      'aculta_brand_home' => '/',
    ]);
  }

}
