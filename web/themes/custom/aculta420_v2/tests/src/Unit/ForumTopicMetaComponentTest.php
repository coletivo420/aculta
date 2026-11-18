<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Tests\UnitTestCase;

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

}
