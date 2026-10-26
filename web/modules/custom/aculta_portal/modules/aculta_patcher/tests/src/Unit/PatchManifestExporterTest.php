<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_patcher\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\aculta_patcher\PatchManifestExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests safe output handling for the patch manifest exporter.
 */
#[CoversClass(PatchManifestExporter::class)]
#[Group('aculta_patcher')]
final class PatchManifestExporterTest extends TestCase {

  /**
   * Temporary project root used by exporter tests.
   */
  private string $projectRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->projectRoot = sys_get_temp_dir() . '/aculta-patcher-test-' . bin2hex(random_bytes(8));
    mkdir($this->projectRoot . '/web', 0755, TRUE);
    file_put_contents($this->projectRoot . '/composer.lock', '{"packages": [], "packages-dev": []}');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ([$this->projectRoot, $this->projectRoot . '-outside'] as $root) {
      if (is_dir($root)) {
        $iterator = new \RecursiveIteratorIterator(
          new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
          \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
          $path = $item->getPathname();
          if ($item->isLink() || $item->isFile()) {
            unlink($path);
          }
          else {
            rmdir($path);
          }
        }
        rmdir($root);
      }
    }
    parent::tearDown();
  }

  /**
   * A symlinked parent cannot redirect generated patches outside the project.
   */
  public function testRejectsSymlinkedPatchesParent(): void {
    $outside = $this->projectRoot . '-outside';
    mkdir($outside);
    $this->assertTrue(symlink($outside, $this->projectRoot . '/patches'));

    try {
      $this->exporter()->export();
      $this->fail('Export must reject a symlinked patches directory.');
    }
    catch (\RuntimeException $exception) {
      $this->assertStringContainsString('patches', $exception->getMessage());
    }

    $this->assertDirectoryDoesNotExist($outside . '/aculta_patcher');
    $this->assertFileDoesNotExist($this->projectRoot . '/patches-aculta.json');
    rmdir($outside);
  }

  /**
   * An empty export creates artifacts only below the project root.
   */
  public function testEmptyExportCreatesProjectLocalArtifacts(): void {
    $message = $this->exporter()->export();

    $this->assertSame('0 patch(es) exportado(s). Revise patches-aculta.json e patches/aculta_patcher/ no Git antes de composer install.', $message);
    $this->assertDirectoryExists($this->projectRoot . '/patches/aculta_patcher');
    $this->assertFileExists($this->projectRoot . '/patches-aculta.json');
  }

  /**
   * Builds an exporter with no active patch entities.
   */
  private function exporter(): PatchManifestExporter {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn([]);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('aculta_patch')->willReturn($storage);

    return new PatchManifestExporter(
      $entityTypeManager,
      $this->projectRoot . '/web',
      $this->createMock(FileSystemInterface::class),
    );
  }

}
