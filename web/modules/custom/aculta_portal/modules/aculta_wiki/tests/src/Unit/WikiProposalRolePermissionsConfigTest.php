<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Protects the versioned role grants required by private Wiki proposals.
 */
#[Group('aculta_wiki')]
final class WikiProposalRolePermissionsConfigTest extends UnitTestCase {

  /**
   * The exported role config retains the Wiki proposal permissions.
   */
  public function testProposalPermissionsRemainAssignedToTheirRoles(): void {
    $moduleRoot = dirname(__DIR__, 3);
    $projectRoot = dirname($moduleRoot, 6);
    $rolePermissions = [
      'email_confirmed' => [
        'submit wiki proposals',
        'view own wiki proposals',
      ],
      'content_editor' => [
        'view any wiki proposals',
        'moderate wiki proposals',
      ],
    ];

    foreach ($rolePermissions as $role => $expectedPermissions) {
      $path = $projectRoot . '/config/sync/user.role.' . $role . '.yml';
      self::assertFileExists($path);
      $config = Yaml::parseFile($path);

      self::assertContains('aculta_wiki', $config['dependencies']['module'] ?? []);
      foreach ($expectedPermissions as $permission) {
        self::assertContains($permission, $config['permissions'] ?? [], sprintf('%s must retain %s.', $role, $permission));
      }
    }
  }

}
