<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Contrato CA0: o calendário não importa classes de quem fornece os dados.
 */
#[Group('aculta_calendar')]
final class IsolamentoTest extends TestCase {

  /**
   * Nenhum arquivo de src/ referencia os módulos donos de dados.
   */
  public function testNaoImportaModuloDonoDeDados(): void {
    $raiz = dirname(__DIR__, 3) . '/src';
    $this->assertDirectoryExists($raiz);

    $arquivos = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS),
    );
    $verificados = 0;
    foreach ($arquivos as $arquivo) {
      if ($arquivo->getExtension() !== 'php') {
        continue;
      }
      $codigo = (string) file_get_contents($arquivo->getPathname());
      $this->assertStringNotContainsString('Drupal\aculta_events', $codigo, $arquivo->getPathname());
      $this->assertStringNotContainsString('Drupal\aculta_lms_agenda', $codigo, $arquivo->getPathname());
      $verificados++;
    }
    $this->assertGreaterThan(0, $verificados, 'nenhum arquivo PHP encontrado');
  }

}
