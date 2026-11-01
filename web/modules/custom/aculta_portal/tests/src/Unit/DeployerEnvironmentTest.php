<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Captcha\TurnstileKeyOverride;
use Drupal\aculta_portal\Environment\DeployerEnvironment;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests environment resolution and Turnstile key selection without a Kernel.
 */
#[Group('aculta_portal')]
final class DeployerEnvironmentTest extends UnitTestCase {

  /**
   * Temporary project root used by Deployer fixtures. */
  private string $projectRoot;

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $this->projectRoot = sys_get_temp_dir() . '/aculta-env-' . bin2hex(random_bytes(4));
    mkdir($this->projectRoot . '/var/deployer', 0777, TRUE);
    mkdir($this->projectRoot . '/web', 0777, TRUE);
  }

  /**
   * {@inheritdoc} */
  protected function tearDown(): void {
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($this->projectRoot, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
      $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($this->projectRoot);
    parent::tearDown();
  }

  /**
   * Writes a Deployer environment fixture. */
  private function writeEnvironment(string $value): void {
    $data = [
      'schema' => 1,
      'environment' => $value,
      'site' => 'https://example.invalid',
      'changed_at' => 'x',
    ];
    file_put_contents(
      $this->projectRoot . '/var/deployer/environment.json',
      json_encode($data),
    );
  }

  /**
   * Writes a small Deployer domain mapping fixture. */
  private function writeDomainMap(): void {
    $directory = $this->projectRoot . '/web/modules/custom/aculta_portal/modules/aculta_deployer/config';
    mkdir($directory, 0777, TRUE);
    file_put_contents($directory . '/deploy.json', json_encode([
      'domain_transition' => [
        'entries' => [
          [
            'domain_id' => 'eventos_aculta_org',
            'test_host' => 'eventos.test.invalid',
            'production_host' => 'eventos.example.org',
          ],
        ],
      ],
    ]));
  }

  /**
   * Tests that absent or invalid environments use a safe fallback. */
  public function testFallbackWhenNoFileOrInvalidValue(): void {
    $this->assertSame('production', DeployerEnvironment::current($this->projectRoot));
    $this->writeEnvironment('development');
    $this->assertSame('production', DeployerEnvironment::current($this->projectRoot, 'production'));
  }

  /**
   * Tests that the environment file is read. */
  public function testReadsDeployerValue(): void {
    $this->writeEnvironment('test');
    $this->assertSame('test', DeployerEnvironment::current($this->projectRoot));
  }

  /**
   * Tests that test and production hosts come from the Deployer mapping. */
  public function testDomainHostUsesTheDeployerMappingForEachEnvironment(): void {
    $this->writeDomainMap();
    $this->writeEnvironment('test');
    $this->assertSame('eventos.test.invalid', DeployerEnvironment::domainHost($this->projectRoot, 'eventos_aculta_org'));
    $this->writeEnvironment('production');
    $this->assertSame('eventos.example.org', DeployerEnvironment::domainHost($this->projectRoot, 'eventos_aculta_org'));
    $this->assertNull(DeployerEnvironment::domainHost($this->projectRoot, 'unknown_domain'));
  }

  /**
   * Tests that Turnstile key selection follows the site environment. */
  public function testProductionKeyByDefaultAndTestKeyOnTestEnvironment(): void {
    $override = new TurnstileKeyOverride($this->projectRoot . '/web');
    $this->assertSame(['turnstile.settings' => ['keys' => 'turnstile']], $override->loadOverrides(['turnstile.settings']));
    $this->writeEnvironment('test');
    $this->assertSame(['turnstile.settings' => ['keys' => 'turnstile_test']], $override->loadOverrides(['turnstile.settings']));
    $this->assertSame([], $override->loadOverrides(['system.site']));
  }

}
