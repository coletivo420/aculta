<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Core\Template\Attribute;
use Drupal\Tests\UnitTestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

/**
 * Protects the accessible terms in the forum topic statistics list.
 *
 * @group aculta420_v2
 */
final class ForumTopicMetaComponentTest extends UnitTestCase {

  /**
   * Every definition term has a text name in addition to its decorative icon.
   */
  public function testStatisticsHaveAccessibleDefinitionTerms(): void {
    $themeDir = dirname(__DIR__, 3);
    $template = (string) file_get_contents($themeDir . '/components/forum-topic-meta/forum-topic-meta.twig');
    self::assertSame(4, preg_match_all('/<dt>(.*?)<\/dt>/s', $template, $matches));

    $terms = $matches[1];
    self::assertCount(4, $terms);
    foreach (['posts', 'participantes', 'visualizações', 'última atividade'] as $index => $label) {
      self::assertStringContainsString('<span class="visually-hidden">', $terms[$index]);
      self::assertStringContainsString("'{$label}'|t", $terms[$index]);
      self::assertStringContainsString("include('aculta420_v2:icon'", $terms[$index]);
    }

    $iconTemplate = (string) file_get_contents($themeDir . '/components/icon/icon.twig');
    self::assertStringContainsString('aria-hidden="true"', $iconTemplate, 'The glyph remains decorative; the definition term supplies its accessible name.');
  }

  /**
   * Verifies rendered dt text is present while the glyph remains hidden.
   */
  public function testRenderedDefinitionTermsAreNamed(): void {
    $themeDir = dirname(__DIR__, 3);
    $twig = new Environment(new ArrayLoader([
      'forum-topic-meta' => (string) file_get_contents($themeDir . '/components/forum-topic-meta/forum-topic-meta.twig'),
      'aculta420_v2:icon' => '<svg aria-hidden="true"></svg>',
    ]));
    $twig->addFilter(new TwigFilter('t', static fn (string $message): string => $message));

    $html = $twig->render('forum-topic-meta', [
      'attributes' => new Attribute(),
      'title' => 'Tópico de exemplo',
      'theme' => 'light',
      'density' => 'default',
      'status' => 'open',
      'posts' => 18,
      'participants' => 6,
      'views' => 342,
      'last_activity' => 'Há pouco',
    ]);

    foreach (['posts', 'participantes', 'visualizações', 'última atividade'] as $label) {
      $pattern = '/<dt>\s*<span class="visually-hidden">' . preg_quote($label, '/') . '<\/span>\s*<svg aria-hidden="true"><\/svg>\s*<\/dt>/u';
      self::assertMatchesRegularExpression($pattern, $html);
    }
  }

}
