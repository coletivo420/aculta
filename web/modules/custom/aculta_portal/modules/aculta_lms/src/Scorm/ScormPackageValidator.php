<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Scorm;

/**
 * Valida um pacote SCORM ZIP antes de qualquer extração.
 *
 * Mede os bytes que cada entrada realmente produz, por streaming, porque os
 * tamanhos declarados no cabeçalho do ZIP podem ser forjados. Também recusa
 * caminhos inseguros, links simbólicos e pacote sem `imsmanifest.xml` na raiz.
 * Sem dependência do Drupal.
 */
final class ScormPackageValidator {

  /**
   * Nome do manifesto, obrigatório na raiz do pacote.
   */
  public const MANIFEST = 'imsmanifest.xml';

  /**
   * Máximo de entradas por pacote.
   */
  public const MAX_ENTRIES = 5000;

  /**
   * Máximo descompactado de uma entrada (64 MiB).
   */
  public const MAX_ENTRY_BYTES = 67108864;

  /**
   * Máximo descompactado do pacote inteiro (256 MiB).
   */
  public const MAX_TOTAL_BYTES = 268435456;

  /**
   * Razão máxima descompactado/compactado de uma entrada.
   */
  public const MAX_RATIO = 100;

  /**
   * A razão só vale acima deste tamanho descompactado (1 MiB).
   */
  public const RATIO_MIN_BYTES = 1048576;

  /**
   * Tamanho de leitura ao medir uma entrada.
   */
  private const READ_BYTES = 65536;

  /**
   * Constructs the validator; defaults match the constants above.
   */
  public function __construct(
    private readonly int $maxEntries = self::MAX_ENTRIES,
    private readonly int $maxEntryBytes = self::MAX_ENTRY_BYTES,
    private readonly int $maxTotalBytes = self::MAX_TOTAL_BYTES,
    private readonly int $maxRatio = self::MAX_RATIO,
    private readonly int $ratioMinBytes = self::RATIO_MIN_BYTES,
  ) {}

  /**
   * Rejeita o pacote quando algum critério falha.
   *
   * @throws \UnexpectedValueException
   *   Quando o ZIP não abre, excede um limite, tem nome ou link inseguro, ou não tem manifesto na raiz.
   */
  public function assertValid(string $zipPath): void {
    $zip = new \ZipArchive();
    if ($zip->open($zipPath, \ZipArchive::RDONLY) !== TRUE) {
      throw new \UnexpectedValueException('O pacote SCORM não é um ZIP válido.');
    }
    try {
      if ($zip->numFiles > $this->maxEntries) {
        throw new \UnexpectedValueException('O pacote SCORM tem entradas demais.');
      }
      if ($zip->locateName(self::MANIFEST) === FALSE) {
        throw new \UnexpectedValueException('O pacote SCORM não tem imsmanifest.xml na raiz.');
      }
      $total = 0;
      for ($index = 0; $index < $zip->numFiles; $index++) {
        $stat = $zip->statIndex($index);
        if ($stat === FALSE || !is_string($stat['name'] ?? NULL)) {
          throw new \UnexpectedValueException('O pacote SCORM contém uma entrada ilegível.');
        }
        $name = $stat['name'];
        $this->assertSafeName($name);
        $this->assertNotSymlink($zip, $index);
        if (str_ends_with($name, '/')) {
          continue;
        }
        $produced = $this->measure($zip, $name, $total);
        $compressed = (int) ($stat['comp_size'] ?? 0);
        if ($produced >= $this->ratioMinBytes && ($compressed === 0 || $produced / $compressed > $this->maxRatio)) {
          throw new \UnexpectedValueException('O pacote SCORM tem uma taxa de compressão suspeita.');
        }
      }
    }
    finally {
      $zip->close();
    }
  }

  /**
   * Rejeita caminho absoluto, de unidade, com barra invertida, NUL, segmento vazio, "." ou "..".
   */
  private function assertSafeName(string $name): void {
    if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')
      || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) === 1) {
      throw new \UnexpectedValueException('O pacote SCORM contém um caminho inválido.');
    }
    foreach (explode('/', rtrim($name, '/')) as $segment) {
      if ($segment === '' || $segment === '.' || $segment === '..') {
        throw new \UnexpectedValueException('O pacote SCORM contém um caminho inválido.');
      }
    }
  }

  /**
   * Rejeita link simbólico, pelos atributos externos do ZIP criados por Unix.
   */
  private function assertNotSymlink(\ZipArchive $zip, int $index): void {
    if (method_exists($zip, 'getExternalAttributesIndex')
      && $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)
      && $operatingSystem === \ZipArchive::OPSYS_UNIX
      && (($attributes >> 16) & 0170000) === 0120000) {
      throw new \UnexpectedValueException('O pacote SCORM contém um link simbólico.');
    }
  }

  /**
   * Bytes realmente descompactados de uma entrada, parando ao passar de algum limite.
   *
   * @param int $total
   *   Bytes já produzidos por entradas anteriores; atualizado por referência.
   */
  private function measure(\ZipArchive $zip, string $name, int &$total): int {
    $stream = $zip->getStream($name);
    if ($stream === FALSE) {
      throw new \UnexpectedValueException('O pacote SCORM contém uma entrada que não pode ser lida.');
    }
    $produced = 0;
    try {
      while (!feof($stream)) {
        $chunk = fread($stream, self::READ_BYTES);
        if ($chunk === FALSE) {
          throw new \UnexpectedValueException('O pacote SCORM contém uma entrada que não pode ser lida.');
        }
        $length = strlen($chunk);
        $produced += $length;
        $total += $length;
        if ($produced > $this->maxEntryBytes) {
          throw new \UnexpectedValueException('O pacote SCORM contém um arquivo grande demais.');
        }
        if ($total > $this->maxTotalBytes) {
          throw new \UnexpectedValueException('O pacote SCORM é grande demais descompactado.');
        }
      }
    }
    finally {
      fclose($stream);
    }
    return $produced;
  }

}
