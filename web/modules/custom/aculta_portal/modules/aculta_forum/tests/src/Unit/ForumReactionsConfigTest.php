<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Verifies the forum reactions configuration and schema.
 */
#[Group('aculta_forum')]
final class ForumReactionsConfigTest extends UnitTestCase {

  /**
   * Verifies default configuration and sync copies align.
   */
  public function testReactionsConfigurationMatches(): void {
    $root = dirname(__DIR__, 3);
    $installDirectory = $root . '/config/install';
    $repositoryRoot = dirname($root, 6);
    $settingsFile = 'aculta_forum.settings.yml';

    $installConfig = Yaml::parseFile($installDirectory . '/' . $settingsFile);
    $syncConfig = Yaml::parseFile($repositoryRoot . '/config/sync/' . $settingsFile);

    self::assertSame($installConfig, $syncConfig);
    self::assertArrayHasKey('reactions_enabled', $installConfig);
    self::assertTrue($installConfig['reactions_enabled']);
    self::assertSame('broccoli', $installConfig['default_reaction']);
    self::assertIsArray($installConfig['allowed_reactions']);

    $reactionIds = array_column($installConfig['allowed_reactions'], 'id');
    self::assertContains('broccoli', $reactionIds);
    self::assertContains('thumbs_up', $reactionIds);
    self::assertContains('seedling', $reactionIds);
    self::assertContains('herb', $reactionIds);
    self::assertContains('fire', $reactionIds);
    self::assertContains('clap', $reactionIds);
    self::assertContains('laugh', $reactionIds);
    self::assertContains('pray', $reactionIds);
  }

  /**
   * Verifies the permission configuration.
   */
  public function testReactionPermissions(): void {
    $root = dirname(__DIR__, 3);
    $permissions = Yaml::parseFile($root . '/aculta_forum.permissions.yml');

    self::assertArrayHasKey('react to aculta discussions', $permissions);
    self::assertSame('Reagir a comentários e publicações de discussões', $permissions['react to aculta discussions']['title']);

    $repositoryRoot = dirname($root, 6);
    $roleConfig = Yaml::parseFile($repositoryRoot . '/config/sync/user.role.email_confirmed.yml');
    self::assertContains('react to aculta discussions', $roleConfig['permissions']);
  }

}
