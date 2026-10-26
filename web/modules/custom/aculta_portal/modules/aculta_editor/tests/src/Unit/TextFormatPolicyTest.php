<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_editor\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Política de HTML dos formatos de texto exportados: nenhum formato libera tag ou atributo executável.
 *
 * Lê os arquivos filter.format.*.yml de config/sync, sem Drupal. Falha se alguém incluir script, iframe, object,
 * embed, style ou qualquer atributo de evento (on*) numa lista allowed_html.
 */
#[CoversNothing]
#[Group('aculta_editor')]
class TextFormatPolicyTest extends UnitTestCase {

  /** Tags que nunca podem aparecer numa lista allowed_html. */
  private const FORBIDDEN_TAGS = ['script', 'iframe', 'object', 'embed', 'style', 'frame', 'frameset', 'applet', 'meta', 'base'];

  /**
   * Formatos exportados com allowed_html, indexados pelo nome do arquivo.
   *
   * @return array<string, array{string}>
   *   Caminho do arquivo de cada formato.
   */
  public static function formatFileProvider(): array {
    $dir = dirname(__DIR__, 9) . '/config/sync';
    $cases = [];
    foreach (glob($dir . '/filter.format.*.yml') ?: [] as $file) {
      $cases[basename($file)] = [$file];
    }
    return $cases;
  }

  /**
   * Nenhum formato libera tag executável.
   */
  #[DataProvider('formatFileProvider')]
  public function testNoForbiddenTagsAllowed(string $file): void {
    $allowed = self::allowedHtml($file);
    if ($allowed === NULL) {
      $this->addToAssertionCount(1);
      return;
    }
    foreach (self::FORBIDDEN_TAGS as $tag) {
      $this->assertDoesNotMatchRegularExpression('/<' . $tag . '[\s>]/i', $allowed, basename($file) . " libera <$tag>.");
    }
  }

  /**
   * Nenhum formato libera atributo de evento (on*) nem estilo inline.
   */
  #[DataProvider('formatFileProvider')]
  public function testNoEventOrStyleAttributesAllowed(string $file): void {
    $allowed = self::allowedHtml($file);
    if ($allowed === NULL) {
      $this->addToAssertionCount(1);
      return;
    }
    $this->assertDoesNotMatchRegularExpression('/\s(on[a-z]+|style)[\s>]/i', $allowed, basename($file) . ' libera atributo de evento ou style.');
  }

  /**
   * allowed_html do formato, ou NULL quando o formato não tem filtro de HTML.
   */
  private static function allowedHtml(string $file): ?string {
    $data = Yaml::parseFile($file);
    $filters = $data['filters'] ?? [];
    $html = $filters['filter_html']['settings']['allowed_html'] ?? NULL;
    return is_string($html) ? $html : NULL;
  }

}
