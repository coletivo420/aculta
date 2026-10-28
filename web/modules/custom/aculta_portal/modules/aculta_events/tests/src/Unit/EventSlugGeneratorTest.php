<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit;

use Drupal\aculta_events\Slug\EventSlugGenerator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Transliteração do título para slug público (FRIENDLY-PORTUGUESE-SLUGS.md).
 */
#[Group('aculta_events')]
class EventSlugGeneratorTest extends UnitTestCase {

  /**
   * Títulos e slugs esperados.
   *
   * @return array<string, array{string, string}>
   */
  public static function titulos(): array {
    return [
      'acentos e maiúsculas' => ['Roda de Conversa', 'roda-de-conversa'],
      'cedilha e til' => ['Formação & Saúde', 'formacao-saude'],
      'pontuação' => ['Oficina: arte, gênero!', 'oficina-arte-genero'],
      'espaços repetidos' => ['  Feira   do   Livro  ', 'feira-do-livro'],
      'só símbolos cai no padrão' => ['!!!', 'evento'],
      'vazio cai no padrão' => ['', 'evento'],
    ];
  }

  /**
   * Título vira slug em português, ASCII, minúsculo, com hífen.
   */
  #[DataProvider('titulos')]
  public function testSlugDoTitulo(string $titulo, string $esperado): void {
    $this->assertSame($esperado, EventSlugGenerator::fromTitle($titulo));
  }

}
