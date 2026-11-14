<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mcp\Unit\Hook;

use Drupal\aculta_mcp\Hook\ComposerVersionMetadataHooks;
use Drupal\Core\Extension\Extension;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Composer version metadata for the Simple OAuth module.
 */
#[CoversClass(ComposerVersionMetadataHooks::class)]
#[Group('aculta_mcp')]
final class ComposerVersionMetadataHooksTest extends UnitTestCase {

  /**
   * Provides the branch version format Drupal's dependency checker expects.
   */
  public function testComposerBranchVersionIsComparable(): void {
    self::assertSame('6.1.x-dev', ComposerVersionMetadataHooks::versionFromComposerBranch('dev-6.1.x'));
    self::assertNull(ComposerVersionMetadataHooks::versionFromComposerBranch(NULL));
    self::assertNull(ComposerVersionMetadataHooks::versionFromComposerBranch('dev-feature-branch'));
    self::assertTrue(version_compare(
      ComposerVersionMetadataHooks::versionFromComposerBranch('dev-6.1.x') ?? '',
      '6.0.3',
      '>=',
    ));
  }

  /**
   * Does not override unrelated extension metadata.
   */
  public function testOtherExtensionsKeepTheirMetadata(): void {
    $extension = new Extension(DRUPAL_ROOT, 'module', 'modules/custom/aculta_mcp/aculta_mcp.info.yml');
    $info = ['version' => '1.0.0'];
    (new ComposerVersionMetadataHooks())->alterSystemInfo($info, $extension, 'module');
    self::assertSame('1.0.0', $info['version']);
  }

  /**
   * Adds the locked Simple OAuth branch version when source metadata omits it.
   */
  public function testSimpleOauthReceivesInstalledComposerVersion(): void {
    $extension = new Extension(DRUPAL_ROOT, 'module', 'modules/contrib/simple_oauth/simple_oauth.info.yml');
    $info = [];
    (new ComposerVersionMetadataHooks())->alterSystemInfo($info, $extension, 'module');
    self::assertSame('6.1.x-dev', $info['version'] ?? NULL);
  }

}
