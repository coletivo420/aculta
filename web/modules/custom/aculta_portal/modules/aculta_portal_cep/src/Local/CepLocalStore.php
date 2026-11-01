<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Local;

use Drupal\Core\File\FileSystemInterface;

/**
 * Reads and imports the local file-based CEP database.
 *
 * Records are stored in private://aculta_portal_cep/shards, split by the first
 * three digits. Each TSV line has CEP, street, district, city, state, and IBGE
 * code fields. The import command generates files outside Git.
 *
 * The repository documentation records the accepted data-license risk.
 */
final class CepLocalStore {

  public const DIRECTORY = 'private://aculta_portal_cep/shards';

  /**
   * Manifest containing the import date, record count, and source filename.
   */
  public const MANIFEST = 'private://aculta_portal_cep/manifest.json';

  public function __construct(private readonly FileSystemInterface $fileSystem) {}

  /**
   * Looks up a CEP in the local database.
   *
   * @return array<string, string>|null
   *   Address fields, or NULL when the CEP is not present.
   */
  public function lookup(string $cep): ?array {
    $cep = preg_replace('/\D+/', '', $cep) ?? '';
    if (strlen($cep) !== 8) {
      return NULL;
    }
    $path = $this->fileSystem->realpath(self::DIRECTORY . '/' . substr($cep, 0, 3) . '.tsv');
    if ($path === FALSE || !is_file($path)) {
      return NULL;
    }
    $handle = fopen($path, 'rb');
    if ($handle === FALSE) {
      return NULL;
    }
    try {
      while (($line = fgets($handle)) !== FALSE) {
        if (str_starts_with($line, $cep . "\t")) {
          return $this->mapped($cep, rtrim($line, "\r\n"));
        }
      }
    }
    finally {
      fclose($handle);
    }
    return NULL;
  }

  /**
   * Imports a CEP-sorted TSV and returns the number of records written.
   *
   * @throws \RuntimeException
   *   When the source file cannot be read or contains an invalid line.
   */
  public function importTsv(string $source): int {
    $handle = fopen($source, 'rb');
    if ($handle === FALSE) {
      throw new \RuntimeException('Não foi possível ler o arquivo de origem.');
    }
    $directory = self::DIRECTORY;
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $count = 0;
    $prefix = NULL;
    $buffer = '';
    try {
      while (($line = fgets($handle)) !== FALSE) {
        $fields = explode("\t", rtrim($line, "\r\n"));
        if (count($fields) !== 6 || !preg_match('/^\d{8}$/', $fields[0])) {
          throw new \RuntimeException('Linha inválida no arquivo de origem (esperado 6 campos e CEP de 8 dígitos).');
        }
        $current = substr($fields[0], 0, 3);
        if ($prefix !== NULL && $current !== $prefix) {
          $this->writeShard($prefix, $buffer);
          $buffer = '';
        }
        $prefix = $current;
        $buffer .= implode("\t", $fields) . "\n";
        $count++;
      }
      if ($prefix !== NULL) {
        $this->writeShard($prefix, $buffer);
      }
    }
    finally {
      fclose($handle);
    }
    $this->makeReadable();
    return $count;
  }

  /**
   * Writes the local database manifest.
   *
   * @param string $version
   *   Import date in Y-m-d format.
   * @param int $records
   *   Number of records imported.
   * @param string $source
   *   Source filename.
   */
  public function writeManifest(string $version, int $records, string $source): void {
    $manifest = ['version' => $version, 'records' => $records, 'source' => $source];
    $this->fileSystem->prepareDirectory(dirname(self::MANIFEST), FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $this->fileSystem->saveData((string) json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), self::MANIFEST, FileSystemInterface::EXISTS_REPLACE);
    $this->makeReadable();
  }

  /**
   * Makes imported public data readable by the web processes.
   *
   * Files remain under private://, outside the web root. This also allows the
   * Portal status check to read files created by another local user.
   */
  private function makeReadable(): void {
    $directory = $this->fileSystem->realpath(self::DIRECTORY);
    if ($directory === FALSE) {
      return;
    }
    $root = dirname($directory);
    foreach ([$root, $directory] as $dir) {
      @chmod($dir, 0755);
    }
    foreach (glob($directory . '/*.tsv') ?: [] as $file) {
      @chmod($file, 0644);
    }
    $manifest = $this->fileSystem->realpath(self::MANIFEST);
    if ($manifest !== FALSE) {
      @chmod($manifest, 0644);
    }
  }

  /**
   * Reads the local database manifest.
   *
   * @return array{version: string, records?: int, source?: string}|null
   *   Manifest data, or NULL when no valid manifest is available.
   */
  public function manifest(): ?array {
    $path = $this->fileSystem->realpath(self::MANIFEST);
    if ($path === FALSE || !is_file($path)) {
      return NULL;
    }
    $data = json_decode((string) file_get_contents($path), TRUE);
    return is_array($data) && isset($data['version']) ? $data : NULL;
  }

  /**
   * Counts prefix files in the local database.
   */
  public function shardCount(): int {
    $path = $this->fileSystem->realpath(self::DIRECTORY);
    return $path === FALSE ? 0 : count(glob($path . '/*.tsv') ?: []);
  }

  /**
   * Writes one prefix file, replacing its previous contents.
   */
  private function writeShard(string $prefix, string $content): void {
    $uri = self::DIRECTORY . '/' . $prefix . '.tsv';
    $this->fileSystem->saveData($content, $uri, FileSystemInterface::EXISTS_REPLACE);
  }

  /**
   * Maps a TSV record to Drupal Address keys.
   *
   * @param string $cep
   *   Eight-digit CEP.
   * @param string $line
   *   Tab-separated record.
   *
   * @return array<string, string>
   *   Address field values.
   */
  private function mapped(string $cep, string $line): array {
    [, $street, $district, $city, $state] = array_pad(explode("\t", $line), 6, '');
    return [
      'postal_code' => $cep,
      'address_line1' => $street,
      'dependent_locality' => $district,
      'locality' => $city,
      'administrative_area' => $state,
    ];
  }

}
