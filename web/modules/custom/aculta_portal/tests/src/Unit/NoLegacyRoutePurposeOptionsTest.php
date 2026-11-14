<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Prevents removed purpose route metadata from returning to active code.
 */
#[Group('aculta_portal')]
final class NoLegacyRoutePurposeOptionsTest extends UnitTestCase {

  /**
   * Checks custom source, route definitions, tests, and Config Sync.
   */
  public function testRemovedRouteMetadataHasNoActiveConsumers(): void {
    $root = dirname(__DIR__, 7);
    $scanRoots = [
      $root . '/web/modules/custom',
      $root . '/web/themes/custom',
      $root . '/config/sync',
      $root . '/tests',
      $root . '/scripts',
    ];
    $excluded = [
      'web/modules/custom/aculta_portal/src/Domain/DomainRoutePolicy.php',
      'web/modules/custom/aculta_portal/tests/src/Unit/NoLegacyRoutePurposeOptionsTest.php',
    ];
    $extensions = ['inc', 'install', 'js', 'module', 'php', 'theme', 'yml', 'yaml'];
    $removed = [
      '_aculta_domain_purpose',
      '_aculta_domain_purposes',
      '_aculta_cross_domain_canonical_purpose',
      'aculta_domain_purpose_test_relaxed',
      'allowsSharedPurpose',
      'purposeForDonationRoute',
    ];
    $violations = [];

    foreach ($scanRoots as $scanRoot) {
      if (!is_dir($scanRoot)) {
        continue;
      }
      $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanRoot, RecursiveDirectoryIterator::SKIP_DOTS),
      );
      foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || !in_array($file->getExtension(), $extensions, TRUE)) {
          continue;
        }
        $relative = substr($file->getPathname(), strlen($root) + 1);
        if (in_array($relative, $excluded, TRUE)) {
          continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (!is_string($contents)) {
          $violations[] = $relative . ' could not be read';
          continue;
        }
        foreach ($removed as $token) {
          if (str_contains($contents, $token)) {
            $violations[] = $relative . ' still contains ' . $token;
          }
        }
      }
    }
    self::assertSame([], $violations);
  }

}
