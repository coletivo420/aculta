<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Routing\LmsFriendlySlugs;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Transliteration\PhpTransliteration;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Derived LMS slugs are Portuguese, ASCII, lowercase and unique within scope.
 */
#[Group('aculta_lms')]
final class LmsFriendlySlugsTest extends UnitTestCase {

  private LmsFriendlySlugs $slugs;

  protected function setUp(): void {
    parent::setUp();
    $transliteration = $this->createMock(PhpTransliteration::class);
    $transliteration->method('transliterate')->willReturnCallback(
      static fn(string $string): string => strtr($string, [
        'ç' => 'c', 'Ç' => 'C', 'ã' => 'a', 'á' => 'a', 'â' => 'a', 'à' => 'a',
        'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ü' => 'u',
      ]),
    );
    $this->slugs = new LmsFriendlySlugs(
      $this->createMock(EntityTypeManagerInterface::class),
      $transliteration,
    );
  }

  #[DataProvider('titles')]
  public function testSlugifyProducesPortugueseAsciiSlugs(string $title, string $expected): void {
    $this->assertSame($expected, $this->slugs->slugify($title));
  }

  public static function titles(): array {
    return [
      'accents and spaces' => ['Proibicionismo e antiproibicionismo', 'proibicionismo-e-antiproibicionismo'],
      'cedilla and tilde' => ['Ação e direitos', 'acao-e-direitos'],
      'punctuation collapses' => ['Ação & Direitos!', 'acao-direitos'],
      'empty after cleanup falls back' => ['!!!', 'sem-titulo'],
      'empty input falls back' => ['', 'sem-titulo'],
    ];
  }

  public function testRepeatedTitlesGetSuffixesInStoredOrder(): void {
    $this->assertSame(
      ['a', 'a-2', 'a-3'],
      array_values($this->slugs->uniqueSlugs(['A', 'a', 'A'])),
    );
  }

  public function testSuffixSkipsSlugsAlreadyTaken(): void {
    $this->assertSame(
      ['x-2', 'x', 'x-3'],
      array_values($this->slugs->uniqueSlugs(['X 2', 'x', 'x'])),
    );
  }

  public function testUniqueSlugsKeepPositionKeys(): void {
    $this->assertSame(
      [0 => 'introducao', 2 => 'fontes'],
      $this->slugs->uniqueSlugs([0 => 'Introdução', 2 => 'Fontes']),
    );
  }

}
