<?php

declare(strict_types=1);

namespace Drupal\aculta_patcher;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\Process\Process;

/** Exports active patch records as reviewed Composer Patches artifacts. */
final class PatchManifestExporter {

  private const PACKAGE_PATTERN = '/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly string $appRoot,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Validates active patches, then writes a Composer Patches v2 manifest.
   *
   * @throws \RuntimeException
   *   When a patch is invalid, stale, or cannot be applied to the locked source.
   */
  public function export(bool $checkOnly = FALSE): string {
    $projectRoot = dirname($this->appRoot);
    $lockPath = $projectRoot . '/composer.lock';
    $lock = is_file($lockPath) ? json_decode((string) file_get_contents($lockPath), TRUE) : NULL;
    if (!is_array($lock)) {
      throw new \RuntimeException('composer.lock não foi encontrado ou não é JSON válido.');
    }
    $lockedVersions = [];
    foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
      if (isset($package['name'], $package['version'])) {
        $lockedVersions[$package['name']] = ltrim((string) $package['version'], 'v');
      }
    }

    $patches = $this->entityTypeManager->getStorage('aculta_patch')->loadMultiple();
    uasort($patches, static fn ($a, $b): int => strcmp($a->id(), $b->id()));
    $manifest = ['patches' => []];
    $artifacts = [];
    foreach ($patches as $patch) {
      if (!$patch->status()) {
        continue;
      }
      $id = (string) $patch->id();
      $package = $patch->getPackage();
      $version = ltrim($patch->getVersion(), 'v');
      $diff = $patch->getPatch();
      if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $id) || !preg_match(self::PACKAGE_PATTERN, $package)) {
        throw new \RuntimeException(sprintf('Registro %s tem id ou pacote Composer inválido.', $id));
      }
      if (($lockedVersions[$package] ?? NULL) !== $version) {
        throw new \RuntimeException(sprintf('O patch %s exige %s %s; o composer.lock contém %s.', $id, $package, $version, $lockedVersions[$package] ?? 'pacote ausente'));
      }
      if (strlen($diff) > 262144 || !str_contains($diff, '--- ') || !str_contains($diff, '+++ ') || !preg_match('/^@@/m', $diff)) {
        throw new \RuntimeException(sprintf('O conteúdo do patch %s não é um unified diff válido ou excede 256 KiB.', $id));
      }
      if (preg_match('~^(?:---|\+\+\+)\s+(?!(?:/dev/null)(?:\s|$))(?:/|[A-Za-z]:[\\\\/])|^(?:---|\+\+\+)\s+.*(?:^|/)\.\.(?:/|$)~m', $diff)) {
        throw new \RuntimeException(sprintf('O patch %s aponta para um caminho fora do pacote.', $id));
      }

      $parts = explode('/', $package, 2);
      $packagePath = $projectRoot . '/vendor/' . $parts[0] . '/' . $parts[1];
      $realPackagePath = realpath($packagePath);
      if ($realPackagePath === FALSE || !str_starts_with($realPackagePath, realpath($projectRoot . '/vendor') . DIRECTORY_SEPARATOR)) {
        throw new \RuntimeException(sprintf('O pacote %s não está instalado em vendor/.', $package));
      }
      $applyCheck = $this->checkWithGitApply($realPackagePath, $diff, FALSE);
      if (!$applyCheck['success'] && !$this->checkWithGitApply($realPackagePath, $diff, TRUE)['success']) {
        throw new \RuntimeException(sprintf('O patch %s não se aplica ao pacote instalado com git apply: %s', $id, trim($applyCheck['output'])));
      }

      $relativePath = 'patches/aculta_patcher/' . $id . '.patch';
      $artifacts[$relativePath] = rtrim($diff) . "\n";
      $manifest['patches'][$package][] = [
        'description' => $patch->label(),
        'url' => $relativePath,
        'sha256' => hash('sha256', $artifacts[$relativePath]),
        'depth' => 1,
      ];
    }
    ksort($manifest['patches']);
    $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if ($checkOnly) {
      return sprintf('%d patch(es) ativo(s) validados; nenhum artefato foi gravado.', count($artifacts));
    }

    $artifactDirectory = $projectRoot . '/patches/aculta_patcher';
    if (is_link($artifactDirectory) || (!is_dir($artifactDirectory) && !mkdir($artifactDirectory, 0755, TRUE) && !is_dir($artifactDirectory))) {
      throw new \RuntimeException('Não foi possível preparar o diretório de artefatos patches/aculta_patcher.');
    }
    foreach ($artifacts as $relativePath => $contents) {
      $path = $projectRoot . '/' . $relativePath;
      if (is_link($path) || file_put_contents($path, $contents, LOCK_EX) === FALSE) {
        throw new \RuntimeException(sprintf('Não foi possível escrever o artefato %s.', $relativePath));
      }
    }
    $manifestPath = $projectRoot . '/patches-aculta.json';
    if (is_link($manifestPath) || file_put_contents($manifestPath, $manifestJson, LOCK_EX) === FALSE) {
      throw new \RuntimeException('Não foi possível escrever patches-aculta.json.');
    }
    return sprintf('%d patch(es) exportado(s). Revise patches-aculta.json e patches/aculta_patcher/ no Git antes de composer install.', count($artifacts));
  }

  /**
   * Checks a diff with the same strict Git patcher used by Composer Patches.
   *
   * Only files named by the diff are copied into a temporary directory. This
   * avoids writing to Composer-managed packages while still validating hunks
   * against their current source.
   *
   * @return array{success: bool, output: string}
   *   Check result and process output.
   */
  private function checkWithGitApply(string $packageRoot, string $diff, bool $reverse): array {
    preg_match_all('/^diff --git a\/([^\s]+) b\/([^\s]+)$/m', $diff, $matches, PREG_SET_ORDER);
    if ($matches === []) {
      return ['success' => FALSE, 'output' => 'O unified diff precisa declarar cabeçalhos diff --git.'];
    }
    $paths = [];
    foreach ($matches as $match) {
      foreach ([$match[1], $match[2]] as $path) {
        if ($path === 'dev/null') {
          continue;
        }
        $relative = preg_replace('~^[ab]/~', '', $path);
        $segments = explode('/', (string) $relative);
        if ($relative === '' || str_starts_with((string) $relative, '/') || in_array('..', $segments, TRUE) || in_array('.', $segments, TRUE)) {
          return ['success' => FALSE, 'output' => 'O diff aponta para um caminho inválido.'];
        }
        $paths[$relative] = TRUE;
      }
    }

    $temporaryRoot = rtrim($this->fileSystem->getTempDirectory(), DIRECTORY_SEPARATOR) . '/aculta-patcher-' . bin2hex(random_bytes(8));
    if (!mkdir($temporaryRoot, 0700)) {
      return ['success' => FALSE, 'output' => 'Não foi possível criar diretório temporário para validação.'];
    }
    try {
      foreach (array_keys($paths) as $relative) {
        $source = $packageRoot . '/' . $relative;
        if (is_link($source)) {
          return ['success' => FALSE, 'output' => 'O diff aponta para um arquivo simbólico.'];
        }
        $directory = dirname($temporaryRoot . '/' . $relative);
        if (!is_dir($directory) && !mkdir($directory, 0700, TRUE) && !is_dir($directory)) {
          return ['success' => FALSE, 'output' => 'Não foi possível preparar diretório temporário.'];
        }
        if (is_file($source) && !copy($source, $temporaryRoot . '/' . $relative)) {
          return ['success' => FALSE, 'output' => 'Não foi possível copiar fonte do pacote para validação.'];
        }
      }

      $gitInit = new Process(['git', '-C', $temporaryRoot, 'init', '--quiet']);
      $gitInit->setTimeout(10);
      $gitInit->run();
      if (!$gitInit->isSuccessful()) {
        return ['success' => FALSE, 'output' => trim($gitInit->getErrorOutput() . $gitInit->getOutput())];
      }
      $command = ['git', '-C', $temporaryRoot, 'apply', '--check', '--verbose', '-p1'];
      if ($reverse) {
        $command[] = '-R';
      }
      $process = new Process($command);
      $process->setInput($diff);
      $process->setTimeout(30);
      $process->run();
      return [
        'success' => $process->isSuccessful(),
        'output' => trim($process->getErrorOutput() . $process->getOutput()),
      ];
    }
    finally {
      $this->fileSystem->deleteRecursive($temporaryRoot);
    }
  }

}
