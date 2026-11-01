<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Deployer\DeployerStatusReader;
use Drupal\Tests\UnitTestCase;

/**
 * Leitura do relatório neutro do deployer (var/deployer/status.json).
 */
#[\PHPUnit\Framework\Attributes\Group('aculta_portal')]
final class DeployerStatusReaderTest extends UnitTestCase {

  private string $base;

  protected function setUp(): void {
    parent::setUp();
    $this->base = sys_get_temp_dir() . '/aculta-reader-' . bin2hex(random_bytes(6));
    mkdir($this->base . '/web', 0700, TRUE);
    mkdir($this->base . '/var/deployer', 0700, TRUE);
  }

  protected function tearDown(): void {
    @unlink($this->base . '/var/deployer/status.json');
    @rmdir($this->base . '/var/deployer');
    @rmdir($this->base . '/var');
    @rmdir($this->base . '/web');
    @rmdir($this->base);
    parent::tearDown();
  }

  public function testMissingFileIsUnavailable(): void {
    $this->assertNull((new DeployerStatusReader($this->base . '/web'))->read());
  }

  public function testSchemaOneIsReadAndOtherSchemaRejected(): void {
    $file = $this->base . '/var/deployer/status.json';
    file_put_contents($file, json_encode(['schema' => 1, 'tool' => 'ferramenta 0.1.6']));
    $this->assertSame('ferramenta 0.1.6', (new DeployerStatusReader($this->base . '/web'))->read()['tool']);
    file_put_contents($file, json_encode(['schema' => 2]));
    $this->assertNull((new DeployerStatusReader($this->base . '/web'))->read());
    file_put_contents($file, 'não é json');
    $this->assertNull((new DeployerStatusReader($this->base . '/web'))->read());
  }

}
