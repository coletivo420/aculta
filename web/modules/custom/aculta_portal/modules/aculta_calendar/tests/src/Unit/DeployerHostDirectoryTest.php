<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Unit;

use Drupal\aculta_calendar\Embed\DeployerHostDirectory;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the read-only view of hosts mapped by ACULTA Deployer.
 */
#[Group('aculta_calendar')]
final class DeployerHostDirectoryTest extends UnitTestCase {

  /**
   * Verifies only unambiguous dns host mappings are listed.
   */
  public function testOnlyUnambiguousDnsHostMappingsAreListed(): void {
    $data = [
      'domain_transition' => [
        'entries' => [
          ['domain_id' => 'main', 'test_host' => 'MAIN.test.invalid', 'production_host' => 'main.invalid'],
          ['domain_id' => 'events', 'test_host' => 'events.test.invalid', 'production_host' => 'events.invalid'],
          ['domain_id' => 'bad', 'test_host' => '*.test.invalid', 'production_host' => 'bad.invalid'],
          ['domain_id' => 'duplicate', 'test_host' => 'one.test.invalid', 'production_host' => 'one.invalid'],
          ['domain_id' => 'duplicate', 'test_host' => 'two.test.invalid', 'production_host' => 'two.invalid'],
        ],
      ],
      'unrelated' => ['test_host' => 'not-listed.invalid'],
    ];

    self::assertSame([
      ['domain_id' => 'events', 'test_host' => 'events.test.invalid', 'production_host' => 'events.invalid'],
      ['domain_id' => 'main', 'test_host' => 'main.test.invalid', 'production_host' => 'main.invalid'],
    ], DeployerHostDirectory::mappingsFromData($data));
  }

  /**
   * Verifies repository mapping contains ten domains across two environments.
   */
  public function testRepositoryMappingContainsTenDomainsAcrossTwoEnvironments(): void {
    $extensions = $this->createMock(ModuleExtensionList::class);
    $extensions->method('getPath')->with('aculta_deployer')->willReturn(dirname(__DIR__, 3) . '/../aculta_deployer');
    $directory = new DeployerHostDirectory($extensions);

    self::assertCount(10, $directory->mappings());
    self::assertCount(20, $directory->hosts());
  }

}
