<?php

declare(strict_types=1);

namespace Drupal\aculta_patcher;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Discovers patches added to the Composer Patches manifest.
 */
final class PatchManifestImporter {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LockBackendInterface $lock,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly string $appRoot,
  ) {}

  /**
   * Imports missing local manifest entries as editable patch records.
   *
   * Existing records edited in the Patcher UI are preserved. The import only
   * runs when the administrative collection is loaded and is safe to repeat.
   *
   * @return int
   *   Number of records created or refreshed from their original source.
   */
  public function importMissing(): int {
    if (!$this->lock->acquire('aculta_patcher.manifest_import', 30.0)) {
      return 0;
    }

    try {
      return $this->doImport();
    }
    finally {
      $this->lock->release('aculta_patcher.manifest_import');
    }
  }

  /**
   * Imports entries while the import lock is held.
   */
  private function doImport(): int {
    $projectRoot = dirname($this->appRoot);
    $manifestPath = $projectRoot . '/patches-aculta.json';
    $lockPath = $projectRoot . '/composer.lock';
    if (is_link($manifestPath) || !is_file($manifestPath) || !is_file($lockPath)) {
      return 0;
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), TRUE);
    $composerLock = json_decode((string) file_get_contents($lockPath), TRUE);
    if (!is_array($manifest) || !is_array($manifest['patches'] ?? NULL) || !is_array($composerLock)) {
      $this->loggerFactory->get('aculta_patcher')->error('Não foi possível importar patches: manifesto Composer ou composer.lock inválido.');
      return 0;
    }

    $versions = [];
    foreach (array_merge($composerLock['packages'] ?? [], $composerLock['packages-dev'] ?? []) as $package) {
      if (isset($package['name'], $package['version'])) {
        $versions[$package['name']] = ltrim((string) $package['version'], 'v');
      }
    }

    $storage = $this->entityTypeManager->getStorage('aculta_patch');
    $entities = $storage->loadMultiple();
    $imported = 0;
    foreach ($manifest['patches'] as $package => $entries) {
      if (!preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', (string) $package) || !is_array($entries)) {
        continue;
      }
      $version = $versions[$package] ?? NULL;
      if ($version === NULL) {
        $this->loggerFactory->get('aculta_patcher')->warning('O pacote Composer %package dos patches não existe no composer.lock.', ['%package' => $package]);
        continue;
      }

      foreach ($entries as $entry) {
        if (!is_array($entry) || !isset($entry['url'], $entry['description'])) {
          continue;
        }
        $url = (string) $entry['url'];
        $patchPath = $this->resolvePatchPath($projectRoot, $url);
        if ($patchPath === NULL) {
          $this->loggerFactory->get('aculta_patcher')->warning('O patch %url foi ignorado: somente arquivos locais dentro da raiz do projeto podem ser importados.', ['%url' => $url]);
          continue;
        }
        $diff = rtrim((string) file_get_contents($patchPath)) . "\n";
        $hash = hash('sha256', $diff);
        if (isset($entry['sha256']) && !hash_equals((string) $entry['sha256'], $hash)) {
          $this->loggerFactory->get('aculta_patcher')->warning('O hash do patch %url não corresponde ao manifesto.', ['%url' => $url]);
          continue;
        }
        try {
          (new PatchDiffValidator())->assertValid(basename($url), $diff);
        }
        catch (\RuntimeException $exception) {
          $this->loggerFactory->get('aculta_patcher')->warning(
            'O patch %url não foi importado: %message',
            ['%url' => $url, '%message' => $exception->getMessage()],
          );
          continue;
        }

        $matched = NULL;
        foreach ($entities as $entity) {
          if ($entity->getPackage() === $package && $entity->get('source_url') === $url) {
            $matched = $entity;
            break;
          }
        }
        if ($matched !== NULL) {
          $baselineHash = (string) $matched->get('source_hash');
          $currentHash = hash('sha256', rtrim($matched->getPatch()) . "\n");
          if ($baselineHash !== '' && hash_equals($baselineHash, $currentHash) && !hash_equals($currentHash, $hash)) {
            $matched->set('patch', $diff);
            $matched->set('source_hash', $hash);
            $matched->save();
            $imported++;
          }
          continue;
        }

        // Associate legacy/UI-exported records by package and identical diff,
        // so a changed manifest URL never produces a duplicate row.
        foreach ($entities as $entity) {
          if ($entity->getPackage() === $package && hash_equals(hash('sha256', rtrim($entity->getPatch()) . "\n"), $hash)) {
            $entity->set('source_url', $url);
            $entity->set('source_hash', $hash);
            $entity->save();
            $imported++;
            continue 2;
          }
        }

        $id = 'import_' . substr(hash('sha256', $package . "\0" . $url), 0, 24);
        if (isset($entities[$id])) {
          continue;
        }
        $entity = $storage->create([
          'id' => $id,
          'label' => (string) $entry['description'],
          'status' => TRUE,
          'package' => $package,
          'version' => $version,
          'issue_url' => '',
          'patch' => $diff,
          'source_url' => $url,
          'source_hash' => $hash,
        ]);
        $entity->save();
        $entities[$id] = $entity;
        $imported++;
      }
    }
    return $imported;
  }

  /**
   * Resolves a local patch URL without allowing path traversal or symlinks.
   */
  private function resolvePatchPath(string $projectRoot, string $url): ?string {
    $segments = explode('/', $url);
    if ($url === '' || str_starts_with($url, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $url) || in_array('..', $segments, TRUE) || in_array('.', $segments, TRUE)) {
      return NULL;
    }
    $root = realpath($projectRoot);
    $path = realpath($projectRoot . '/' . $url);
    return $root !== FALSE && $path !== FALSE && is_file($path) && str_starts_with($path, $root . DIRECTORY_SEPARATOR)
      ? $path
      : NULL;
  }

}
