<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Valida a configuração versionada do bloco da barra institucional.
 *
 * Garante que a barra institucional esteja presente no Config Sync,
 * atribuída à região 'institution' do tema 'aculta420_v2', sem duplicações.
 */
final class InstitutionBarConfigSyncTest extends UnitTestCase {

  /**
   * Testa a integridade do arquivo versionado em config/sync.
   */
  public function testInstitutionBarConfigInSync(): void {
    $root = dirname(__DIR__, 7);
    $filePath = $root . '/config/sync/block.block.aculta420_v2_institution_bar.yml';

    self::assertFileExists($filePath, 'O arquivo block.block.aculta420_v2_institution_bar.yml deve existir em config/sync.');

    $content = (string) file_get_contents($filePath);
    $data = Yaml::parse($content);

    self::assertIsArray($data);
    self::assertSame('aculta420_v2_institution_bar', $data['id'] ?? NULL);
    self::assertSame('aculta420_v2', $data['theme'] ?? NULL);
    self::assertSame('institution', $data['region'] ?? NULL);
    self::assertSame('aculta_institution_bar', $data['plugin'] ?? NULL);
    self::assertTrue($data['status'] ?? FALSE);
  }

}
