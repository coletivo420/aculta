<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

require_once dirname(__DIR__, 3) . '/src/Deployer/DeployerStatusReader.php';

use Drupal\aculta_portal\Deployer\DeployerStatusReader;
use Drupal\Tests\UnitTestCase;

/**
 * Leitura do relatório neutro do deployer (var/deployer/status.json).
 */
#[\PHPUnit\Framework\Attributes\Group('aculta_portal')]
final class DeployerStatusReaderTest extends UnitTestCase {

  /**
   * Caminho base do diretório temporário usado pelo teste.
   *
   * @var string
   */
  private string $base;

  /**
   *Cria os diretórios temporários usados pelo teste.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->base = sys_get_temp_dir() . '/aculta-reader-' . bin2hex(random_bytes(6));
    mkdir($this->base . '/web', 0700, TRUE);
    mkdir($this->base . '/var/deployer', 0700, TRUE);
    mkdir($this->base . '/var/runtime-sync', 0700, TRUE);
  }

  /**
   * Remove os diretórios temporários criados pelo teste.
   */
  protected function tearDown(): void {
    @unlink($this->base . '/var/deployer/status.json');
    @unlink($this->base . '/var/runtime-sync/state.json');
    @unlink($this->base . '/var/runtime-sync/latest.json');
    @rmdir($this->base . '/var/deployer');
    @rmdir($this->base . '/var/runtime-sync');
    @rmdir($this->base . '/var');
    @rmdir($this->base . '/web');
    @rmdir($this->base);
    parent::tearDown();
  }

  /**
   * Confirma que a ausência do arquivo resulta em status indisponível.
   */
  public function testMissingFileIsUnavailable(): void {
    $reader = new DeployerStatusReader($this->base . '/web');
    $this->assertNull($reader->read());
    $this->assertNull($reader->readRuntimeSync());
  }

  /**
   * Confirma que o esquema 1 é lido e outros esquemas são rejeitados.
   */
  public function testSchemaOneIsReadAndOtherSchemaRejected(): void {
    $file = $this->base . '/var/deployer/status.json';
    file_put_contents($file, json_encode(['schema' => 1, 'tool' => 'ferramenta 0.1.6']));
    $this->assertSame('ferramenta 0.1.6', (new DeployerStatusReader($this->base . '/web'))->read()['tool']);
    file_put_contents($file, json_encode(['schema' => 2]));
    $this->assertNull((new DeployerStatusReader($this->base . '/web'))->read());
    file_put_contents($file, 'não é json');
    $this->assertNull((new DeployerStatusReader($this->base . '/web'))->read());
  }

  /**
   * Confirma leitura somente leitura do Runtime Sync v2 (schema_version 2) e rejeição de symlink.
   */
  public function testRuntimeSyncSchemaTwoIsReadAndSymlinkRejected(): void {
    $stateFile = $this->base . '/var/runtime-sync/state.json';
    $latestFile = $this->base . '/var/runtime-sync/latest.json';
    file_put_contents($stateFile, json_encode([
      'schema_version' => 2,
      'checkout_sha' => '43f16778ea853b4426f51f924c618da558c8c9da',
      'last_validated_sha' => '43f16778ea853b4426f51f924c618da558c8c9da',
      'last_operation' => ['outcome' => 'VALIDATED', 'risk' => 'R1'],
    ]));
    file_put_contents($latestFile, json_encode([
      'schema_version' => 2,
      'gates' => [['id' => 'drush-cache-rebuild', 'result' => 'PASS', 'reason' => 'ok']],
    ]));
    $reader = new DeployerStatusReader($this->base . '/web');
    $sync = $reader->readRuntimeSync();
    $this->assertIsArray($sync);
    $this->assertSame('VALIDATED', $sync['last_operation']['outcome']);
    $this->assertSame('drush-cache-rebuild', $sync['latest_report']['gates'][0]['id']);

    // Esquema antigo ou inválido deve retornar NULL.
    file_put_contents($stateFile, json_encode(['schema_version' => 1]));
    $this->assertNull($reader->readRuntimeSync());

    // Symlink deve ser recusado.
    unlink($stateFile);
    symlink($latestFile, $stateFile);
    $this->assertNull($reader->readRuntimeSync());
  }

}
