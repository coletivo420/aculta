<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_patcher\Unit;

use Drupal\aculta_patcher\PatchDiffValidator;
use Drupal\aculta_patcher\PatchManifestExporter;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Exportador de patches contra projeto temporário.
 */
#[CoversClass(PatchManifestExporter::class)]
#[Group('aculta_patcher')]
class PatchManifestExporterTest extends UnitTestCase {

  /**
   * Raiz do projeto temporário criado para cada teste.
   */
  private string $project;

  /**
   * Diff válido: troca "world" por "ACULTA" em src/Greeting.txt.
   */
  private const VALID_DIFF = "diff --git a/src/Greeting.txt b/src/Greeting.txt\n--- a/src/Greeting.txt\n+++ b/src/Greeting.txt\n@@ -1,2 +1,2 @@\n hello\n-world\n+ACULTA\n";

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->project = sys_get_temp_dir() . '/aculta-patcher-' . bin2hex(random_bytes(6));
    mkdir($this->project . '/web', 0777, TRUE);
    mkdir($this->project . '/vendor/acme/lib/src', 0777, TRUE);
    file_put_contents($this->project . '/vendor/acme/lib/src/Greeting.txt', "hello\nworld\n");
    file_put_contents($this->project . '/composer.lock', json_encode([
      'packages' => [['name' => 'acme/lib', 'version' => '1.0.0']],
      'packages-dev' => [],
    ]));
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->removeTree($this->project);
    parent::tearDown();
  }

  /**
   * A symlinked parent cannot redirect generated patches outside the project.
   */
  public function testRejectsSymlinkedPatchesParent(): void {
    $outside = $this->project . '-outside';
    mkdir($outside);
    $this->assertTrue(symlink($outside, $this->project . '/patches'));

    try {
      $this->exporter([])->export();
      $this->fail('Export must reject a symlinked patches directory.');
    }
    catch (\RuntimeException $exception) {
      $this->assertStringContainsString('patches', $exception->getMessage());
    }

    $this->assertDirectoryDoesNotExist($outside . '/aculta_patcher');
    $this->assertFileDoesNotExist($this->project . '/patches-aculta.json');
    rmdir($outside);
  }

  /**
   * Rejects a package source reached through a symlinked parent directory.
   */
  public function testRejectsPackageSourceSymlinkParent(): void {
    $outside = $this->project . '/outside-source';
    mkdir($outside);
    file_put_contents($outside . '/Greeting.txt', "hello\nworld\n");
    symlink($outside, $this->project . '/vendor/acme/lib/src/linked');
    $diff = "diff --git a/src/linked/Greeting.txt b/src/linked/Greeting.txt\n--- a/src/linked/Greeting.txt\n+++ b/src/linked/Greeting.txt\n@@ -1,2 +1,2 @@\n hello\n-world\n+ACULTA\n";

    try {
      $this->exporter(['symlink' => $this->patch('symlink', '1.0.0', $diff)])->export(TRUE);
      $this->fail('Export must reject source paths that cross a symlink.');
    }
    catch (\RuntimeException $exception) {
      $this->assertStringContainsString('simbólico', $exception->getMessage());
    }
    $this->assertSame("hello\nworld\n", file_get_contents($outside . '/Greeting.txt'));
  }

  /**
   * Rejects a symlinked manifest before writing any patch artifact.
   */
  public function testRejectsManifestSymlinkBeforeWritingArtifacts(): void {
    $outside = $this->project . '/outside-manifest.json';
    file_put_contents($outside, 'keep');
    symlink($outside, $this->project . '/patches-aculta.json');

    try {
      $this->exporter(['ok' => $this->patch('ok', '1.0.0', self::VALID_DIFF)])->export();
      $this->fail('Export must reject a symlinked manifest.');
    }
    catch (\RuntimeException $exception) {
      $this->assertStringContainsString('manifesto', $exception->getMessage());
    }

    $this->assertSame('keep', file_get_contents($outside));
    $this->assertFileDoesNotExist($this->project . '/patches/aculta_patcher/ok.patch');
  }

  /**
   * Rejects a symlinked patch file before writing the manifest.
   */
  public function testRejectsArtifactSymlinkBeforeWritingManifest(): void {
    mkdir($this->project . '/patches/aculta_patcher', 0777, TRUE);
    $outside = $this->project . '/outside-patch';
    file_put_contents($outside, 'keep');
    symlink($outside, $this->project . '/patches/aculta_patcher/ok.patch');

    try {
      $this->exporter(['ok' => $this->patch('ok', '1.0.0', self::VALID_DIFF)])->export();
      $this->fail('Export must reject a symlinked patch artifact.');
    }
    catch (\RuntimeException $exception) {
      $this->assertStringContainsString('artefato', $exception->getMessage());
    }

    $this->assertSame('keep', file_get_contents($outside));
    $this->assertFileDoesNotExist($this->project . '/patches-aculta.json');
  }

  /**
   * An empty export creates artifacts only below the project root.
   */
  public function testEmptyExportCreatesProjectLocalArtifacts(): void {
    $message = $this->exporter([])->export();

    $this->assertStringContainsString('0 patch(es) exportado(s)', $message);
    $this->assertDirectoryExists($this->project . '/patches/aculta_patcher');
    $this->assertFileExists($this->project . '/patches-aculta.json');
  }

  /**
   * Exportador com patches e pacote apontando para o projeto temporário.
   *
   * @param array<string, object> $patches
   *   Patches simulados, indexados pelo id.
   */
  private function exporter(array $patches): PatchManifestExporter {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn($patches);
    $types = $this->createMock(EntityTypeManagerInterface::class);
    $types->method('getStorage')->willReturn($storage);
    $files = $this->createMock(FileSystemInterface::class);
    $files->method('getTempDirectory')->willReturn(sys_get_temp_dir());
    $root = $this->project;
    return new PatchManifestExporter(
      $types,
      $this->project . '/web',
      $files,
      new PatchDiffValidator(),
      static fn (string $package): ?string => $package === 'acme/lib' ? $root . '/vendor/acme/lib' : NULL,
    );
  }

  /**
   * Patch simulado com os metadados informados.
   */
  private function patch(string $id, string $version, string $diff, bool $active = TRUE): object {
    // AcultaPatch é final; o exportador usa somente estes métodos simulados.
    return new class($id, $version, $diff, $active) {

      public function __construct(
        private readonly string $id,
        private readonly string $version,
        private readonly string $diff,
        private readonly bool $active,
      ) {}

      /**
       * Simula id() do patch.
       */
      public function id(): string {
        return $this->id;
      }

      /**
       * Simula label() do patch.
       */
      public function label(): string {
        return 'Patch ' . $this->id;
      }

      /**
       * Simula status() do patch.
       */
      public function status(): bool {
        return $this->active;
      }

      /**
       * Simula getPackage() do patch.
       */
      public function getPackage(): string {
        return 'acme/lib';
      }

      /**
       * Simula getVersion() do patch.
       */
      public function getVersion(): string {
        return $this->version;
      }

      /**
       * Simula getPatch() do patch.
       */
      public function getPatch(): string {
        return $this->diff;
      }

    };
  }

  /**
   * Exporta um patch válido e registra seu hash no manifesto.
   */
  public function testExportWritesArtifactAndMatchingManifest(): void {
    $message = $this->exporter(['ok' => $this->patch('ok', '1.0.0', self::VALID_DIFF)])->export();
    $this->assertStringContainsString('1 patch(es) exportado(s)', $message);

    $artifact = $this->project . '/patches/aculta_patcher/ok.patch';
    $this->assertFileExists($artifact);
    $manifest = json_decode((string) file_get_contents($this->project . '/patches-aculta.json'), TRUE);
    $entry = $manifest['patches']['acme/lib'][0];
    $this->assertSame('patches/aculta_patcher/ok.patch', $entry['url']);
    $this->assertSame(hash_file('sha256', $artifact), $entry['sha256']);
  }

  /**
   * Verificação não grava nenhum arquivo.
   */
  public function testCheckOnlyWritesNothing(): void {
    $message = $this->exporter(['ok' => $this->patch('ok', '1.0.0', self::VALID_DIFF)])->export(TRUE);
    $this->assertStringContainsString('nenhum artefato foi gravado', $message);
    $this->assertFileDoesNotExist($this->project . '/patches-aculta.json');
    $this->assertDirectoryDoesNotExist($this->project . '/patches');
  }

  /**
   * Exportar duas vezes gera manifesto e artefatos byte a byte iguais.
   */
  public function testExportIsIdempotent(): void {
    $exporter = $this->exporter(['ok' => $this->patch('ok', '1.0.0', self::VALID_DIFF)]);
    $exporter->export();
    $firstManifest = (string) file_get_contents($this->project . '/patches-aculta.json');
    $firstArtifact = (string) file_get_contents($this->project . '/patches/aculta_patcher/ok.patch');
    $exporter->export();
    $this->assertSame($firstManifest, (string) file_get_contents($this->project . '/patches-aculta.json'));
    $this->assertSame($firstArtifact, (string) file_get_contents($this->project . '/patches/aculta_patcher/ok.patch'));
  }

  /**
   * Patch inativo não entra no manifesto, mesmo com diff válido.
   */
  public function testInactivePatchIsSkipped(): void {
    $this->exporter(['off' => $this->patch('off', '1.0.0', self::VALID_DIFF, FALSE)])->export();
    $manifest = json_decode((string) file_get_contents($this->project . '/patches-aculta.json'), TRUE);
    $this->assertSame([], $manifest['patches']);
  }

  /**
   * Versão diferente do composer.lock é recusada antes de qualquer aplicação.
   */
  public function testVersionMismatchWithLockIsRejected(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('composer.lock contém 1.0.0');
    $this->exporter(['ok' => $this->patch('ok', '2.0.0', self::VALID_DIFF)])->export(TRUE);
  }

  /**
   * Diff que não se aplica ao código instalado é recusado, nos dois sentidos.
   */
  public function testDiffThatDoesNotApplyIsRejected(): void {
    $diff = "diff --git a/src/Greeting.txt b/src/Greeting.txt\n--- a/src/Greeting.txt\n+++ b/src/Greeting.txt\n@@ -1,2 +1,2 @@\n nao\n-existe\n+x\n";
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('não se aplica ao pacote instalado');
    $this->exporter(['bad' => $this->patch('bad', '1.0.0', $diff)])->export(TRUE);
  }

  /**
   * Caminho que sai do pacote é recusado antes do git apply.
   */
  public function testPathOutsidePackageIsRejected(): void {
    $diff = "diff --git a/../x b/../x\n--- a/../x\n+++ b/../x\n@@ -1 +1 @@\n-a\n+b\n";
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('caminho fora do pacote');
    $this->exporter(['evil' => $this->patch('evil', '1.0.0', $diff)])->export(TRUE);
  }

  /**
   * Remove uma árvore temporária do teste.
   */
  private function removeTree(string $path): void {
    if (is_link($path) || is_file($path)) {
      unlink($path);
      return;
    }
    if (!is_dir($path)) {
      return;
    }
    foreach (scandir($path) ?: [] as $entry) {
      if ($entry !== '.' && $entry !== '..') {
        $this->removeTree($path . '/' . $entry);
      }
    }
    rmdir($path);
  }

}
