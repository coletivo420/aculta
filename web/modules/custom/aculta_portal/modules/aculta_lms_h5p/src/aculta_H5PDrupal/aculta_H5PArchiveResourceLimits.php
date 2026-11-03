<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\aculta_H5PDrupal;

/**
 * Limits applied to H5P library packages before any extraction.
 *
 * Path and symlink checks do not bound the work an archive can cause: a small
 * package can declare or expand into gigabytes (ZIP bomb). This class measures
 * the bytes each entry really produces by streaming it, because the sizes
 * declared in the ZIP headers can be forged.
 *
 * Dependency-free on purpose, so it can be tested without a Drupal bootstrap.
 */
final class aculta_H5PArchiveResourceLimits {

  /**
   * Maximum number of entries in one package.
   */
  public const MAX_ENTRIES = 10000;

  /**
   * Maximum decompressed size of one entry (32 MiB).
   */
  public const MAX_ENTRY_BYTES = 33554432;

  /**
   * Maximum decompressed size of the whole package (256 MiB).
   */
  public const MAX_TOTAL_BYTES = 268435456;

  /**
   * Maximum decompressed/compressed ratio for an entry.
   */
  public const MAX_RATIO = 100;

  /**
   * Ratio is only enforced above this decompressed size (1 MiB).
   *
   * Small text files compress well and would otherwise be rejected.
   */
  public const RATIO_MIN_BYTES = 1048576;

  /**
   * Read size used while streaming an entry.
   */
  private const CHUNK_BYTES = 65536;

  /**
   * Constructs the limits; defaults match the constants above.
   */
  public function __construct(
    private readonly int $maxEntries = self::MAX_ENTRIES,
    private readonly int $maxEntryBytes = self::MAX_ENTRY_BYTES,
    private readonly int $maxTotalBytes = self::MAX_TOTAL_BYTES,
    private readonly int $maxRatio = self::MAX_RATIO,
    private readonly int $ratioMinBytes = self::RATIO_MIN_BYTES,
  ) {}

  /**
   * Rejects the archive when any limit is exceeded.
   *
   * @throws \UnexpectedValueException
   *   When the archive exceeds a limit or an entry cannot be read.
   */
  public function assertWithinLimits(\ZipArchive $zip): void {
    $count = $zip->numFiles;
    if ($count > $this->maxEntries) {
      throw new \UnexpectedValueException('O pacote H5P tem entradas demais.');
    }

    $total = 0;
    for ($index = 0; $index < $count; $index++) {
      $stat = $zip->statIndex($index);
      if ($stat === FALSE || !is_string($stat['name'] ?? NULL)) {
        throw new \UnexpectedValueException('O pacote H5P contém uma entrada ilegível.');
      }
      $name = $stat['name'];
      // Directory entries carry no data.
      if (str_ends_with($name, '/')) {
        continue;
      }

      $produced = $this->streamEntry($zip, $name, $total);
      $compressed = (int) ($stat['comp_size'] ?? 0);
      if ($produced >= $this->ratioMinBytes
        && ($compressed === 0 || $produced / $compressed > $this->maxRatio)
      ) {
        throw new \UnexpectedValueException('O pacote H5P tem uma taxa de compressão suspeita.');
      }
    }
  }

  /**
   * Streams one entry, counting bytes and stopping at the first excess.
   *
   * @param \ZipArchive $zip
   *   The open archive.
   * @param string $name
   *   Entry name.
   * @param int $total
   *   Bytes already produced by previous entries; updated by reference.
   *
   * @return int
   *   Decompressed bytes produced by this entry.
   *
   * @throws \UnexpectedValueException
   *   When the entry or the package exceeds its limit, or cannot be read.
   */
  private function streamEntry(\ZipArchive $zip, string $name, int &$total): int {
    $stream = $zip->getStream($name);
    if ($stream === FALSE) {
      throw new \UnexpectedValueException('O pacote H5P contém uma entrada que não pode ser lida.');
    }

    $produced = 0;
    try {
      while (!feof($stream)) {
        $chunk = fread($stream, self::CHUNK_BYTES);
        if ($chunk === FALSE) {
          throw new \UnexpectedValueException('O pacote H5P contém uma entrada que não pode ser lida.');
        }
        $length = strlen($chunk);
        $produced += $length;
        $total += $length;
        if ($produced > $this->maxEntryBytes) {
          throw new \UnexpectedValueException('O pacote H5P contém um arquivo grande demais.');
        }
        if ($total > $this->maxTotalBytes) {
          throw new \UnexpectedValueException('O pacote H5P é grande demais descompactado.');
        }
      }
    }
    finally {
      fclose($stream);
    }

    return $produced;
  }

}
